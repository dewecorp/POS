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
 $act=$_POST['act']??''; $id=(int)($_POST['id']??0);
 $sale=$pdo->prepare("SELECT * FROM sales WHERE id=?"); $sale->execute([$id]); $sale=$sale->fetch();
 if(!$sale){flash_set('error','Transaksi tidak ditemukan'); redirect($_SERVER['REQUEST_URI']);}
 if($act==='void'){
   if($sale['status']!=='completed'){flash_set('error','Hanya transaksi completed bisa di-void'); redirect($_SERVER['REQUEST_URI']);}
   $reason=trim($_POST['reason']??''); if($reason===''){flash_set('error','Alasan wajib'); redirect($_SERVER['REQUEST_URI']);}
   $pdo->beginTransaction();
   $pdo->prepare("UPDATE sales SET status='cancelled', correction_reason=? WHERE id=?")->execute([$reason,$id]);
   $items=$pdo->prepare("SELECT * FROM sale_items WHERE sale_id=?"); $items->execute([$id]); $its=$items->fetchAll();
   foreach($its as $it){
     $p=$pdo->prepare("SELECT stock FROM products WHERE id=? FOR UPDATE"); $p->execute([$it['product_id']]); $stock=(int)$p->fetchColumn();
     $new=$stock+$it['qty']; $pdo->prepare("UPDATE products SET stock=? WHERE id=?")->execute([$new,$it['product_id']]);
     $pdo->prepare("INSERT INTO stock_movements (product_id,type,reference_type,reference_id,qty_change,stock_before,stock_after,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?)")->execute([$it['product_id'],'CORRECTION','sale',$id,$it['qty'],$stock,$new,"Void $reason", current_user()['id']]);
   }
   $pdo->prepare("INSERT INTO cash_transactions (type,category,amount,description,reference_type,reference_id,created_by) VALUES ('out','void',?, ?, 'sale', ?, ?)")->execute([$sale['grand_total'],"Void ".$sale['transaction_number'].": $reason",$id, current_user()['id']]);
   $pdo->commit(); audit('CANCEL_SALE','sales',$id,"Void ".$sale['transaction_number']." alasan: $reason", $sale, ['status'=>'cancelled']); flash_set('success','Transaksi di-void, stok dikembalikan');
   redirect($_SERVER['REQUEST_URI']);
 }
 if($act==='correction'){
   $reason=trim($_POST['reason']??''); if($reason===''){flash_set('error','Alasan koreksi wajib'); redirect($_SERVER['REQUEST_URI']);}
   $new_pm=trim($_POST['payment_method']??$sale['payment_method']); $new_cust=$_POST['customer_id']? (int)$_POST['customer_id']:null;
   $old=json_encode($sale);
   $pdo->prepare("UPDATE sales SET customer_id=?, payment_method=?, correction_reason=?, status='corrected' WHERE id=?")->execute([$new_cust,$new_pm,$reason,$id]);
   audit('EDIT_SALE','sales',$id,"Koreksi ".$sale['transaction_number']." alasan: $reason", json_decode($old,true), ['payment_method'=>$new_pm,'customer_id'=>$new_cust]); flash_set('success','Koreksi disimpan (audit tercatat)');
   redirect($_SERVER['REQUEST_URI']);
 }
}
$q=trim($_GET['q']??''); $from=$_GET['from']??''; $to=$_GET['to']??''; $status=$_GET['status']??''; $page=max(1,(int)($_GET['page']??1)); $per=15;
$where="WHERE 1"; $par=[];
if($q!==''){ $where.=" AND (s.transaction_number LIKE ? OR u.name LIKE ?)"; $par[]="%$q%"; $par[]="%$q%"; }
if($from!==''){ $where.=" AND DATE(s.created_at)>=?"; $par[]=$from; }
if($to!==''){ $where.=" AND DATE(s.created_at)<=?"; $par[]=$to; }
if($status && in_array($status,['completed','cancelled','refunded','corrected'])){ $where.=" AND s.status=?"; $par[]=$status; }
$total=$pdo->prepare("SELECT COUNT(*) FROM sales s JOIN users u ON u.id=s.cashier_id $where"); $total->execute($par); $total=(int)$total->fetchColumn();
list($pages,$page,$off)=paginate_params($total,$page,$per);
$stmt=$pdo->prepare("SELECT s.*, u.name as cashier, c.name as customer FROM sales s JOIN users u ON u.id=s.cashier_id LEFT JOIN customers c ON c.id=s.customer_id $where ORDER BY s.id DESC LIMIT $per OFFSET $off"); $stmt->execute($par); $rows=$stmt->fetchAll();
$customers=$pdo->query("SELECT id,name FROM customers WHERE is_active=1 ORDER BY name")->fetchAll();

