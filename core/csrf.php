<?php
function csrf_token(): string {
    if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="'.e(csrf_token()).'">'; }
function csrf_verify($token): bool { return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token); }
function csrf_check_or_fail(){
    $t = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if(!csrf_verify($t)) json_fail('CSRF token tidak valid',[],419);
}
function csrf_check_form(){
    $t = $_POST['_csrf'] ?? '';
    if(!csrf_verify($t)){ flash_set('error','CSRF token tidak valid'); redirect($_SERVER['HTTP_REFERER'] ?? 'login.php'); }
}
