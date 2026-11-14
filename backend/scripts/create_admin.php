<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
require __DIR__.'/../src/Core.php';
Core::loadEnv(__DIR__.'/../.env');
date_default_timezone_set(Core::env('APP_TIMEZONE','Asia/Manila'));
function ask(string $label):string {echo $label;return trim((string)fgets(STDIN));}
try {
    echo "\nRegistrar System - Administrator Setup\n";
    $name=ask('Full name: ');$username=ask('Username: ');$email=ask('Email: ');
    $password=ask('Secure password (at least 12 characters; input is visible): ');
    if(!$name||!preg_match('/^[A-Za-z0-9_.-]{3,80}$/',$username)||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12) throw new RuntimeException('Invalid details, or password is shorter than 12 characters.');
    $exists=Core::one('SELECT id FROM users WHERE username=? OR email=?',[$username,$email]);
    if($exists) throw new RuntimeException('Username or email already exists. Choose a new one.');
    Core::q("INSERT INTO users(username,email,password_hash,full_name,role) VALUES(?,?,?,?,'admin')",[$username,$email,password_hash($password,PASSWORD_DEFAULT),$name]);
    echo "\nAdministrator created. You may now log in.\n";
} catch(Throwable $e){fwrite(STDERR,'Setup failed: '.$e->getMessage()."\n");exit(1);}
