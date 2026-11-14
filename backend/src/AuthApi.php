<?php
declare(strict_types=1);
final class AuthApi {
    public static function login(): never {
        $data=Core::json();
        $identity=Core::field($data,'identity');
        $password=(string)($data['password']??'');
        $u=Core::one('SELECT id,password_hash,status FROM users WHERE username=? OR email=? LIMIT 1',[$identity,$identity]);
        if (!$u || !password_verify($password,$u['password_hash'])) throw new ApiException('Invalid username/email or password.',401);
        if ($u['status']!=='active') throw new ApiException('This account is inactive. Contact the registrar.',403);
        Auth::login((int)$u['id']);
        Core::audit((int)$u['id'],'login','user',(int)$u['id']);
        Core::respond(['user'=>Auth::user(),'csrf'=>Auth::csrf()]);
    }
    public static function register(): never {
        $d=Core::json();
        $studentNumber=Core::field($d,'student_number',45);
        $name=Core::field($d,'full_name',160);
        $email=Core::field($d,'email',190);
        $username=Core::field($d,'username',80);
        $program=Core::field($d,'program',150);
        $year=Core::field($d,'year_level',30);
        $password=(string)($d['password']??'');
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new ApiException('Enter a valid email address.');
        if (!preg_match('/^[A-Za-z0-9_.-]{3,80}$/',$username)) throw new ApiException('Username must have 3+ letters, digits, underscores, dots, or hyphens.');
        if (strlen($password)<10) throw new ApiException('Password must contain at least 10 characters.');
        if (Core::one('SELECT id FROM users WHERE username=? OR email=?',[$username,$email]) || Core::one('SELECT id FROM students WHERE student_number=?',[$studentNumber])) throw new ApiException('Username, email, or student number already exists.',409);
        $db=Core::db(); $db->beginTransaction();
        try {
            Core::q("INSERT INTO users(username,email,password_hash,full_name,role) VALUES(?,?,?,?,'student')",[$username,$email,password_hash($password,PASSWORD_DEFAULT),$name]);
            $uid=(int)$db->lastInsertId();
            Core::q('INSERT INTO students(user_id,student_number,program,year_level,verification_status) VALUES(?,?,?,?,?)',[
                $uid,$studentNumber,$program,$year,Core::setting('allow_unverified_students','1')==='1'?'verified':'pending']);
            Core::audit($uid,'student.register','student',(int)$db->lastInsertId());
            $db->commit();
        } catch(Throwable $e) { $db->rollBack();if($e instanceof PDOException && $e->getCode()==='23000') throw new ApiException('Account already exists.',409);throw $e; }
        Auth::login($uid);
        Core::respond(['user'=>Auth::user(),'csrf'=>Auth::csrf()],201,'Account created successfully.');
    }
    public static function changePassword(): never {
        $u=Auth::user();$d=Core::json();
        $old=(string)($d['current_password']??'');$new=(string)($d['new_password']??'');
        $hash=Core::one('SELECT password_hash FROM users WHERE id=?',[$u['id']])['password_hash'];
        if (!password_verify($old,$hash)) throw new ApiException('Current password is incorrect.',422);
        if(strlen($new)<10) throw new ApiException('New password must contain at least 10 characters.');
        if(hash_equals($old,$new)) throw new ApiException('Choose a different password.');
        Core::q('UPDATE users SET password_hash=?,must_change_password=0 WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$u['id']]);
        Core::audit((int)$u['id'],'password.change','user',(int)$u['id']);
        Core::respond(['changed'=>true],200,'Password changed successfully.');
    }
    public static function resetRequest(): never {
        $d=Core::json();$identity=Core::field($d,'identity');
        $u=Core::one('SELECT id FROM users WHERE (username=? OR email=?) AND status="active"',[$identity,$identity]);
        if ($u) {
            $existing=Core::one("SELECT id FROM password_reset_requests WHERE user_id=? AND status='pending'",[$u['id']]);
            if (!$existing) Core::q('INSERT INTO password_reset_requests(user_id) VALUES(?)',[$u['id']]);
        }
        Core::respond(['received'=>true],200,'If the account exists, the registrar will review your reset request.');
    }
    public static function profile(): never {
        $u=Auth::user();$d=Core::json();
        $name=Core::field($d,'full_name',160);$email=Core::field($d,'email',190);
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new ApiException('Enter a valid email address.');
        $db=Core::db();$db->beginTransaction();
        try {
            Core::q('UPDATE users SET full_name=?,email=? WHERE id=?',[$name,$email,$u['id']]);
            if($u['role']==='student') {
                Core::q('UPDATE students SET program=?,year_level=? WHERE id=?',[Core::field($d,'program',150),Core::field($d,'year_level',30),$u['student_id']]);
            }
            $db->commit();
        } catch (Throwable $e) { $db->rollBack();if($e instanceof PDOException && $e->getCode()==='23000') throw new ApiException('Email is already in use.',409);throw $e; }
        Core::respond(['saved'=>true],200,'Profile saved.');
    }
}
