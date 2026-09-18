<?php
date_default_timezone_set('Asia/Jakarta');

// Deteksi dukungan URL rewriting (clean URL). Fallback ke .php bila tidak tersedia.
$__pretty = false;
if (function_exists('apache_get_modules')) {
    $__pretty = in_array('mod_rewrite', apache_get_modules(), true);
}
if (getenv('PRETTY_URL') !== false) {
    $__pretty = (bool)getenv('PRETTY_URL');
}
define('PRETTY_URL', $__pretty);
unset($__pretty);

define('APP_NAME','POS Profesional');
define('APP_DEBUG', false);
define('APP_URL','http://localhost/POS');
define('SESSION_TIMEOUT', 3600);
define('LOGIN_MAX_ATTEMPT', 5);
define('LOGIN_LOCK_MINUTES', 2);
define('CURRENCY','Rp');
define('DEFAULT_TAX', 0);
define('ALLOW_NEGATIVE_STOCK', false);
define('UPLOAD_MAX_MB', 2);
define('ITEMS_PER_PAGE', 15);