$saleIds=array_column($rows,'id');
$itemsBySale=[];
if(!empty($saleIds)){
  $inClause=implode(',',array_map('intval',$saleIds));
  $itRows=$pdo->query("SELECT * FROM sale_items WHERE sale_id IN ($inClause)")->fetchAll();
  foreach($itRows as $it){ $itemsBySale[$it['sale_id']][]=$it; }
}
?>
<!DOCTYPE html><html lang="id"><head><title>Penjualan • <?=e(APP_NAME)?></title><?php include __DIR__.'/../../components/head.php'; ?></head>
<body class="min-h-screen bg-slate-50 flex flex-col"><?php include __DIR__.'/../../components/header.php';?>
<main class="flex w-full flex-1 flex-col gap-6 px-4 pb-10 pt-5 sm:px-6 lg:px-8 lg:flex-row lg:items-start"><?php include __DIR__.'/../../components/sidebar_admin.php';?>
<section class="order-2 flex-1 space-y-4">
<nav class="text-[11px] text-slate-500">Dashboard / Penjualan</nav>
<h2 class="text-lg font-semibold">Penjualan</h2>
<form method="GET" class="rounded-2xl border bg-white p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 text-xs"><input name="q" value="<?=e($q)?>" placeholder="No transaksi / kasir" class="rounded-xl border px-3 py-2 sm:col-span-2 lg:col-span-1"><input type="date" name="from" value="<?=e($from)?>" class="rounded-xl border px-3 py-2"><input type="date" name="to" value="<?=e($to)?>" class="rounded-xl border px-3 py-2"><select name="status" class="rounded-xl border px-3 py-2"><option value="">Semua status</option><option value="completed" <?=$status==='completed'?'selected':''?>>Completed</option><option value="cancelled" <?=$status==='cancelled'?'selected':''?>>Cancelled</option><option value="corrected" <?=$status==='corrected'?'selected':''?>>Corrected</option><option value="refunded" <?=$status==='refunded'?'selected':''?>>Refunded</option></select><button class="justify-self-start rounded-xl bg-emerald-600 px-4 py-2 text-white font-medium">Filter</button></form>
<div class="rounded-2xl border bg-white p-4 shadow-sm"><div class="overflow-auto rounded-xl border"><table class="min-w-full divide-y text-xs"><thead class="bg-slate-100"><tr><th class="px-3 py-2 text-left">No</th><th class="px-3 py-2 text-left">Tanggal</th><th class="px-3 py-2 text-left">Kasir</th><th class="px-3 py-2 text-left">Pelanggan</th><th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2 text-center">Status</th><th class="px-3 py-2 text-center">Aksi</th></tr></thead><tbody class="divide-y">
<?php foreach($rows as $r):?><tr class="hover:bg-slate-50"><td class="px-3 py-2 font-mono text-[11px]"><?=e($r['transaction_number'])?></td><td class="px-3 py-2"><?=e($r['created_at'])?></td><td class="px-3 py-2"><?=e($r['cashier'])?></td><td class="px-3 py-2"><?=e($r['customer']??'Umum')?></td><td class="px-3 py-2 text-right font-semibold"><?=rupiah($r['grand_total'])?></td><td class="px-3 py-2 text-center"><span class="rounded-full px-2 py-0.5 text-[10px] <?=$r['status']=='completed'?'bg-emerald-50 text-emerald-600':($r['status']=='cancelled'?'bg-rose-50 text-rose-600':'bg-amber-50 text-amber-600')?>"><?=e($r['status'])?></span></td>
<td class="px-3 py-2 text-center flex justify-center gap-1">
<button type="button" data-modal-toggle="#detailSale<?=$r['id']?>" title="Detail Penjualan" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:border-emerald-500 hover:bg-emerald-50 hover:text-emerald-600 transition"><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button>
<a href="<?=url('/kasir/cetak-struk')?>?id=<?=$r['id']?>" target="_blank" title="Cetak Struk" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition"><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg></a>
<?php if($r['status']=='completed'):?><button type="button" data-modal-toggle="#void<?=$r['id']?>" title="Void / Batalkan" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100"><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg></button><button type="button" data-modal-toggle="#corr<?=$r['id']?>" title="Koreksi Transaksi" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-amber-200 bg-amber-50 text-amber-600 hover:bg-amber-100"><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button><?php endif;?></td></tr>
<?php endforeach; if(!$rows) echo '<tr><td colspan="7" class="px-3 py-6 text-center text-slate-400">Tidak ada data</td></tr>';?>
</tbody></table></div>
<div class="mt-3 flex justify-between text-xs text-slate-500"><span><?=$total?> transaksi</span><div class="flex gap-1"><?php for($i=1;$i<=$pages;$i++):?><a href="?q=<?=urlencode($q)?>&from=<?=$from?>&to=<?=$to?>&status=<?=$status?>&page=<?=$i?>" class="rounded-full border px-3 py-1 <?=$i==$page?'bg-emerald-600 text-white':''?>"><?=$i?></a><?php endfor;?></div></div>
</div></section></main>

