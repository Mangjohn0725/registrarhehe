<?php
declare(strict_types=1);
final class QueueApi {
    public static function selectFields(): string {
        return "q.*,sv.service_name,sv.service_code,sv.estimated_minutes,c.counter_number,c.counter_name,u.full_name AS student_name,st.student_number,staff_user.full_name AS staff_name";
    }
    public static function joins(): string {
        return ' FROM queues q JOIN services sv ON sv.id=q.service_id JOIN students st ON st.id=q.student_id JOIN users u ON u.id=st.user_id LEFT JOIN counters c ON c.id=q.counter_id LEFT JOIN staff sf ON sf.id=q.staff_id LEFT JOIN users staff_user ON staff_user.id=sf.user_id';
    }
    public static function ticket(): never {
        $u=Auth::requireRole('student');$d=Core::json();
        if($u['verification_status']!=='verified') throw new ApiException('Your student account needs verification before joining a queue.',403);
        if(Core::setting('queue_suspended','0')==='1') throw new ApiException('Queue ticket issuance is temporarily suspended.',409);
        if(Core::setting('enforce_office_hours','0')==='1') {
            $now=date('H:i');
            if($now<Core::setting('opening_time','08:00') || $now>Core::setting('closing_time','17:00')) throw new ApiException('Ticket issuance is closed outside office operating hours.',409);
        }
        $serviceId=Core::positiveId($d['service_id']??0);
        $db=Core::db();$db->beginTransaction();
        try {
            // Serialize ticket issuance per student to block duplicate active requests.
            Core::one('SELECT id FROM students WHERE id=? FOR UPDATE',[$u['student_id']]);
            $service=Core::one('SELECT * FROM services WHERE id=? AND is_active=1',[$serviceId]);
            if(!$service) throw new ApiException('Service is unavailable.',404);
            if(!Core::one('SELECT cs.counter_id FROM counter_services cs JOIN counters c ON c.id=cs.counter_id WHERE cs.service_id=? AND c.is_active=1 LIMIT 1',[$serviceId])) throw new ApiException('No active counter currently offers this service.',409);
            if(Core::one("SELECT id FROM queues WHERE student_id=? AND status IN ('waiting','called','serving') LIMIT 1",[$u['student_id']])) throw new ApiException('You already have an active queue ticket. Complete or cancel it first.',409);
            $today=date('Y-m-d');
            $reset=Core::setting('queue_reset_daily','1')==='1';
            $seqDate=$reset?$today:'2000-01-01';
            Core::q('INSERT INTO queue_sequences(queue_date,service_id,last_number) VALUES(?,?,1) ON DUPLICATE KEY UPDATE last_number=last_number+1',[$seqDate,$serviceId]);
            $seq=(int)Core::one('SELECT last_number FROM queue_sequences WHERE queue_date=? AND service_id=? FOR UPDATE',[$seqDate,$serviceId])['last_number'];
            $number=$service['queue_prefix'].'-'.str_pad((string)$seq,3,'0',STR_PAD_LEFT);
            Core::q("INSERT INTO queues(student_id,service_id,queue_date,queue_sequence,queue_number) VALUES(?,?,?,?,?)",[$u['student_id'],$serviceId,$today,$seq,$number]);
            $id=(int)$db->lastInsertId();
            self::event($id,(int)$u['id'],null,'waiting','ticket.created');
            Core::audit((int)$u['id'],'queue.create','queue',$id);
            $db->commit();
            Core::respond(['id'=>$id,'queue_number'=>$number],201,'Your queue ticket has been issued.');
        } catch(Throwable $e){$db->rollBack();throw $e;}
    }
    private static function event(int $qid,int $uid,?string $from,string $to,string $action): void {
        Core::q('INSERT INTO queue_events(queue_id,actor_user_id,previous_status,new_status,event_type) VALUES(?,?,?,?,?)',[$qid,$uid,$from,$to,$action]);
    }
    public static function myActive(): never {
        $u=Auth::requireRole('student');
        $rows=Core::all('SELECT '.self::selectFields().self::joins()." WHERE q.student_id=? AND q.status IN ('waiting','called','serving') ORDER BY q.created_at DESC",[$u['student_id']]);
        foreach($rows as &$row) {
            if($row['status']==='waiting') {
                $ahead=(int)(Core::one("SELECT COUNT(*) AS n FROM queues WHERE queue_date=? AND service_id=? AND status='waiting' AND (priority_level>? OR (priority_level=? AND (created_at<? OR (created_at=? AND id<?))))",[$row['queue_date'],$row['service_id'],$row['priority_level'],$row['priority_level'],$row['created_at'],$row['created_at'],$row['id']])['n']??0);
                $counterCount=(int)(Core::one('SELECT COUNT(DISTINCT c.id) AS n FROM counters c JOIN counter_services cs ON cs.counter_id=c.id WHERE c.is_active=1 AND cs.service_id=?',[$row['service_id']])['n']??1);
                $row['people_ahead']=$ahead;
                $row['position']=$ahead+1;
                $row['estimated_wait_minutes']=(int)ceil($ahead*(int)$row['estimated_minutes']/max(1,$counterCount));
            } else { $row['people_ahead']=0;$row['position']=0;$row['estimated_wait_minutes']=0; }
        }
        Core::respond($rows);
    }
    public static function myHistory(): never {
        $u=Auth::requireRole('student');
        Core::respond(Core::all('SELECT '.self::selectFields().self::joins().' WHERE q.student_id=? ORDER BY q.created_at DESC LIMIT 100',[$u['student_id']]));
    }
    public static function list(): never {
        $u=Auth::requireRole('admin','staff');[$page,$limit,$offset]=Core::page();
        $where=' WHERE 1=1';$params=[];
        if($u['role']==='staff') { $where.=' AND q.staff_id=?';$params[]=$u['staff_id']; }
        if(!empty($_GET['status'])) {$valid=['waiting','called','serving','completed','skipped','cancelled'];if(in_array($_GET['status'],$valid,true)){$where.=' AND q.status=?';$params[]=$_GET['status'];}}
        if(!empty($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$_GET['date'])){$where.=' AND q.queue_date=?';$params[]=$_GET['date'];}
        if(!empty($_GET['search'])){$where.=' AND (q.queue_number LIKE ? OR u.full_name LIKE ? OR st.student_number LIKE ?)';$like='%'.substr($_GET['search'],0,100).'%';array_push($params,$like,$like,$like);}
        $total=(int)Core::one('SELECT COUNT(*) AS total'.self::joins().$where,$params)['total'];
        $rows=Core::all('SELECT '.self::selectFields().self::joins().$where.' ORDER BY q.created_at DESC LIMIT '.$limit.' OFFSET '.$offset,$params);
        Core::respond(['items'=>$rows,'total'=>$total,'page'=>$page,'limit'=>$limit]);
    }
    private static function assignedCounter(array $u): array {
        if(!$u['counter_id']) throw new ApiException('No counter assigned. Contact the administrator.',409);
        $counter=Core::one('SELECT * FROM counters WHERE id=? AND is_active=1',[$u['counter_id']]);
        if(!$counter) throw new ApiException('Your assigned counter is inactive.',409);
        return $counter;
    }
    public static function next(): never {
        $u=Auth::requireRole('staff');$counter=self::assignedCounter($u);
        if(Core::setting('queue_suspended','0')==='1') throw new ApiException('Queue calling is temporarily suspended.',409);
        $db=Core::db();$db->beginTransaction();
        try {
            Core::one('SELECT id FROM counters WHERE id=? FOR UPDATE',[$counter['id']]);
            $busy=Core::one("SELECT id FROM queues WHERE counter_id=? AND status IN ('called','serving') LIMIT 1 FOR UPDATE",[$counter['id']]);
            if($busy) throw new ApiException('Finish or skip the current ticket before calling the next student.',409);
            $q=Core::one("SELECT q.id,q.status,q.queue_number,q.student_id FROM queues q JOIN counter_services cs ON cs.service_id=q.service_id WHERE cs.counter_id=? AND q.queue_date=? AND q.status='waiting' ORDER BY q.priority_level DESC,q.created_at ASC,q.id ASC LIMIT 1 FOR UPDATE",[$counter['id'],date('Y-m-d')]);
            if(!$q) throw new ApiException('There are no waiting students for this counter.',404);
            Core::q("UPDATE queues SET status='called',counter_id=?,staff_id=?,called_at=NOW() WHERE id=? AND status='waiting'",[$counter['id'],$u['staff_id'],$q['id']]);
            self::event((int)$q['id'],(int)$u['id'],'waiting','called','queue.called');
            $student=Core::one('SELECT user_id FROM students WHERE id=?',[$q['student_id']]);
            Core::notify((int)$student['user_id'],'Your queue is called','Please proceed to Counter '.$counter['counter_number'].' for ticket '.$q['queue_number'].'.');
            Core::audit((int)$u['id'],'queue.call','queue',(int)$q['id']);
            $db->commit();Core::respond(['id'=>$q['id'],'queue_number'=>$q['queue_number']]);
        } catch(Throwable $e) {$db->rollBack();throw $e;}
    }
    public static function change(int $id,string $action): never {
        $u=Auth::user();$d=Core::json();
        if($action==='cancel' && $u['role']!=='student' && $u['role']!=='admin') throw new ApiException('Not authorized.',403);
        if($action!=='cancel' && $u['role']!=='staff') throw new ApiException('Only staff can process tickets.',403);
        if((int)$u['must_change_password']) throw new ApiException('Change your temporary password first.',403);
        $db=Core::db();$db->beginTransaction();
        try {
            $q=Core::one('SELECT * FROM queues WHERE id=? FOR UPDATE',[$id]);
            if(!$q) throw new ApiException('Ticket not found.',404);
            $old=$q['status'];$new='';$sql='';$args=[];
            if($action==='cancel') {
                if($u['role']==='student' && (int)$q['student_id']!==(int)$u['student_id']) throw new ApiException('Not your ticket.',403);
                if($old!=='waiting') throw new ApiException('Only waiting tickets can be cancelled.',409);
                $new='cancelled';$sql='status=?,completed_at=NOW()';$args=[$new];
            } else {
                self::assignedCounter($u);
                if((int)$q['staff_id']!==(int)$u['staff_id'] || (int)$q['counter_id']!==(int)$u['counter_id']) throw new ApiException('Ticket is not assigned to your counter.',403);
                $transitions=['recall'=>['called','called'],'start'=>['called','serving'],'complete'=>['serving','completed']];
                if($action==='skip') {
                    if(!in_array($old,['called','serving'],true)) throw new ApiException('Ticket cannot be skipped.',409);
                    $new='skipped';$sql='status=?,completed_at=NOW(),remarks=?';$args=[$new,Core::field($d,'remarks',1000,false)];
                } else {
                    if(!isset($transitions[$action])) throw new ApiException('Unknown action.',404);
                    [$from,$new]=$transitions[$action];
                    if($old!==$from) throw new ApiException('Ticket must be '.$from.' before this action.',409);
                    if($action==='recall') {$sql='called_at=NOW()';$args=[];}
                    elseif($action==='start') {$sql='status=?,started_at=NOW()';$args=[$new];}
                    else {$sql='status=?,completed_at=NOW(),remarks=?';$args=[$new,Core::field($d,'remarks',1000,false)];}
                }
            }
            Core::q('UPDATE queues SET '.$sql.' WHERE id=?',[...$args,$id]);
            if(in_array($action,['complete','skip'],true)) {
                Core::q('INSERT INTO transactions(queue_id,staff_id,counter_id,outcome,remarks,processing_duration_minutes) VALUES(?,?,?,?,?,?)',
                  [$id,$u['staff_id'],$u['counter_id'],$new,Core::field($d,'remarks',1000,false),
                    $q['started_at']?max(0,(int)floor((time()-strtotime($q['started_at']))/60)):null]);
            }
            self::event($id,(int)$u['id'],$old,$new,'queue.'.$action);
            if(in_array($action,['cancel','complete','skip'],true)) {
                $student=Core::one('SELECT user_id FROM students WHERE id=?',[$q['student_id']]);
                Core::notify((int)$student['user_id'],'Queue update','Ticket '.$q['queue_number'].' is now '.$new.'.');
            }
            Core::audit((int)$u['id'],'queue.'.$action,'queue',$id);
            $db->commit();Core::respond(['id'=>$id,'status'=>$new],200,'Queue updated.');
        } catch(Throwable $e){$db->rollBack();throw $e;}
    }
    public static function priority(int $id): never {
        $u=Auth::requireRole('admin');$d=Core::json();
        $priority=(int)($d['priority_level']??-1);
        if(!in_array($priority,[0,1,2],true)) throw new ApiException('Priority must be 0, 1, or 2.');
        $stmt=Core::q("UPDATE queues SET priority_level=? WHERE id=? AND status='waiting'",[$priority,$id]);
        if(!$stmt->rowCount()) throw new ApiException('Only waiting tickets can have their priority changed.',409);
        Core::audit((int)$u['id'],'queue.priority','queue',$id,'level '.$priority);
        Core::respond(['priority_level'=>$priority]);
    }
    public static function reinstate(int $id): never {
        $u=Auth::requireRole('staff');$db=Core::db();$db->beginTransaction();
        try {
            $q=Core::one('SELECT * FROM queues WHERE id=? FOR UPDATE',[$id]);
            if(!$q || $q['status']!=='skipped') throw new ApiException('Only skipped tickets may be returned to the waiting queue.',409);
            if((int)$q['staff_id']!==(int)$u['staff_id']) throw new ApiException('Only the original staff member may requeue this ticket.',403);
            if($q['queue_date']!==date('Y-m-d')) throw new ApiException('Only tickets from today can be requeued.',409);
            Core::q("UPDATE queues SET status='waiting',counter_id=NULL,staff_id=NULL,called_at=NULL,started_at=NULL,completed_at=NULL,remarks=NULL,created_at=NOW() WHERE id=?",[$id]);
            Core::q('DELETE FROM transactions WHERE queue_id=?',[$id]);
            self::event($id,(int)$u['id'],'skipped','waiting','queue.reinstate');
            Core::audit((int)$u['id'],'queue.reinstate','queue',$id);
            $db->commit();Core::respond(['id'=>$id,'status'=>'waiting']);
        } catch(Throwable $e){$db->rollBack();throw $e;}
    }
    public static function publicDisplay(): never {
        $today=date('Y-m-d');
        $current=Core::all("SELECT q.queue_number,q.status,c.counter_number,c.counter_name,sv.service_name,q.called_at FROM queues q JOIN counters c ON c.id=q.counter_id JOIN services sv ON sv.id=q.service_id WHERE q.queue_date=? AND q.status IN ('called','serving') ORDER BY c.counter_number ASC",[$today]);
        $waiting=Core::all("SELECT q.queue_number,sv.service_name FROM queues q JOIN services sv ON sv.id=q.service_id WHERE q.queue_date=? AND q.status='waiting' ORDER BY q.priority_level DESC,q.created_at ASC,q.id ASC LIMIT 12",[$today]);
        $counts=Core::one("SELECT SUM(status='waiting') waiting,SUM(status='serving') serving,SUM(status='completed') completed FROM queues WHERE queue_date=?",[$today]);
        Core::respond(['current'=>$current,'waiting'=>$waiting,'counts'=>$counts,'office_name'=>Core::setting('office_name','University Registrar'),'ticker'=>Core::setting('announcement_ticker'),'server_time'=>date('c')]);
    }
    public static function staffDashboard(): never {
        $u=Auth::requireRole('staff');
        $counter=$u['counter_id']?Core::one('SELECT * FROM counters WHERE id=?',[$u['counter_id']]):null;
        $current=$counter?Core::one('SELECT '.self::selectFields().self::joins()." WHERE q.counter_id=? AND q.staff_id=? AND q.status IN ('called','serving') ORDER BY q.called_at DESC LIMIT 1",[$counter['id'],$u['staff_id']]):null;
        $waiting=$counter?Core::all('SELECT '.self::selectFields().self::joins()." WHERE q.queue_date=? AND q.status='waiting' AND q.service_id IN (SELECT service_id FROM counter_services WHERE counter_id=?) ORDER BY q.priority_level DESC,q.created_at ASC,q.id ASC LIMIT 30",[date('Y-m-d'),$counter['id']]):[];
        $stats=Core::one("SELECT COUNT(*) AS served,COALESCE(AVG(TIMESTAMPDIFF(MINUTE,started_at,completed_at)),0) AS avg_minutes FROM queues WHERE staff_id=? AND queue_date=? AND status='completed'",[$u['staff_id'],date('Y-m-d')]);
        $recent=Core::all('SELECT '.self::selectFields().self::joins().' WHERE q.staff_id=? ORDER BY q.created_at DESC LIMIT 12',[$u['staff_id']]);
        Core::respond(['counter'=>$counter,'current'=>$current,'waiting'=>$waiting,'stats'=>$stats,'recent'=>$recent]);
    }
}
