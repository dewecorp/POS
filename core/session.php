<?php
ini_set('session.cookie_httponly',1);
ini_set('session.use_only_cookies',1);
if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ini_set('session.cookie_secure',1);
session_start();
if(empty($_SESSION['__init'])){
    session_regenerate_id(true);
    $_SESSION['__init']=true;
}
if(isset($_SESSION['user_id'])){
    $t = $_SESSION['last_activity'] ?? time();
    if(time()-$t > SESSION_TIMEOUT){
        session_unset(); session_destroy(); session_start();
        header('Location: '.APP_URL.'/login.php?timeout=1'); exit;
    }
    $_SESSION['last_activity']=time();
}
function flash_set($k,$v){ $_SESSION['_flash'][$k]=$v; }
function flash_get($k){ $v=$_SESSION['_flash'][$k]??null; unset($_SESSION['_flash'][$k]); return $v; }
