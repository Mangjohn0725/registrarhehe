<?php
declare(strict_types=1);
require __DIR__.'/../src/Core.php';
Core::loadEnv(__DIR__.'/../.env');
date_default_timezone_set(Core::env('APP_TIMEZONE','Asia/Manila'));
require __DIR__.'/../src/AuthApi.php';
require __DIR__.'/../src/QueueApi.php';
require __DIR__.'/../src/AdminApi.php';
require __DIR__.'/../src/CommonApi.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, private');
Auth::init();
set_exception_handler(function(Throwable $e):void {
    if($e instanceof ApiException) Core::fail($e->getMessage(),$e->status);
    error_log('[Registrar] '.$e->getMessage());
    Core::fail(Core::env('APP_DEBUG')==='true'?$e->getMessage():'An unexpected server error occurred.',500);
});

$method=$_SERVER['REQUEST_METHOD']??'GET';
$uri=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH) ?: '/';
$pos=strpos($uri,'/api/');
$path=$pos===false?($uri==='/api'?'/api':$uri):substr($uri,$pos);
if($method==='OPTIONS'){http_response_code(204);exit;}
if(!in_array($method,['GET','HEAD'],true)) Auth::checkCsrf();

// Routing intentionally keeps all permission enforcement on the server.
$routes=[
 ['GET','#^/api/health$#',fn()=>Core::respond(['status'=>'ok','timezone'=>date_default_timezone_get()])],
 ['GET','#^/api/auth/csrf$#',fn()=>Core::respond(['csrf'=>Auth::csrf()])],
 ['GET','#^/api/auth/me$#',fn()=>Core::respond(['user'=>Auth::user(false)])],
 ['POST','#^/api/auth/login$#',fn()=>AuthApi::login()],
 ['POST','#^/api/auth/register-student$#',fn()=>AuthApi::register()],
 ['POST','#^/api/auth/password-reset-request$#',fn()=>AuthApi::resetRequest()],
 ['POST','#^/api/auth/logout$#',function(){Auth::logout();Core::respond(['logged_out'=>true]);}],
 ['POST','#^/api/auth/change-password$#',fn()=>AuthApi::changePassword()],
 ['PUT','#^/api/profile$#',fn()=>AuthApi::profile()],
 ['GET','#^/api/services$#',fn()=>CommonApi::services()],
 ['GET','#^/api/display/current$#',fn()=>QueueApi::publicDisplay()],
 ['GET','#^/api/announcements$#',fn()=>CommonApi::announcements()],
 ['GET','#^/api/announcements/manage$#',fn()=>CommonApi::announcements(true)],
 ['POST','#^/api/announcements$#',fn()=>CommonApi::saveAnnouncement(null)],
 ['PUT','#^/api/announcements/(?P<id>\d+)$#',fn($m)=>CommonApi::saveAnnouncement((int)$m['id'])],
 ['DELETE','#^/api/announcements/(?P<id>\d+)$#',fn($m)=>CommonApi::deleteAnnouncement((int)$m['id'])],
 ['GET','#^/api/notifications$#',fn()=>CommonApi::notifications()],
 ['PATCH','#^/api/notifications/(?P<id>\d+)/read$#',fn($m)=>CommonApi::markRead((int)$m['id'])],
 ['GET','#^/api/dashboard/admin$#',fn()=>AdminApi::dashboard()],
 ['GET','#^/api/dashboard/staff$#',fn()=>QueueApi::staffDashboard()],
 ['GET','#^/api/staff$#',fn()=>AdminApi::staff()],
 ['POST','#^/api/staff$#',fn()=>AdminApi::createStaff()],
 ['PUT','#^/api/staff/(?P<id>\d+)$#',fn($m)=>AdminApi::updateStaff((int)$m['id'])],
 ['PATCH','#^/api/staff/(?P<id>\d+)/status$#',fn($m)=>AdminApi::statusStaff((int)$m['id'])],
 ['POST','#^/api/staff/(?P<id>\d+)/reset-password$#',fn($m)=>AdminApi::resetUserPassword('staff',(int)$m['id'])],
 ['GET','#^/api/students$#',fn()=>AdminApi::students()],
 ['PATCH','#^/api/students/(?P<id>\d+)/verification$#',fn($m)=>AdminApi::verifyStudent((int)$m['id'])],
 ['POST','#^/api/students/(?P<id>\d+)/reset-password$#',fn($m)=>AdminApi::resetUserPassword('student',(int)$m['id'])],
 ['GET','#^/api/password-reset-requests$#',fn()=>AdminApi::resetRequests()],
 ['GET','#^/api/admin/services$#',fn()=>AdminApi::services()],
 ['POST','#^/api/admin/services$#',fn()=>AdminApi::serviceSave(null)],
 ['PUT','#^/api/admin/services/(?P<id>\d+)$#',fn($m)=>AdminApi::serviceSave((int)$m['id'])],
 ['DELETE','#^/api/admin/services/(?P<id>\d+)$#',fn($m)=>AdminApi::serviceDisable((int)$m['id'])],
 ['GET','#^/api/counters$#',fn()=>AdminApi::counters()],
 ['POST','#^/api/counters$#',fn()=>AdminApi::counterSave(null)],
 ['PUT','#^/api/counters/(?P<id>\d+)$#',fn($m)=>AdminApi::counterSave((int)$m['id'])],
 ['DELETE','#^/api/counters/(?P<id>\d+)$#',fn($m)=>AdminApi::counterDisable((int)$m['id'])],
 ['POST','#^/api/queues$#',fn()=>QueueApi::ticket()],
 ['GET','#^/api/queues/my-active$#',fn()=>QueueApi::myActive()],
 ['GET','#^/api/queues/my-history$#',fn()=>QueueApi::myHistory()],
 ['GET','#^/api/queues$#',fn()=>QueueApi::list()],
 ['POST','#^/api/queues/next$#',fn()=>QueueApi::next()],
 ['PATCH','#^/api/queues/(?P<id>\d+)/priority$#',fn($m)=>QueueApi::priority((int)$m['id'])],
 ['POST','#^/api/queues/(?P<id>\d+)/reinstate$#',fn($m)=>QueueApi::reinstate((int)$m['id'])],
 ['POST','#^/api/queues/(?P<id>\d+)/(recall|start|complete|skip|cancel)$#',fn($m)=>QueueApi::change((int)$m['id'],$m[2])],
 ['GET','#^/api/reports/overview$#',fn()=>AdminApi::reports()],
 ['GET','#^/api/reports/export$#',fn()=>AdminApi::export()],
 ['GET','#^/api/audit-logs$#',fn()=>AdminApi::audit()],
 ['GET','#^/api/settings$#',fn()=>AdminApi::settings()],
 ['PUT','#^/api/settings$#',fn()=>AdminApi::settingsSave()],
];
foreach($routes as [$m,$regex,$handler]){
    if($method!==$m || !preg_match($regex,$path,$match)) continue;
    $handler($match);
    exit;
}
Core::fail('API route not found.',404);
