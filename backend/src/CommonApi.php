<?php
declare(strict_types=1);
final class CommonApi {
    public static function services(): never {
        Core::respond(Core::all('SELECT id,service_code,service_name,description,queue_prefix,estimated_minutes FROM services WHERE is_active=1 ORDER BY service_name'));
    }
    public static function announcements(bool $manage=false): never {
        if($manage) Auth::requireRole('admin');
        $where=$manage?'':' WHERE is_active=1';
        Core::respond(Core::all('SELECT a.*,u.full_name AS author FROM announcements a LEFT JOIN users u ON u.id=a.created_by'.$where.' ORDER BY a.created_at DESC LIMIT 100'));
    }
    public static function saveAnnouncement(?int $id): never {
        $u=Auth::requireRole('admin');$d=Core::json();
        $title=Core::field($d,'title',180);$msg=Core::field($d,'message',3000);
        $active=empty($d['is_active'])?0:1;
        if($id) Core::q('UPDATE announcements SET title=?,message=?,is_active=? WHERE id=?',[$title,$msg,$active,$id]);
        else {Core::q('INSERT INTO announcements(title,message,is_active,created_by) VALUES(?,?,?,?)',[$title,$msg,$active,$u['id']]);$id=(int)Core::db()->lastInsertId();}
        Core::audit((int)$u['id'],'announcement.save','announcement',$id);Core::respond(['id'=>$id]);
    }
    public static function deleteAnnouncement(int $id): never {
        $u=Auth::requireRole('admin');Core::q('DELETE FROM announcements WHERE id=?',[$id]);Core::audit((int)$u['id'],'announcement.delete','announcement',$id);Core::respond(['deleted'=>true]);
    }
    public static function notifications(): never {
        $u=Auth::user();Core::respond(Core::all('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100',[$u['id']]));
    }
    public static function markRead(int $id): never {
        $u=Auth::user();Core::q('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?',[$id,$u['id']]);Core::respond(['read'=>true]);
    }
}
