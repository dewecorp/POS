<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/helper.php';
require_login();
$pdo=db();
if(isset($_GET['export']) && $_GET['export']==='csv'){
  if(!has_permission('products.view')){ http_response_code(403); exit('Forbidden'); }
  header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="produk.csv"');
  $out=fopen('php://output','w'); fputcsv($out,['SKU','Barcode','Nama','Kategori','Beli','Jual','Stok']);
  foreach($pdo->query("SELECT p.sku,p.barcode,p.name,c.name as cat,p.purchase_price,p.selling_price,p.stock FROM products p LEFT JOIN product_categories c ON c.id=p.category_id") as $r) fputcsv($out,[$r['sku'],$r['barcode'],$r['name'],$r['cat'],$r['purchase_price'],$r['selling_price'],$r['stock']]);
  exit;
}
header('Content-Type: application/json');
$q=trim($_GET['q']??''); $barcode=trim($_GET['barcode']??''); $id=(int)($_GET['id']??0);
if($id>0){
  $s=$pdo->prepare("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN product_categories c ON c.id=p.category_id WHERE p.id=? LIMIT 1");
  $s->execute([$id]); $r=$s->fetch();
  if(!$r) echo json_encode(['success'=>false,'message'=>'Produk tidak ditemukan']); else echo json_encode(['success'=>true,'data'=>$r]);
  exit;
}
if($barcode!==''){
  $s=$pdo->prepare("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN product_categories c ON c.id=p.category_id WHERE p.barcode=? OR p.sku=? LIMIT 1");
  $s->execute([$barcode,$barcode]); $r=$s->fetch();
  if(!$r) echo json_encode(['success'=>false,'message'=>'Produk tidak ditemukan']); else echo json_encode(['success'=>true,'data'=>$r]);
  exit;
}
if($q===''){ echo json_encode(['success'=>true,'data'=>[]]); exit; }
$s=$pdo->prepare("SELECT p.id,p.sku,p.barcode,p.name,p.selling_price,p.wholesale_price,p.stock, c.name as cat FROM products p LEFT JOIN product_categories c ON c.id=p.category_id WHERE p.is_active=1 AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?) LIMIT 20");
$like="%$q%"; $s->execute([$like,$like,$like]); echo json_encode(['success'=>true,'data'=>$s->fetchAll()]);
