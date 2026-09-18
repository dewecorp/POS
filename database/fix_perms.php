<?php
require __DIR__.'/../config/database.php';
$pdo=db();
$kasirId=$pdo->query("SELECT id FROM users WHERE username='kasir'")->fetchColumn();
$perms=$pdo->query("SELECT id,code FROM permissions WHERE code IN ('sales.create','sales.view','sales.print','products.view','inventory.view')")->fetchAll();
foreach($perms as $p){
  try{ $pdo->prepare("INSERT IGNORE INTO user_permissions (user_id,permission_id) VALUES (?,?)")->execute([$kasirId,$p['id']]); }catch(Exception $e){}
  try{ $pdo->prepare("INSERT INTO user_permissions (user_id,permission_id) VALUES (?,?)")->execute([$kasirId,$p['id']]); }catch(Exception $e){}
}
echo "kasir perms: ".$pdo->query("SELECT COUNT(*) FROM user_permissions WHERE user_id=$kasirId")->fetchColumn()."\n";
foreach($pdo->query("SELECT p.code FROM permissions p JOIN user_permissions up ON up.permission_id=p.id WHERE up.user_id=$kasirId") as $r) echo $r['code']." ";
