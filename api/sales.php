<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/helper.php';
require_once __DIR__.'/../core/csrf.php';
require_once __DIR__.'/../core/audit.php';
require_login();
if(!has_permission('sales.create')){ json_fail('Forbidden',[],403); }
if($_SERVER['REQUEST_METHOD']!=='POST'){ json_fail('Method not allowed',[],405); }
csrf_check_or_fail();
$input=json_decode(file_get_contents('php://input'), true);
if(!$input) json_fail('Payload invalid');
$items=$input['items']??[];
$customer_id = !empty($input['customer_id']) ? (int)$input['customer_id'] : null;
$disc_type = $input['discount_type']??'none'; if(!in_array($disc_type,['none','nominal','percent'])) $disc_type='none';
$disc_val = (float)($input['discount_value']??0);
$tax_percent = (float)($input['tax_percent']??0);
$add_cost = (int)($input['additional_cost']??0);
$paid = (int)($input['paid_amount']??0);
$pay_method = trim($input['payment_method']??'tunai');
if(empty($items)) json_fail('Keranjang kosong');
$pdo=db();
try{
  $pdo->beginTransaction();
  $subtotal=0;
  $line=[];
  foreach($items as $it){
    $pid=(int)($it['product_id']??0); $qty=(int)($it['qty']??0);
    $d_type=$it['discount_type']??'none'; $d_val=(float)($it['discount_value']??0);
    if($pid<=0||$qty<=0) throw new Exception('Item tidak valid');
    $stmt=$pdo->prepare("SELECT * FROM products WHERE id=? FOR UPDATE"); $stmt->execute([$pid]); $p=$stmt->fetch();
    if(!$p || !$p['is_active']) throw new Exception('Produk tidak ditemukan / nonaktif: '.$pid);
    if(!ALLOW_NEGATIVE_STOCK && $p['stock'] < $qty) throw new Exception('Stok tidak mencukupi: '.$p['name'].' (tersedia '.$p['stock'].')');
    $price=(int)$p['selling_price'];
    $line_disc=0;
    if($d_type==='percent'){ $line_disc=(int)round($price*$qty*$d_val/100); }
    elseif($d_type==='nominal'){ $line_disc=(int)$d_val; if($line_disc > $price*$qty) $line_disc=$price*$qty; }
    $line_sub=$price*$qty - $line_disc;
    $subtotal+=$line_sub;
    $line[]=['p'=>$p,'qty'=>$qty,'price'=>$price,'d_type'=>$d_type,'d_val'=>$d_val,'d_amt'=>$line_disc,'sub'=>$line_sub];
  }
  $trx_disc=0;
  if($disc_type==='percent'){ $trx_disc=(int)round($subtotal*$disc_val/100); }
  elseif($disc_type==='nominal'){ $trx_disc=(int)$disc_val; if($trx_disc>$subtotal) $trx_disc=$subtotal; }
  $after_disc=$subtotal - $trx_disc;
  $tax_amt=(int)round($after_disc * $tax_percent / 100);
  $grand=$after_disc + $tax_amt + $add_cost;
  if($paid < $grand && $pay_method==='tunai'){ throw new Exception('Pembayaran kurang. Total '.rupiah($grand).', dibayar '.rupiah($paid)); }
  if($paid < $grand && !in_array($pay_method,['tunai','transfer','qris','debit','kredit','ewallet'])) throw new Exception('Metode pembayaran tidak valid');
  if($paid < $grand){ $paid=$grand; }
  $change=$paid - $grand;
  $trx=trx_number($pdo);
  $stmt=$pdo->prepare("INSERT INTO sales (transaction_number,customer_id,cashier_id,subtotal,discount_type,discount_value,discount_amount,tax_percent,tax_amount,additional_cost,grand_total,paid_amount,change_amount,payment_method,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'completed')");
  $stmt->execute([$trx,$customer_id, current_user()['id'], $subtotal,$disc_type,$disc_val,$trx_disc,$tax_percent,$tax_amt,$add_cost,$grand,$paid,$change,$pay_method]);
  $sale_id=(int)$pdo->lastInsertId();
  foreach($line as $l){
    $pdo->prepare("INSERT INTO sale_items (sale_id,product_id,product_name,sku,barcode,qty,price,discount_type,discount_value,discount_amount,subtotal) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
    ->execute([$sale_id,$l['p']['id'],$l['p']['name'],$l['p']['sku'],$l['p']['barcode'],$l['qty'],$l['price'],$l['d_type'],$l['d_val'],$l['d_amt'],$l['sub']]);
    $newStock=$l['p']['stock'] - $l['qty'];
    $pdo->prepare("UPDATE products SET stock=? WHERE id=?")->execute([$newStock,$l['p']['id']]);
    $pdo->prepare("INSERT INTO stock_movements (product_id,type,reference_type,reference_id,qty_change,stock_before,stock_after,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?)")
    ->execute([$l['p']['id'],'SALE','sale',$sale_id,-$l['qty'],$l['p']['stock'],$newStock,'Penjualan '.$trx, current_user()['id']]);
  }
  $pdo->prepare("INSERT INTO sale_payments (sale_id,method,amount) VALUES (?,?,?)")->execute([$sale_id,$pay_method,$paid]);
  if($pay_method==='tunai'){
    $pdo->prepare("INSERT INTO cash_transactions (type,category,amount,description,reference_type,reference_id,created_by) VALUES ('in','penjualan',?,?, 'sale', ?, ?)")->execute([$grand,"Penjualan $trx",$sale_id, current_user()['id']]);
  }
  $pdo->commit();
  audit('CREATE_SALE','sales',$sale_id,"Penjualan $trx total ".rupiah($grand));
  json_ok('Transaksi berhasil',['transaction_number'=>$trx,'sale_id'=>$sale_id,'grand_total'=>$grand,'change'=>$change]);
}catch(Exception $e){
  if($pdo->inTransaction()) $pdo->rollBack();
  json_fail($e->getMessage(),[],400);
}
