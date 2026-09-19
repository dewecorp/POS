<?php
if(file_exists(__DIR__.'/local.php')) require __DIR__.'/local.php';
if(!isset($DB_HOST)) $DB_HOST = '127.0.0.1';
if(!isset($DB_NAME)) $DB_NAME = 'pos_db';
if(!isset($DB_USER)) $DB_USER = 'root';
if(!isset($DB_PASS)) $DB_PASS = '';
if(!isset($DB_CHARSET)) $DB_CHARSET = 'utf8mb4';
if(!isset($DB_PORT)) $DB_PORT = 3306;

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        global $DB_HOST,$DB_NAME,$DB_USER,$DB_PASS,$DB_CHARSET,$DB_PORT;
        $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset={$DB_CHARSET}";
        $opt = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $opt);
        $pdo->exec("SET time_zone = '+07:00'");
    }
    return $pdo;
}