<!-- Modals Detail, Void & Koreksi -->
<?php foreach($rows as $r):?>
<!-- Modal Detail Penjualan -->
<div id="detailSale<?=$r['id']?>" class="modal-dashboard hidden"><div class="modal-dialog max-w-lg"><div class="modal-content"><div class="flex justify-between items-center pb-2 border-b"><div class="text-left"><h3 class="text-sm font-semibold text-slate-800">Detail Penjualan</h3><p class="text-[11px] font-mono text-emerald-600"><?=e($r['transaction_number'])?></p></div><button type="button" data-modal-hide="#detailSale<?=$r['id']?>" class="btn-close"></button></div>
<div class="py-3 text-xs space-y-3 text-left">
  <div class="grid grid-cols-2 gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200">
    <div><span class="text-[10px] text-slate-400 block">Waktu Transaksi</span><span class="font-medium text-slate-700"><?=date('d/m/Y H:i',strtotime($r['created_at']))?></span></div>
    <div><span class="text-[10px] text-slate-400 block">Kasir</span><span class="font-medium text-slate-700"><?=e($r['cashier'])?></span></div>
    <div><span class="text-[10px] text-slate-400 block">Pelanggan</span><span class="font-medium text-slate-700"><?=e($r['customer']??'Umum')?></span></div>
    <div><span class="text-[10px] text-slate-400 block">Metode Pembayaran</span><span class="font-medium uppercase text-slate-700"><?=e($r['payment_method'])?></span></div>
  </div>

  <div class="overflow-hidden rounded-xl border border-slate-200">
    <table class="min-w-full divide-y text-[11px]">
      <thead class="bg-slate-100">
        <tr>
          <th class="px-2.5 py-1.5 text-left font-semibold text-slate-600">Item Produk</th>
          <th class="px-2 py-1.5 text-center font-semibold text-slate-600">Qty</th>
          <th class="px-2.5 py-1.5 text-right font-semibold text-slate-600">Subtotal</th>
        </tr>
      </thead>
      <tbody class="divide-y bg-white">
        <?php foreach($itemsBySale[$r['id']] ?? [] as $it): ?>
        <tr>
          <td class="px-2.5 py-1.5">
            <div class="font-medium text-slate-800"><?=e($it['product_name'])?></div>
            <div class="text-[10px] text-slate-400"><?=rupiah($it['price'])?><?php if($it['discount_amount']>0):?> <span class="text-rose-500">(-<?=rupiah($it['discount_amount'])?>)</span><?php endif;?></div>
          </td>
          <td class="px-2 py-1.5 text-center font-medium"><?=$it['qty']?></td>
          <td class="px-2.5 py-1.5 text-right font-semibold text-slate-800"><?=rupiah($it['subtotal'])?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="space-y-1 text-xs border-t border-dashed pt-2 px-1">
    <div class="flex justify-between text-slate-600"><span>Subtotal</span><span><?=rupiah($r['subtotal'])?></span></div>
    <?php if($r['discount_amount']>0):?><div class="flex justify-between text-rose-600"><span>Diskon</span><span>-<?=rupiah($r['discount_amount'])?></span></div><?php endif;?>
    <?php if($r['tax_amount']>0):?><div class="flex justify-between text-slate-600"><span>Pajak (<?=e($r['tax_percent'])?>%)</span><span><?=rupiah($r['tax_amount'])?></span></div><?php endif;?>
    <?php if($r['additional_cost']>0):?><div class="flex justify-between text-slate-600"><span>Biaya Tambahan</span><span><?=rupiah($r['additional_cost'])?></span></div><?php endif;?>
    <div class="flex justify-between text-sm font-bold text-slate-900 border-t pt-1.5 mt-1"><span>Grand Total</span><span class="text-emerald-600"><?=rupiah($r['grand_total'])?></span></div>
    <div class="flex justify-between text-slate-600"><span>Jumlah Bayar</span><span><?=rupiah($r['paid_amount'])?></span></div>
    <div class="flex justify-between text-slate-600"><span>Kembalian</span><span><?=rupiah($r['change_amount'])?></span></div>
  </div>
