<?php
require_once __DIR__.'/config/app.php';
require_once __DIR__.'/core/session.php';
require_once __DIR__.'/core/audit.php';
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/core/helper.php';
if(!empty($_SESSION['user_id'])) audit('LOGOUT','auth',$_SESSION['user_id'],'Logout');
session_unset(); session_destroy();
header('Location: '.APP_URL.'/login.php'); exit;
