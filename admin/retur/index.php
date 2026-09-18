<?php
require_once __DIR__.'/../../config/app.php';
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../core/auth.php';
require_once __DIR__.'/../../core/helper.php';
require_once __DIR__.'/../../core/csrf.php';
require_once __DIR__.'/../../core/audit.php';
require_role(['admin','manager','owner']);
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!csrf_verify($_POST['_csrf']??'')){flash_set('error','CSRF tidak valid'); redirect($_SERVER['REQUEST_URI']);}
 $sale_id=(int)($_POST['sale_id']??0); $reason=trim($_POST['reason']??'');
 $item_ids=$_POST['item_id']??[]; $qtys=$_POST['qty']??[];
 if($sale_id==0||empty($item_ids)){flash_set('error','Pilih transaksi dan item'); redirect($_SERVER['REQUEST_URI']);}
 $sale=$pdo->prepare("SELECT * FROM sales WHERE id=?"); $sale->execute([$sale_id]); $sale=$sale->fetch();
 if(!$sale || $sale['status']!='completed'){flash_set('error','Hanya transaksi completed bisa diretur'); redirect($_SERVER['REQUEST_URI']);}
 try{
  $pdo->beginTransaction();
  $code='RET-'.date('YmdHis').'-'.bin2hex(random_bytes(2));
  $totalRefund=0; $rows=[];
  foreach($item_ids as $i=>$sid){
    $sid=(int)$sid; $q=(int)($qtys[$i]??0); if($q<=0) continue;
    $it=$pdo->prepare("SELECT * FROM sale_items WHERE id=? AND sale_id=?"); $it->execute([$sid,$sale_id]); $it=$it->fetch();
    if(!$it) throw new Exception('Item tidak valid');
    $already=$pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM return_items WHERE sale_item_id=?"); $already->execute([$sid]); $already=(int)$already->fetchColumn();
    if($already+$q > $it['qty']) throw new Exception('Qty retur melebihi qty beli: '.$it['product_name']);
    $refund=(int)round($it['subtotal']/$it['qty']*$q);
    $totalRefund+=$refund; $rows[]=['sid'=>$sid,'pid'=>$it['product_id'],'qty'=>$q,'refund'=>$refund];
  }
  if(empty($rows)) throw new Exception('Tidak ada item valid');
  $pdo->prepare("INSERT INTO returns (code,sale_id,user_id,total_refund,reason) VALUES (?,?,?,?,?)")->execute([$code,$sale_id,current_user()['id'],$totalRefund,$reason]);
  $rid=$pdo->lastInsertId();
  foreach($rows as $r){
    $pdo->prepare("INSERT INTO return_items (return_id,sale_item_id,product_id,qty,refund_amount,reason) VALUES (?,?,?,?,?,?)")->execute([$rid,$r['sid'],$r['pid'],$r['qty'],$r['refund'],$reason]);
    $p=$pdo->prepare("SELECT stock FROM products WHERE id=? FOR UPDATE"); $p->execute([$r['pid']]); $st=(int)$p->fetchColumn();
    $new=$st+$r['qty']; $pdo->prepare("UPDATE products SET stock=? WHERE id=?")->execute([$new,$r['pid']]);
    $pdo->prepare("INSERT INTO stock_movements (product_id,type,reference_type,reference_id,qty_change,stock_before,stock_after,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?)")->execute([$r['pid'],'RETURN_SALE','return',$rid,$r['qty'],$st,$new,"Retur $code", current_user()['id']]);
  }
  $pdo->prepare("INSERT INTO cash_transactions (type,category,amount,description,reference_type,reference_id,created_by) VALUES ('out','retur',?, ?, 'return', ?, ?)")->execute([$totalRefund,"Retur $code",$rid, current_user()['id']]);
  $hasAll=true;
  // if all items fully returned -> mark sale refunded
  $pdo->commit(); audit('RETURN_SALE','returns',$rid,"Retur $code refund ".rupiah($totalRefund)); flash_set('success','Retur berhasil, refund '.rupiah($totalRefund));
 }catch(Exception $e){ if($pdo->inTransaction()) $pdo->rollBack(); flash_set('error',$e->getMessage()); }
 redirect($_SERVER['REQUEST_URI']);
}
$sales=$pdo->query("SELECT s.id,s.transaction_number,s.grand_total,s.created_at FROM sales s WHERE s.status='completed' ORDER BY s.id DESC LIMIT 50")->fetchAll();
$rets=$pdo->query("SELECT r.*, s.transaction_number, u.name as user FROM returns r JOIN sales s ON s.id=r.sale_id JOIN users u ON u.id=r.user_id ORDER BY r.id DESC LIMIT 20")->fetchAll();
?>
<!DOCTYPE html><html lang="id"><head><title>Retur • <?=e(APP_NAME)?></title><?php include __DIR__.'/../../components/head.php'; ?></head>
<body class="min-h-screen bg-slate-50 flex flex-col"><?php include __DIR__.'/../../components/header.php';?>
<main class="flex w-full flex-1 flex-col gap-6 px-4 pb-10 pt-5 sm:px-6 lg:px-8 lg:flex-row lg:items-start"><?php include __DIR__.'/../../components/sidebar_admin.php';?>
<section class="order-2 flex-1 space-y-4">
<nav class="text-[11px] text-slate-500">Dashboard / Retur</nav>
<div class="flex justify-between items-center"><h2 class="text-lg font-semibold">Retur Penjualan</h2><button data-modal-toggle="#modalRetur" class="rounded-full bg-emerald-600 px-4 py-1.5 text-xs text-white">+ Buat Retur</button></div>
<div class="rounded-2xl border bg-white p-4 shadow-sm"><h3 class="text-xs font-semibold mb-2">History Retur</h3><div class="overflow-auto rounded-xl border"><table class="min-w-full divide-y text-xs"><thead class="bg-slate-100"><tr><th class="px-3 py-2">Kode Retur</th><th class="px-3 py-2">Transaksi</th><th class="px-3 py-2 text-right">Refund</th><th class="px-3 py-2">Alasan</th><th class="px-3 py-2">Oleh</th><th class="px-3 py-2">Tanggal</th></tr></thead><tbody class="divide-y"><?php foreach($rets as $r):?><tr><td class="px-3 py-2 font-mono"><?=e($r['code'])?></td><td class="px-3 py-2"><?=e($r['transaction_number'])?></td><td class="px-3 py-2 text-right font-semibold"><?=rupiah($r['total_refund'])?></td><td class="px-3 py-2"><?=e($r['reason'])?></td><td class="px-3 py-2"><?=e($r['user'])?></td><td class="px-3 py-2"><?=e($r['created_at'])?></td></tr><?php endforeach; if(!$rets) echo '<tr><td colspan="6" class="px-3 py-6 text-center text-slate-400">Belum ada retur</td></tr>';?></tbody></table></div></div>
</section></main>
<div id="modalRetur" class="modal-dashboard hidden"><div class="modal-dialog" style="max-width:650px"><div class="modal-content"><div class="flex justify-between mb-3"><h3 class="text-sm font-semibold">Buat Retur</h3><button data-modal-hide="#modalRetur" class="btn-close"></button></div>
<form method="POST" class="space-y-3"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
<label class="text-xs">Pilih Transaksi</label><select name="sale_id" id="saleSelect" required class="w-full rounded-xl border px-3 py-2 text-xs"><option value="">- Pilih -</option><?php foreach($sales as $s):?><option value="<?=$s['id']?>"><?=e($s['transaction_number'])?> - <?=rupiah($s['grand_total'])?> (<?=e($s['created_at'])?>)</option><?php endforeach;?></select>
<div id="itemsBox" class="space-y-2"></div>
<textarea name="reason" placeholder="Alasan retur" required class="w-full rounded-xl border px-3 py-2 text-xs"></textarea>
<button class="w-full rounded-xl bg-emerald-600 py-2 text-xs text-white">Proses Retur (stok + , kas keluar)</button>
</form></div></div></div>
<script>
document.getElementById('saleSelect').addEventListener('change',async e=>{
 const id=e.target.value; if(!id) return;
 const r=await fetch('<?=url('/api/sale_items')?>?sale_id='+id); const j=await r.json();
 let h='';
 (j.data||[]).forEach(it=>{
  h+=`<label class="flex gap-2 items-center rounded-xl border px-3 py-2 bg-slate-50"><input type="checkbox" name="item_id[]" value="${it.id}" checked class="rounded"> <span class="flex-1 text-xs">${it.product_name} — qty ${it.qty} @ ${it.price}</span><input type="number" name="qty[]" value="${it.qty}" min="1" max="${it.qty}" class="w-16 rounded border px-2 py-1 text-xs"></label>`;
 });
 document.getElementById('itemsBox').innerHTML=h||'<p class="text-xs text-slate-400">Tidak ada item</p>';
});
</script>
<?php include __DIR__.'/../../components/footer.php';?></body></html>