</div>
<div class="flex gap-2 pt-2 border-t">
  <a href="<?=url('/kasir/cetak-struk')?>?id=<?=$r['id']?>" target="_blank" class="flex-1 rounded-xl bg-emerald-600 py-2 text-center text-xs font-semibold text-white hover:bg-emerald-700 transition">Cetak Struk</a>
  <button type="button" data-modal-hide="#detailSale<?=$r['id']?>" class="rounded-xl border border-slate-300 px-4 py-2 text-xs font-medium text-slate-600 hover:bg-slate-100 transition">Tutup</button>
</div>
</div></div></div>
<div id="void<?=$r['id']?>" class="modal-dashboard hidden"><div class="modal-dialog max-w-sm"><div class="modal-content"><div class="flex justify-between mb-2"><h3 class="text-sm font-semibold">Void <?=e($r['transaction_number'])?></h3><button type="button" data-modal-hide="#void<?=$r['id']?>" class="btn-close"></button></div><form method="POST" class="space-y-2"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="void"><input type="hidden" name="id" value="<?=$r['id']?>"><textarea name="reason" required placeholder="Alasan void" class="w-full rounded-xl border px-3 py-2 text-xs"></textarea><button class="w-full rounded-xl bg-rose-600 py-2 text-xs text-white">Konfirmasi Void (stok kembali)</button></form></div></div></div>
<div id="corr<?=$r['id']?>" class="modal-dashboard hidden"><div class="modal-dialog max-w-sm"><div class="modal-content"><div class="flex justify-between mb-2"><h3 class="text-sm font-semibold">Koreksi <?=e($r['transaction_number'])?></h3><button type="button" data-modal-hide="#corr<?=$r['id']?>" class="btn-close"></button></div><form method="POST" class="space-y-2"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="correction"><input type="hidden" name="id" value="<?=$r['id']?>"><select name="customer_id" class="w-full rounded-xl border px-3 py-2 text-xs"><option value="">Umum</option><?php foreach($customers as $c):?><option value="<?=$c['id']?>" <?=$r['customer_id']==$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select><select name="payment_method" class="w-full rounded-xl border px-3 py-2 text-xs"><option value="tunai" <?=$r['payment_method']=='tunai'?'selected':''?>>Tunai</option><option value="transfer" <?=$r['payment_method']=='transfer'?'selected':''?>>Transfer</option><option value="qris" <?=$r['payment_method']=='qris'?'selected':''?>>QRIS</option><option value="debit" <?=$r['payment_method']=='debit'?'selected':''?>>Debit</option></select><textarea name="reason" required placeholder="Alasan koreksi (audit log)" class="w-full rounded-xl border px-3 py-2 text-xs"></textarea><button class="w-full rounded-xl bg-amber-500 py-2 text-xs text-white">Simpan Koreksi</button></form></div></div></div>
<?php endforeach;?>
<?php include __DIR__.'/../../components/footer.php';?></body></html>
