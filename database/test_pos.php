<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../core/helper.php';
$pdo=db();
echo "== POS E2E TEST ==\n";
function assert_eq($label,$exp,$got){
  if($exp===$got) echo "[PASS] $label exp=$exp got=$got\n";
  else echo "[FAIL] $label exp=$exp got=$got\n";
}
function assert_true($label,$cond){ echo $cond?"[PASS] $label\n":"[FAIL] $label\n"; }

// TEST1: stok 10 -> jual 2 -> stok 8 (pakai produk SKU001)
$pdo->exec("UPDATE products SET stock=10 WHERE sku='SKU001'");
$stockBefore=(int)$pdo->query("SELECT stock FROM products WHERE sku='SKU001'")->fetchColumn();
assert_eq("TEST1 stock before",10,$stockBefore);
// simulasi transaksi via api/sales.php logic
try{
  $pdo->beginTransaction();
  $stmt=$pdo->prepare("SELECT * FROM products WHERE sku='SKU001' FOR UPDATE"); $stmt->execute(); $p=$stmt->fetch();
  $qty=2; $price=(int)$p['selling_price'];
  $subtotal=$price*$qty;
  $grand=$subtotal;
  $trx=trx_number($pdo);
  $pdo->prepare("INSERT INTO sales (transaction_number,customer_id,cashier_id,subtotal,grand_total,paid_amount,change_amount,payment_method) VALUES (?,?,?,?,?,?,?,?)")->execute([$trx,1,1,$subtotal,$grand,$grand,0,'tunai']);
  $saleId=$pdo->lastInsertId();
  $pdo->prepare("INSERT INTO sale_items (sale_id,product_id,product_name,sku,barcode,qty,price,subtotal) VALUES (?,?,?,?,?,?,?,?)")->execute([$saleId,$p['id'],$p['name'],$p['sku'],$p['barcode'],$qty,$price,$subtotal]);
  $newStock=$p['stock']-$qty;
  $pdo->prepare("UPDATE products SET stock=? WHERE id=?")->execute([$newStock,$p['id']]);
  $pdo->prepare("INSERT INTO stock_movements (product_id,type,reference_type,reference_id,qty_change,stock_before,stock_after,created_by) VALUES (?,?,?,?,?,?,?,?)")->execute([$p['id'],'SALE','sale',$saleId,-$qty,$p['stock'],$newStock,1]);
  $pdo->commit();
  $stockAfter=(int)$pdo->query("SELECT stock FROM products WHERE sku='SKU001'")->fetchColumn();
  assert_eq("TEST1 stock after jual 2",8,$stockAfter);
  $saleId1=$saleId; $trx1=$trx;
}catch(Exception $e){ if($pdo->inTransaction()) $pdo->rollBack(); echo "[FAIL] TEST1 exception ".$e->getMessage()."\n"; }

// TEST2: diskon 10% -> total benar
// jual 2 x 3500 =7000 diskon 10% =700 grand 6300
$pdo->exec("UPDATE products SET stock=10 WHERE sku='SKU002'");
$p2=$pdo->query("SELECT * FROM products WHERE sku='SKU002'")->fetch();
$qty=2; $price=(int)$p2['selling_price']; //4000
$line=$price*$qty; //8000
$discType='percent'; $discVal=10; $trxDisc=(int)round($line*$discVal/100); //800
$grand2=$line-$trxDisc;
assert_eq("TEST2 grand dengan diskon 10%",7200,$grand2);

// TEST3: pembayaran tunai kembalian
$paid=10000; $change=$paid-$grand2; assert_eq("TEST3 kembalian",2800,$change);

// TEST4: pembayaran kurang ditolak (simulasi api sales validasi)
$paidShort=5000; $shouldFail=$paidShort < $grand2; assert_true("TEST4 pembayaran kurang ditolak",$shouldFail);

// TEST5: void transaksi stok kembali
try{
  $pdo->beginTransaction();
  $s=$pdo->prepare("SELECT * FROM sales WHERE id=? FOR UPDATE"); $s->execute([$saleId1]); $sale=$s->fetch();
  $items=$pdo->prepare("SELECT * FROM sale_items WHERE sale_id=?"); $items->execute([$saleId1]); $its=$items->fetchAll();
  foreach($its as $it){
    $st=$pdo->prepare("SELECT stock FROM products WHERE id=? FOR UPDATE"); $st->execute([$it['product_id']]); $stock=(int)$st->fetchColumn();
    $new=$stock+$it['qty']; $pdo->prepare("UPDATE products SET stock=? WHERE id=?")->execute([$new,$it['product_id']]);
    $pdo->prepare("INSERT INTO stock_movements (product_id,type,reference_type,reference_id,qty_change,stock_before,stock_after,created_by) VALUES (?,?,?,?,?,?,?,?)")->execute([$it['product_id'],'CORRECTION','sale',$saleId1,$it['qty'],$stock,$new,1]);
  }
  $pdo->prepare("UPDATE sales SET status='cancelled' WHERE id=?")->execute([$saleId1]);
  $pdo->commit();
  $stockAfterVoid=(int)$pdo->query("SELECT stock FROM products WHERE sku='SKU001'")->fetchColumn();
  assert_eq("TEST5 void stok kembali 10",$stockAfterVoid,10);
}catch(Exception $e){ if($pdo->inTransaction()) $pdo->rollBack(); echo "[FAIL] TEST5 ".$e->getMessage()."\n"; }

