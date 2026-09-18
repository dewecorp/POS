<?php
require_once __DIR__.'/config/app.php';
require_once __DIR__.'/core/session.php';
require_once __DIR__.'/core/auth.php';
if(!is_logged_in()) redirect(url('/login'));
$r=$_SESSION['user']['role']??'kasir';
if($r==='admin'||$r==='manager'||$r==='owner') redirect(url('/admin'));
redirect(url('/kasir'));
