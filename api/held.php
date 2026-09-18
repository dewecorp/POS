<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/helper.php';
require_once __DIR__.'/../core/csrf.php';
require_login();
header('Content-Type: application/json');
$pdo=db();
$method=$_SERVER['REQUEST_METHOD'];
if($method==='GET'){
  if(isset($_GET['id'])){
    $id=(int)$_GET['id'];
    $stmt=$pdo->prepare("SELECT * FROM held_transactions WHERE id=? AND cashier_id=?");
    $stmt->execute([$id, current_user()['id']]); $row=$stmt->fetch();
    if(!$row) json_fail('Tidak ditemukan',[],404);
    echo json_encode(['success'=>true,'data'=>json_decode($row['data'],true),'code'=>$row['code'],'id'=>$row['id']]); exit;
  }
  $stmt=$pdo->prepare("SELECT id,code,created_at FROM held_transactions WHERE cashier_id=? ORDER BY id DESC LIMIT 20");
  $stmt->execute([current_user()['id']]); echo json_encode(['success'=>true,'data'=>$stmt->fetchAll()]); exit;
}
if($method==='POST'){
  csrf_check_or_fail();
  $data=json_decode(file_get_contents('php://input'), true);
  if(empty($data['items'])) json_fail('Keranjang kosong');
  $code='PARK-'.date('YmdHis').'-'.bin2hex(random_bytes(2));
  $pdo->prepare("INSERT INTO held_transactions (code,cashier_id,data) VALUES (?,?,?)")->execute([$code, current_user()['id'], json_encode($data,JSON_UNESCAPED_UNICODE)]);
  json_ok('Transaksi diparkir',['code'=>$code]);
}
if($method==='DELETE'){
  csrf_check_or_fail();
  $id=(int)($_GET['id']??0);
  $pdo->prepare("DELETE FROM held_transactions WHERE id=? AND cashier_id=?")->execute([$id, current_user()['id']]);
  json_ok('Dihapus');
}
json_fail('Method not allowed',[],405);
