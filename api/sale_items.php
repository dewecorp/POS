<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../core/auth.php';
require_login();
header('Content-Type: application/json');
$pdo=db();
$id=(int)($_GET['sale_id']??0);
$s=$pdo->prepare("SELECT * FROM sale_items WHERE sale_id=?"); $s->execute([$id]);
echo json_encode(['success'=>true,'data'=>$s->fetchAll()]);
