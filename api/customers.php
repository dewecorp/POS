<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/helper.php';
require_login();
header('Content-Type: application/json');
$pdo=db();
$q=trim($_GET['q']??'');
if($q===''){ echo json_encode(['success'=>true,'data'=>[]]); exit; }
$s=$pdo->prepare("SELECT id,code,name,phone,type FROM customers WHERE is_active=1 AND (name LIKE ? OR phone LIKE ? OR code LIKE ?) LIMIT 10");
$like="%$q%"; $s->execute([$like,$like,$like]);
echo json_encode(['success'=>true,'data'=>$s->fetchAll()]);
