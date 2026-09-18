<?php
require_once __DIR__.'/config/app.php';
require_once __DIR__.'/core/session.php';
require_once __DIR__.'/core/auth.php';
if(!is_logged_in()) redirect(APP_URL.'/login.php');
$r=$_SESSION['user']['role']??'kasir';
if($r==='admin'||$r==='manager'||$r==='owner') redirect(APP_URL.'/admin/index.php');
redirect(APP_URL.'/kasir/index.php');
