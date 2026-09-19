<?php
date_default_timezone_set('Asia/Jakarta');

if(file_exists(__DIR__.'/local.php')) require __DIR__.'/local.php';

if(!defined('PRETTY_URL')){
    $__pretty = false;
    if(function_exists('apache_get_modules')) $__pretty = in_array('mod_rewrite', apache_get_modules(), true);
    if(getenv('PRETTY_URL') !== false) $__pretty = (bool)getenv('PRETTY_URL');
    define('PRETTY_URL', $__pretty);
    unset($__pretty);
}
if(!defined('APP_NAME')) define('APP_NAME','POS Profesional');
if(!defined('APP_DEBUG')) define('APP_DEBUG', false);
if(!defined('APP_URL')) define('APP_URL','http://localhost/POS');
if(!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 3600);
if(!defined('LOGIN_MAX_ATTEMPT')) define('LOGIN_MAX_ATTEMPT', 5);
if(!defined('LOGIN_LOCK_MINUTES')) define('LOGIN_LOCK_MINUTES', 2);
if(!defined('CURRENCY')) define('CURRENCY','Rp');
if(!defined('DEFAULT_TAX')) define('DEFAULT_TAX', 0);
if(!defined('ALLOW_NEGATIVE_STOCK')) define('ALLOW_NEGATIVE_STOCK', false);
if(!defined('UPLOAD_MAX_MB')) define('UPLOAD_MAX_MB', 2);
if(!defined('ITEMS_PER_PAGE')) define('ITEMS_PER_PAGE', 15);
if(!defined('ALLOWED_UPDATE_ROLES')) define('ALLOWED_UPDATE_ROLES', ['admin','owner']);
if(!defined('UPDATE_GIT_REMOTE')) define('UPDATE_GIT_REMOTE', 'origin');
if(!defined('UPDATE_GIT_URL')) define('UPDATE_GIT_URL', 'https://github.com/dewecorp/POS.git');
