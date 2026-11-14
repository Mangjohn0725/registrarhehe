<?php
declare(strict_types=1);
final class AdminApi {
    public static function dashboard(): never {
        Auth::requireRole('admin');$date=date('Y-m-d');
        $stats=Core::one("SELECT COUNT(*) AS total,SUM(status='waiting') AS waiting,SUM(status='called') AS called,SUM(status='serving') AS serving,SUM(status='completed') AS completed, COALESCE(AVG(CASE WHEN called_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,created_at,called_at) END),0) AS avg_wait, COALESCE(AVG(CASE WHEN completed_at IS NOT NULL AND started_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,started_at,completed_at) END),0) AS avg_process FROM queues WHERE queue_date=?",[$date]);
        $staff=Core::one("SELECT COUNT(*) AS n FROM users WHERE role='staff' AND status='active'")['n'];
        $counters=Core::one('SELECT COUNT(*) AS n FROM counters WHERE is_active=1')['n'];
        $trend=Core::all("SELECT DATE_FORMAT(queue_date,'%Y-%m-%d') AS day,COUNT(*) AS tickets,SUM(status='completed') AS completed FROM queues WHERE queue_date>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY queue_date ORDER BY queue_date");
        $byService=Core::all("SELECT sv.service_name AS name,COUNT(q.id) AS value FROM services sv LEFT JOIN queues q ON q.service_id=sv.id AND q.queue_date=CURDATE() GROUP BY sv.id,sv.service_name ORDER BY value DESC LIMIT 8");
        $recent=Core::all('SELECT '.QueueApi::selectFields().QueueApi::joins().' WHERE q.queue_date=? ORDER BY q.created_at DESC LIMIT 10',[$date]);
        Core::respond(['stats'=>$stats,'active_staff'=>$staff,'active_counters'=>$counters,'trend'=>$trend,'services'=>$byService,'recent'=>$recent]);
    }
    public static function staff(): never {
        Auth::requireRole('admin');
        Core::respond(Core::all("SELECT st.id,st.employee_number,u.id AS user_id,u.full_name,u.username,u.email,u.status,u.must_change_password,c.id AS counter_id,c.counter_name,c.counter_number FROM staff st JOIN users u ON u.id=st.user_id LEFT JOIN staff_counter_assignments a ON a.staff_id=st.id AND a.ended_at IS NULL LEFT JOIN counters c ON c.id=a.counter_id ORDER BY st.id DESC"));
    }
    public static function createStaff(): never {
        $u=Auth::requireRole('admin');$d=Core::json();
        $name=Core::field($d,'full_name',160);$email=Core::field($d,'email');$username=Core::field($d,'username',80);$employee=Core::field($d,'employee_number',45);
        $password=(string)($d['password']??'');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new ApiException('Invalid email.');
        if(!preg_match('/^[A-Za-z0-9_.-]{3,80}$/',$username)) throw new ApiException('Invalid username.');
        if(strlen($password)<10) throw new ApiException('Temporary password must contain at least 10 characters.');
        $counterId=!empty($d['counter_id'])?Core::positiveId($d['counter_id']):null;
        if($counterId&&!Core::one('SELECT id FROM counters WHERE id=? AND is_active=1',[$counterId])) throw new ApiException('Counter not found.',404);
        $db=Core::db();$db->beginTransaction();
        try {
            Core::q("INSERT INTO users(username,email,password_hash,full_name,role,must_change_password) VALUES(?,?,?,?,'staff',1)",[$username,$email,password_hash($password,PASSWORD_DEFAULT),$name]);
            $uid=(int)$db->lastInsertId();
            Core::q('INSERT INTO staff(user_id,employee_number) VALUES(?,?)',[$uid,$employee]);
            $id=(int)$db->lastInsertId();
            if($counterId) self::assignStaff($id,$counterId);
            Core::audit((int)$u['id'],'staff.create','staff',$id);
            $db->commit();Core::respond(['id'=>$id],201,'Staff account created.');
        } catch(Throwable $e){$db->rollBack();self::duplicate($e);throw $e;}
    }
    private static function duplicate(Throwable $e): void { if($e instanceof PDOException && $e->getCode()==='23000') throw new ApiException('This username, email or employee ID is already in use.',409); }
    private static function assignStaff(int $id,?int $counterId): void {
        $previous=Core::one('SELECT counter_id FROM staff_counter_assignments WHERE staff_id=? AND ended_at IS NULL LIMIT 1',[$id]);
        if($previous && (int)$previous['counter_id']!==$counterId) {
            if(Core::one("SELECT id FROM queues WHERE staff_id=? AND status IN ('called','serving') LIMIT 1",[$id])) throw new ApiException('Finish this staff member\'s current ticket before changing counter.',409);
        }
        if($counterId) {
            $occupied=Core::one('SELECT staff_id FROM staff_counter_assignments WHERE counter_id=? AND ended_at IS NULL LIMIT 1',[$counterId]);
            if($occupied && (int)$occupied['staff_id']!==$id && Core::one("SELECT id FROM queues WHERE staff_id=? AND status IN ('called','serving') LIMIT 1",[$occupied['staff_id']])) throw new ApiException('This counter has an unfinished ticket. Complete it before reassigning.',409);
        }
        Core::q('UPDATE staff_counter_assignments SET ended_at=NOW() WHERE staff_id=? AND ended_at IS NULL',[$id]);
        if($counterId) {
            // A physical counter has one active assigned staff member at a time.
            Core::q('UPDATE staff_counter_assignments SET ended_at=NOW() WHERE counter_id=? AND ended_at IS NULL',[$counterId]);
            Core::q('INSERT INTO staff_counter_assignments(staff_id,counter_id) VALUES(?,?)',[$id,$counterId]);
        }
    }
    public static function updateStaff(int $id): never {
        $u=Auth::requireRole('admin');$d=Core::json();
        $staff=Core::one('SELECT st.id,st.user_id FROM staff st WHERE st.id=?',[$id]);if(!$staff) throw new ApiException('Staff not found.',404);
        $name=Core::field($d,'full_name',160);$email=Core::field($d,'email');$username=Core::field($d,'username',80);$employee=Core::field($d,'employee_number',45);
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||!preg_match('/^[A-Za-z0-9_.-]{3,80}$/',$username)) throw new ApiException('Invalid username or email.');
        $counterId=!empty($d['counter_id'])?Core::positiveId($d['counter_id']):null;
        if($counterId&&!Core::one('SELECT id FROM counters WHERE id=? AND is_active=1',[$counterId])) throw new ApiException('Invalid counter.',404);
        $db=Core::db();$db->beginTransaction();
        try {
            Core::q('UPDATE users SET full_name=?,email=?,username=? WHERE id=?',[$name,$email,$username,$staff['user_id']]);
            Core::q('UPDATE staff SET employee_number=? WHERE id=?',[$employee,$id]);
            self::assignStaff($id,$counterId);
            Core::audit((int)$u['id'],'staff.update','staff',$id);
            $db->commit();Core::respond(['saved'=>true],200,'Staff updated.');
        } catch(Throwable $e){$db->rollBack();self::duplicate($e);throw $e;}
    }
    public static function statusStaff(int $id): never {
        $u=Auth::requireRole('admin');$d=Core::json();
        $status=($d['status']??'')==='active'?'active':(($d['status']??'')==='inactive'?'inactive':null);
        if(!$status) throw new ApiException('Status must be active or inactive.');
        if($status==='inactive' && Core::one("SELECT id FROM queues WHERE staff_id=? AND status IN ('called','serving') LIMIT 1",[$id])) throw new ApiException('Complete or skip this staff member\'s active ticket first.',409);
        $stmt=Core::q("UPDATE users u JOIN staff s ON s.user_id=u.id SET u.status=? WHERE s.id=?",[$status,$id]);
        if(!$stmt->rowCount()) throw new ApiException('Staff not found or status unchanged.',404);
        Core::audit((int)$u['id'],'staff.status','staff',$id,$status);
        Core::respond(['status'=>$status]);
    }
    public static function resetUserPassword(string $kind,int $id): never {
        $u=Auth::requireRole('admin');$d=Core::json();
        $password=(string)($d['new_password']??'');
        if(strlen($password)<10) throw new ApiException('Temporary password must contain at least 10 characters.');
        $table=$kind==='staff'?'staff':'students';
        $target=Core::one("SELECT user_id FROM $table WHERE id=?",[$id]);
        if(!$target) throw new ApiException('Account not found.',404);
        Core::q('UPDATE users SET password_hash=?,must_change_password=1 WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$target['user_id']]);
        Core::q("UPDATE password_reset_requests SET status='resolved',resolved_at=NOW() WHERE user_id=? AND status='pending'",[$target['user_id']]);
        Core::audit((int)$u['id'],$kind.'.password_reset',$kind,$id);
        Core::respond(['reset'=>true],200,'Temporary password set. Share it securely with the user.');
    }
    public static function students(): never {
        Auth::requireRole('admin');
        Core::respond(Core::all('SELECT s.id,s.student_number,s.program,s.year_level,s.verification_status,u.full_name,u.username,u.email,u.status,u.created_at FROM students s JOIN users u ON u.id=s.user_id ORDER BY u.created_at DESC LIMIT 500'));
    }
    public static function verifyStudent(int $id): never {
        $u=Auth::requireRole('admin');$d=Core::json();
        $value=($d['verification_status']??'')==='verified'?'verified':(($d['verification_status']??'')==='pending'?'pending':null);
        if(!$value) throw new ApiException('Invalid verification status.');
        Core::q('UPDATE students SET verification_status=? WHERE id=?',[$value,$id]);
        Core::audit((int)$u['id'],'student.verification','student',$id,$value);
        Core::respond(['verification_status'=>$value]);
    }
    public static function resetRequests(): never {
        Auth::requireRole('admin');
        Core::respond(Core::all("SELECT pr.id,pr.requested_at,pr.status,u.full_name,u.username,u.role,st.id AS staff_id,s.id AS student_id FROM password_reset_requests pr JOIN users u ON u.id=pr.user_id LEFT JOIN staff st ON st.user_id=u.id LEFT JOIN students s ON s.user_id=u.id WHERE pr.status='pending' ORDER BY pr.requested_at DESC LIMIT 100"));
    }
    public static function services(): never {
        Auth::requireRole('admin');Core::respond(Core::all('SELECT * FROM services ORDER BY is_active DESC,service_name'));
    }
    public static function serviceSave(?int $id): never {
        $u=Auth::requireRole('admin');$d=Core::json();
        $code=strtoupper(Core::field($d,'service_code',30));$name=Core::field($d,'service_name',160);
        $prefix=strtoupper(Core::field($d,'queue_prefix',8));$description=Core::field($d,'description',3000,false);
        $minutes=(int)($d['estimated_minutes']??10);
        if(!preg_match('/^[A-Z0-9_-]{2,30}$/',$code)||!preg_match('/^[A-Z0-9]{2,8}$/',$prefix)||$minutes<1||$minutes>240) throw new ApiException('Invalid service code, prefix, or duration.');
        try {
            if($id) {
                Core::q('UPDATE services SET service_code=?,service_name=?,description=?,queue_prefix=?,estimated_minutes=?,is_active=? WHERE id=?',[$code,$name,$description,$prefix,$minutes,empty($d['is_active'])?0:1,$id]);
            } else {
                Core::q('INSERT INTO services(service_code,service_name,description,queue_prefix,estimated_minutes) VALUES(?,?,?,?,?)',[$code,$name,$description,$prefix,$minutes]);$id=(int)Core::db()->lastInsertId();
            }
        } catch(PDOException $e){self::duplicate($e);throw $e;}
        Core::audit((int)$u['id'],'service.save','service',$id);Core::respond(['id'=>$id]);
    }
    public static function serviceDisable(int $id): never {
        $u=Auth::requireRole('admin');Core::q('UPDATE services SET is_active=0 WHERE id=?',[$id]);Core::audit((int)$u['id'],'service.disable','service',$id);Core::respond(['id'=>$id]);
    }
    public static function counters(): never {
        Auth::requireRole('admin');
        $rows=Core::all('SELECT * FROM counters ORDER BY is_active DESC,counter_number');
        foreach($rows as &$c) {$c['service_ids']=array_map('intval',array_column(Core::all('SELECT service_id FROM counter_services WHERE counter_id=?',[$c['id']]),'service_id'));}
        Core::respond($rows);
    }
    public static function counterSave(?int $id): never {
        $u=Auth::requireRole('admin');$d=Core::json();
        $number=Core::field($d,'counter_number',20);$name=Core::field($d,'counter_name',120);
        $serviceIds=array_unique(array_map('intval',(array)($d['service_ids']??[])));
        $db=Core::db();$db->beginTransaction();
        try {
            if($id) Core::q('UPDATE counters SET counter_number=?,counter_name=?,is_active=? WHERE id=?',[$number,$name,empty($d['is_active'])?0:1,$id]);
            else {Core::q('INSERT INTO counters(counter_number,counter_name) VALUES(?,?)',[$number,$name]);$id=(int)$db->lastInsertId();}
            Core::q('DELETE FROM counter_services WHERE counter_id=?',[$id]);
            foreach($serviceIds as $sid) {if($sid>0) Core::q('INSERT INTO counter_services(counter_id,service_id) VALUES(?,?)',[$id,$sid]);}
            Core::audit((int)$u['id'],'counter.save','counter',$id);
            $db->commit();Core::respond(['id'=>$id]);
        } catch(Throwable $e){$db->rollBack();self::duplicate($e);throw $e;}
    }
    public static function counterDisable(int $id): never {
        $u=Auth::requireRole('admin');Core::q('UPDATE counters SET is_active=0 WHERE id=?',[$id]);Core::audit((int)$u['id'],'counter.disable','counter',$id);Core::respond(['id'=>$id]);
    }
    public static function reports(): never {
        Auth::requireRole('admin');$days=max(1,min(365,(int)($_GET['days']??14)));
        $metrics=Core::one("SELECT COUNT(*) tickets,SUM(status='completed') completed,SUM(status='cancelled') cancelled,SUM(status='skipped') skipped,ROUND(AVG(CASE WHEN called_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,created_at,called_at) END),1) avg_wait,ROUND(AVG(CASE WHEN completed_at IS NOT NULL AND started_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,started_at,completed_at) END),1) avg_service FROM queues WHERE queue_date>=DATE_SUB(CURDATE(),INTERVAL $days DAY)");
        $trend=Core::all("SELECT DATE_FORMAT(queue_date,'%Y-%m-%d') day,COUNT(*) tickets,SUM(status='completed') completed FROM queues WHERE queue_date>=DATE_SUB(CURDATE(),INTERVAL $days DAY) GROUP BY queue_date ORDER BY queue_date");
        $services=Core::all("SELECT s.service_name name,COUNT(q.id) tickets,SUM(q.status='completed') completed FROM services s LEFT JOIN queues q ON q.service_id=s.id AND q.queue_date>=DATE_SUB(CURDATE(),INTERVAL $days DAY) GROUP BY s.id,s.service_name ORDER BY tickets DESC");
        $staff=Core::all("SELECT u.full_name name,st.employee_number,COUNT(q.id) handled,SUM(q.status='completed') completed,ROUND(AVG(CASE WHEN q.completed_at IS NOT NULL AND q.started_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,q.started_at,q.completed_at) END),1) avg_minutes FROM staff st JOIN users u ON u.id=st.user_id LEFT JOIN queues q ON q.staff_id=st.id AND q.queue_date>=DATE_SUB(CURDATE(),INTERVAL $days DAY) GROUP BY st.id,u.full_name,st.employee_number ORDER BY completed DESC");
        Core::respond(compact('metrics','trend','services','staff'));
    }
    public static function export(): never {
        Auth::requireRole('admin');
        $days=max(1,min(365,(int)($_GET['days']??14)));
        $data=Core::all('SELECT q.queue_number,q.queue_date,u.full_name AS student,s.student_number,sv.service_name,q.status,c.counter_number,q.created_at,q.called_at,q.started_at,q.completed_at FROM queues q JOIN students s ON s.id=q.student_id JOIN users u ON u.id=s.user_id JOIN services sv ON sv.id=q.service_id LEFT JOIN counters c ON c.id=q.counter_id WHERE q.queue_date>=DATE_SUB(CURDATE(),INTERVAL '.$days.' DAY) ORDER BY q.created_at DESC');
        header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="registrar-report-'.date('Y-m-d').'.csv"');
        $out=fopen('php://output','w'); fputcsv($out,['Ticket','Date','Student','Student Number','Service','Status','Counter','Created','Called','Started','Completed']);
        foreach($data as $row){foreach($row as &$cell){if(is_string($cell)&&preg_match('/^[=+@\-\t\r]/',$cell))$cell="'".$cell;}unset($cell);fputcsv($out,array_values($row));}
        fclose($out);exit;
    }
    public static function audit(): never {
        Auth::requireRole('admin');
        Core::respond(Core::all('SELECT a.*,u.full_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT 200'));
    }
    public static function settings(): never {
        Auth::requireRole('admin');$items=Core::all('SELECT setting_key,setting_value FROM system_settings');Core::respond(array_column($items,'setting_value','setting_key'));
    }
    public static function settingsSave(): never {
        $u=Auth::requireRole('admin');$d=Core::json();$allowed=['office_name','opening_time','closing_time','queue_reset_daily','allow_unverified_students','announcement_ticker','queue_suspended','enforce_office_hours'];
        foreach($allowed as $key){if(array_key_exists($key,$d)){
            $value=Core::field($d,$key,400,false);
            if(in_array($key,['opening_time','closing_time'],true)&&!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',$value)) throw new ApiException('Invalid operating time.');
            if(in_array($key,['queue_reset_daily','allow_unverified_students','queue_suspended','enforce_office_hours'],true)&&!in_array($value,['0','1'],true)) throw new ApiException('Invalid setting.');
            Core::q('INSERT INTO system_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$key,$value]);
        }}
        Core::audit((int)$u['id'],'settings.update','settings');Core::respond(['saved'=>true]);
    }
}