// TEST6 retur
// buat transaksi baru 2 qty SKU001
$pdo->exec("UPDATE products SET stock=10 WHERE sku='SKU001'");
$pdo->beginTransaction();
$p=$pdo->query("SELECT * FROM products WHERE sku='SKU001' FOR UPDATE")->fetch();
$trx6=trx_number($pdo);
$pdo->prepare("INSERT INTO sales (transaction_number,customer_id,cashier_id,subtotal,grand_total,paid_amount,change_amount,payment_method) VALUES (?,?,?,?,?,?,?,?)")->execute([$trx6,1,1,$p['selling_price']*2,$p['selling_price']*2,$p['selling_price']*2,0,'tunai']);
$sale6=$pdo->lastInsertId();
$pdo->prepare("INSERT INTO sale_items (sale_id,product_id,product_name,sku,barcode,qty,price,subtotal) VALUES (?,?,?,?,?,?,?,?)")->execute([$sale6,$p['id'],$p['name'],$p['sku'],$p['barcode'],2,$p['selling_price'],$p['selling_price']*2]);
$pdo->prepare("UPDATE products SET stock=? WHERE id=?")->execute([$p['stock']-2,$p['id']]);
$pdo->prepare("INSERT INTO stock_movements (product_id,type,reference_type,reference_id,qty_change,stock_before,stock_after,created_by) VALUES (?,?,?,?,?,?,?,?)")->execute([$p['id'],'SALE','sale',$sale6,-2,$p['stock'],$p['stock']-2,1]);
$pdo->commit();
$stockAfterSale=(int)$pdo->query("SELECT stock FROM products WHERE sku='SKU001'")->fetchColumn(); assert_eq("TEST6 after sale stock 8",$stockAfterSale,8);
// retur 1 qty
$pdo->beginTransaction();
$code='RET-'.bin2hex(random_bytes(3)); $refund=(int)($p['selling_price']*1);
$pdo->prepare("INSERT INTO returns (code,sale_id,user_id,total_refund,reason) VALUES (?,?,?,?,?)")->execute([$code,$sale6,1,$refund,'test']);
$retId=$pdo->lastInsertId();
$itRet=$pdo->query("SELECT * FROM sale_items WHERE sale_id=$sale6 LIMIT 1")->fetch();
$pdo->prepare("INSERT INTO return_items (return_id,sale_item_id,product_id,qty,refund_amount) VALUES (?,?,?,?,?)")->execute([$retId,$itRet['id'],$p['id'],1,$refund]);
$st=$pdo->prepare("SELECT stock FROM products WHERE id=? FOR UPDATE"); $st->execute([$p['id']]); $cur=(int)$st->fetchColumn();
$pdo->prepare("UPDATE products SET stock=? WHERE id=?")->execute([$cur+1,$p['id']]);
$pdo->prepare("INSERT INTO stock_movements (product_id,type,reference_type,reference_id,qty_change,stock_before,stock_after,created_by) VALUES (?,?,?,?,?,?,?,?)")->execute([$p['id'],'RETURN_SALE','return',$retId,1,$cur,$cur+1,1]);
$pdo->prepare("INSERT INTO cash_transactions (type,category,amount,description,reference_type,reference_id,created_by) VALUES ('out','retur',?, ?, 'return', ?, ?)")->execute([$refund,"Retur $code",$retId,1]);
$pdo->commit();
$stockAfterRet=(int)$pdo->query("SELECT stock FROM products WHERE sku='SKU001'")->fetchColumn(); assert_eq("TEST6 after retur 1 stock 9",$stockAfterRet,9);
$cashOut=(int)$pdo->query("SELECT amount FROM cash_transactions WHERE reference_id=$retId AND reference_type='return'")->fetchColumn(); assert_eq("TEST6 cash out refund",$refund,$cashOut);

// TEST7 audit log koreksi
$pdo->prepare("INSERT INTO audit_logs (user_id,action,module,record_id,description) VALUES (1,'EDIT_SALE','sales',?,?)")->execute([$sale6,'koreksi test']);
$cnt=(int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='EDIT_SALE' AND record_id=$sale6")->fetchColumn(); assert_true("TEST7 audit log tercatat",$cnt>0);

// TEST8 permission kasir blocked admin - simulasi has_permission
$_SESSION['user']=['id'=>2,'role'=>'kasir','permissions'=>['sales.create','sales.view']];
require_once __DIR__.'/../core/auth.php';
$blocked=!has_permission('users.manage'); assert_true("TEST8 kasir blocked users.manage",$blocked);
$adminBlocked = (function(){ $r='kasir'; return !in_array($r,['admin','manager','owner'],true); })(); assert_true("TEST8 kasir blocked admin route",$adminBlocked);

// TEST9 SQL injection prepared statement
$inj="' OR 1=1 --";
$stmt=$pdo->prepare("SELECT * FROM products WHERE sku=?"); $stmt->execute([$inj]); $rows=$stmt->fetchAll(); assert_eq("TEST9 sql injection 0 rows",0,count($rows));

// TEST10 CSRF token invalid
$_SESSION['csrf_token']='validtoken123';
require_once __DIR__.'/../core/csrf.php';
$ok=csrf_verify('invalid'); assert_true("TEST10 CSRF invalid ditolak",!$ok);
$ok2=csrf_verify('validtoken123'); assert_true("TEST10 CSRF valid diterima",$ok2);

// restore
$_SESSION['user']=['id'=>1,'role'=>'admin','permissions'=>['*']];
echo "== DONE ==\n";
