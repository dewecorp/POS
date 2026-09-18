<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/helper.php';
function current_user(){ return $_SESSION['user'] ?? null; }
function is_logged_in(): bool { return !empty($_SESSION['user_id']); }
function require_login(){
    if(!is_logged_in()){ header('Location: '.url('/login')); exit; }
}
function require_role($roles){
    require_login();
    $r = $_SESSION['user']['role'] ?? '';
    $allow = is_array($roles) ? $roles : [$roles];
    if(!in_array($r,$allow,true)){
        http_response_code(403); die('Forbidden: akses ditolak');
    }
}
function has_permission(string $perm): bool {
    $role = $_SESSION['user']['role'] ?? '';
    if(in_array($role,['admin','owner','manager'],true)) return true;
    $perms = $_SESSION['user']['permissions'] ?? [];
    return in_array($perm,$perms,true) || in_array('*',$perms,true);
}
function require_permission(string $perm){
    require_login();
    if(!has_permission($perm)){ http_response_code(403); die('Forbidden: permission '.$perm.' diperlukan'); }
}
function login_user(array $user){
    session_regenerate_id(true);
    $_SESSION['user_id']=$user['id'];
    $_SESSION['user']=$user;
    $_SESSION['last_activity']=time();
    $_SESSION['login_attempts']=0;
}
function logout_user(){ session_unset(); session_destroy(); }
function user_permissions(PDO $pdo,int $userId): array {
    $s=$pdo->prepare("SELECT p.code FROM permissions p JOIN user_permissions up ON up.permission_id=p.id WHERE up.user_id=?");
    $s->execute([$userId]); return array_column($s->fetchAll(),'code');
}
