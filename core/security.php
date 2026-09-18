<?php
function brute_check(): bool {
    $k='login_attempts'; $t='login_locked_until';
    if(!empty($_SESSION[$t]) && time() < $_SESSION[$t]) return false;
    if(!empty($_SESSION[$t]) && time() >= $_SESSION[$t]){ $_SESSION[$k]=0; unset($_SESSION[$t]); }
    return true;
}
function brute_hit(){
    $_SESSION['login_attempts']=($_SESSION['login_attempts']??0)+1;
    if($_SESSION['login_attempts'] >= LOGIN_MAX_ATTEMPT) $_SESSION['login_locked_until']=time()+LOGIN_LOCK_MINUTES*60;
}
function brute_reset(){ $_SESSION['login_attempts']=0; unset($_SESSION['login_locked_until']); }
function rate_fail_msg(): string {
    $u=$_SESSION['login_locked_until']??0;
    if($u>time()){ $s=$u-time(); return "Terlalu banyak percobaan. Coba lagi dalam ".ceil($s/60)." menit."; }
    return '';
}
function sanitize_input($v){ return is_string($v)?trim($v):$v; }
function validate_required($v,$name){ if($v===''||$v===null) return "$name wajib diisi"; return null; }
