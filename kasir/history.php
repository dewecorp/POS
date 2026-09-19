<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/helper.php';
require_once __DIR__.'/../core/csrf.php';
require_once __DIR__.'/../core/audit.php';
require_login();
$pdo=db();

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['act']??'')==='koreksi_barang'){
  if(!csrf_verify($_POST['_csrf']??'')){ flash_set('error','CSRF tidak valid'); redirect($_SERVER['REQUEST_URI']); }
  $sale_id=(int)($_POST['sale_id']??0);
  $reason=trim($_POST['reason']??'');
  $item_ids=$_POST['item_id']??[];
  $qtys=$_POST['qty']??[];
  $deletes=$_POST['delete_item']??[]; // array of item_id yang mau dihapus
  $newProductIds=$_POST['new_product_id']??[];
  $newQtys=$_POST['new_qty']??[];

  if($sale_id<=0 || empty($reason)){
    flash_set('error','Alasan koreksi wajib diisi'); redirect($_SERVER['REQUEST_URI']);
  }

  $sStmt=$pdo->prepare("SELECT * FROM sales WHERE id=? FOR UPDATE");
  $sStmt->execute([$sale_id]);
  $sale=$sStmt->fetch();
  if(!$sale || in_array($sale['status'],['cancelled','refunded'],true)){
    flash_set('error','Transaksi tidak dapat diedit'); redirect($_SERVER['REQUEST_URI']);
  }

  try{
    $pdo->beginTransaction();
    $oldData=['sale'=>$sale,'items'=>$pdo->query("SELECT * FROM sale_items WHERE sale_id=$sale_id")->fetchAll()];
    $newSubtotal=0;
    $remainingItems=0;

    foreach($item_ids as $idx=>$itId){
      $itId=(int)$itId;
      $itStmt=$pdo->prepare("SELECT * FROM sale_items WHERE id=? AND sale_id=? FOR UPDATE");
      $itStmt->execute([$itId,$sale_id]);
      $item=$itStmt->fetch();
      if(!$item) continue;

      $isDelete=!empty($deletes[$itId]);
      $newQty=$isDelete ? 0 : max(0,(int)($qtys[$idx]??$item['qty']));

      if($isDelete || $newQty===0){
        // Kembalikan semua stok barang ini
        $pStmt=$pdo->prepare("SELECT stock FROM products WHERE id=? FOR UPDATE");
        $pStmt->execute([$item['product_id']]);
        $curStock=(int)$pStmt->fetchColumn();
        $restored=$curStock + $item['qty'];
        $pdo->prepare("UPDATE products SET stock=? WHERE id=?")->execute([$restored,$item['product_id']]);
        $pdo->prepare("INSERT INTO stock_movements (product_id,type,reference_type,reference_id,qty_change,stock_before,stock_after,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$item['product_id'],'CORRECTION','sale',$sale_id,$item['qty'],$curStock,$restored,"Hapus item di {$sale['transaction_number']}: $reason",current_user()['id']]);
        $pdo->prepare("DELETE FROM sale_items WHERE id=?")->execute([$itId]);
      } else {
        // Penyesuaian qty
        $diffQty=$newQty - (int)$item['qty']; // positif: ambil stok lagi; negatif: kembalikan stok
        if($diffQty !== 0){
          $pStmt=$pdo->prepare("SELECT stock FROM products WHERE id=? FOR UPDATE");
          $pStmt->execute([$item['product_id']]);
          $curStock=(int)$pStmt->fetchColumn();
          if(!ALLOW_NEGATIVE_STOCK && $diffQty > 0 && $curStock < $diffQty){
            throw new Exception("Stok {$item['product_name']} tidak cukup untuk penambahan qty (sisa $curStock)");
          }
          $newStock=$curStock - $diffQty;
          $pdo->prepare("UPDATE products SET stock=? WHERE id=?")->execute([$newStock,$item['product_id']]);
          $pdo->prepare("INSERT INTO stock_movements (product_id,type,reference_type,reference_id,qty_change,stock_before,stock_after,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?)")
              ->execute([$item['product_id'],'CORRECTION','sale',$sale_id,-$diffQty,$curStock,$newStock,"Koreksi qty ({$item['qty']}→{$newQty}) {$sale['transaction_number']}: $reason",current_user()['id']]);
        }

        // Hitung ulang line discount & subtotal
        $price=(int)$item['price'];
        $lineDisc=0;
        if($item['discount_type']==='percent'){
          $lineDisc=(int)round($price * $newQty * (float)$item['discount_value'] / 100);
        } elseif($item['discount_type']==='nominal'){
          $lineDisc=(int)min((float)$item['discount_value'], $price * $newQty);
        }
        $lineSub=($price * $newQty) - $lineDisc;

        $pdo->prepare("UPDATE sale_items SET qty=?, discount_amount=?, subtotal=? WHERE id=?")
            ->execute([$newQty,$lineDisc,$lineSub,$itId]);

        $newSubtotal += $lineSub;
        $remainingItems++;
      }
    }

    // Tambah barang baru ke transaksi
    foreach($newProductIds as $idx=>$pid){
      $pid=(int)$pid;
      $addQty=(int)($newQtys[$idx]??0);
      if($pid<=0 || $addQty<=0) continue;

      $pStmt=$pdo->prepare("SELECT * FROM products WHERE id=? FOR UPDATE");
      $pStmt->execute([$pid]);
      $prod=$pStmt->fetch();
      if(!$prod || !$prod['is_active']) throw new Exception("Produk tidak ditemukan / nonaktif");

      $curStock=(int)$prod['stock'];
      if(!ALLOW_NEGATIVE_STOCK && $curStock < $addQty){
        throw new Exception("Stok {$prod['name']} tidak cukup (sisa $curStock)");
      }
      $newStock=$curStock - $addQty;
      $pdo->prepare("UPDATE products SET stock=? WHERE id=?")->execute([$newStock,$pid]);
      $pdo->prepare("INSERT INTO stock_movements (product_id,type,reference_type,reference_id,qty_change,stock_before,stock_after,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?)")
          ->execute([$pid,'CORRECTION','sale',$sale_id,-$addQty,$curStock,$newStock,"Tambah item di {$sale['transaction_number']}: $reason",current_user()['id']]);

      $price=(int)$prod['selling_price'];
      $lineSub=$price * $addQty;
      $pdo->prepare("INSERT INTO sale_items (sale_id,product_id,product_name,sku,barcode,qty,price,discount_type,discount_value,discount_amount,subtotal) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
          ->execute([$sale_id,$pid,$prod['name'],$prod['sku'],$prod['barcode'],$addQty,$price,'none',0,0,$lineSub]);

      $newSubtotal += $lineSub;
      $remainingItems++;
    }

    if($remainingItems===0){
      throw new Exception("Semua item dihapus. Gunakan fitur Void jika transaksi dibatalkan.");
    }

    // Hitung ulang grand total dari seluruh item tersisa di DB
    $newSubtotal=(int)$pdo->query("SELECT COALESCE(SUM(subtotal),0) FROM sale_items WHERE sale_id=$sale_id")->fetchColumn();
    $trxDisc=0;
    if($sale['discount_type']==='percent'){
      $trxDisc=(int)round($newSubtotal * (float)$sale['discount_value'] / 100);
    } elseif($sale['discount_type']==='nominal'){
      $trxDisc=(int)min((float)$sale['discount_value'], $newSubtotal);
    }
    $afterDisc=$newSubtotal - $trxDisc;
    $taxAmt=(int)round($afterDisc * (float)$sale['tax_percent'] / 100);
    $newGrand=$afterDisc + $taxAmt + (int)$sale['additional_cost'];
    $newPaid=max($newGrand, (int)$sale['paid_amount']);
    $newChange=$newPaid - $newGrand;

    $pdo->prepare("UPDATE sales SET subtotal=?, discount_amount=?, tax_amount=?, grand_total=?, paid_amount=?, change_amount=?, status='corrected', correction_reason=? WHERE id=?")
        ->execute([$newSubtotal,$trxDisc,$taxAmt,$newGrand,$newPaid,$newChange,$reason,$sale_id]);

    $newData=['subtotal'=>$newSubtotal,'grand_total'=>$newGrand,'reason'=>$reason];
    audit('CORRECT_SALE_ITEMS','sales',$sale_id,"Koreksi item {$sale['transaction_number']}: $reason",$oldData,$newData);

    $pdo->commit();
    flash_set('success',"Transaksi {$sale['transaction_number']} berhasil dikoreksi. Grand total baru: ".rupiah($newGrand));
  }catch(Exception $e){
    if($pdo->inTransaction()) $pdo->rollBack();
    flash_set('error',$e->getMessage());
  }
  redirect($_SERVER['REQUEST_URI']);
}

$q=trim($_GET['q']??''); $from=$_GET['from']??''; $to=$_GET['to']??''; $status=$_GET['status']??''; $pay=$_GET['pay']??''; $page=max(1,(int)($_GET['page']??1)); $per=15;
$where="WHERE 1"; $par=[];
if($q!==''){ $where.=" AND (s.transaction_number LIKE ? OR u.name LIKE ? OR c.name LIKE ?)"; $par[]="%$q%"; $par[]="%$q%"; $par[]="%$q%"; }
if($from!==''){ $where.=" AND DATE(s.created_at) >= ?"; $par[]=$from; }
if($to!==''){ $where.=" AND DATE(s.created_at) <= ?"; $par[]=$to; }
if($status!=='' && in_array($status,['completed','cancelled','refunded','corrected'])){ $where.=" AND s.status=?"; $par[]=$status; }
if($pay!==''){ $where.=" AND s.payment_method=?"; $par[]=$pay; }
if((current_user()['role']??'')==='kasir'){ $where.=" AND s.cashier_id=?"; $par[]=current_user()['id']; }
$total=$pdo->prepare("SELECT COUNT(*) FROM sales s LEFT JOIN users u ON u.id=s.cashier_id LEFT JOIN customers c ON c.id=s.customer_id $where"); $total->execute($par); $total=(int)$total->fetchColumn();
list($pages,$page,$off)=paginate_params($total,$page,$per);
$stmt=$pdo->prepare("SELECT s.*, u.name as cashier, c.name as customer FROM sales s JOIN users u ON u.id=s.cashier_id LEFT JOIN customers c ON c.id=s.customer_id $where ORDER BY s.id DESC LIMIT $per OFFSET $off"); $stmt->execute($par); $rows=$stmt->fetchAll();
$methods=$pdo->query("SELECT code FROM payment_methods")->fetchAll(PDO::FETCH_COLUMN);
$allProducts=$pdo->query("SELECT id,sku,name,selling_price,stock FROM products WHERE is_active=1 ORDER BY name")->fetchAll();

// Preload items untuk modal edit barang tiap transaksi
$saleIds=array_column($rows,'id');
$itemsBySale=[];
if(!empty($saleIds)){
  $inClause=implode(',',array_map('intval',$saleIds));
  $itRows=$pdo->query("SELECT * FROM sale_items WHERE sale_id IN ($inClause)")->fetchAll();
  foreach($itRows as $it){ $itemsBySale[$it['sale_id']][]=$it; }
}
?>
<!DOCTYPE html><html lang="id"><head><title>History • <?=e(APP_NAME)?></title><?php include __DIR__.'/../components/head.php'; ?></head>
<body class="min-h-screen bg-slate-50 flex flex-col"><?php include __DIR__.'/../components/header.php';?>
<div class="border-b bg-white px-4 sm:px-6 lg:px-8 py-2.5 flex gap-2 text-sm"><a href="<?=url('/kasir/index')?>" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-2.5 font-medium text-slate-600 hover:bg-slate-50"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>PENJUALAN <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500">F1</span></a><a href="<?=url('/kasir/cek-harga')?>" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-2.5 font-medium text-slate-600 hover:bg-slate-50"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>CEK HARGA <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500">F3</span></a><a href="<?=url('/kasir/history')?>" class="inline-flex items-center gap-2 rounded-full bg-emerald-600 px-5 py-2.5 text-white font-semibold shadow-sm"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>HISTORY <span class="rounded bg-white/20 px-1.5 py-0.5 text-[10px] font-bold">F4</span></a></div>
<main class="flex w-full flex-1 flex-col gap-6 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
<section class="flex-1 space-y-4">
<h2 class="text-lg font-semibold">History Penjualan</h2>
<form method="GET" class="rounded-2xl border bg-white p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 text-xs">
<input name="q" value="<?=e($q)?>" placeholder="No transaksi / kasir / pelanggan" class="rounded-xl border px-3 py-2 sm:col-span-2 lg:col-span-1">
<input type="date" name="from" value="<?=e($from)?>" class="rounded-xl border px-2 py-2">
<input type="date" name="to" value="<?=e($to)?>" class="rounded-xl border px-2 py-2">
<select name="status" class="rounded-xl border px-2 py-2"><option value="">Semua status</option><option value="completed" <?=$status==='completed'?'selected':''?>>Completed</option><option value="cancelled" <?=$status==='cancelled'?'selected':''?>>Cancelled</option><option value="corrected" <?=$status==='corrected'?'selected':''?>>Corrected</option></select>
<select name="pay" class="rounded-xl border px-2 py-2"><option value="">Semua bayar</option><?php foreach($methods as $m):?><option value="<?=$m?>" <?=$pay===$m?'selected':''?>><?=e($m)?></option><?php endforeach;?></select>
<button class="justify-self-start rounded-xl bg-emerald-600 px-4 py-2 text-white font-medium">Filter</button>
</form>
<div class="rounded-2xl border bg-white p-4 shadow-sm"><div class="overflow-auto rounded-xl border"><table class="min-w-full divide-y text-xs"><thead class="bg-slate-100"><tr><th class="px-3 py-2 text-left">No</th><th class="px-3 py-2 text-left">Tanggal</th><th class="px-3 py-2 text-left">Kasir</th><th class="px-3 py-2 text-left">Pelanggan</th><th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2 text-center">Bayar</th><th class="px-3 py-2 text-center">Status</th><th class="px-3 py-2 text-center">Aksi</th></tr></thead><tbody class="divide-y">
<?php foreach($rows as $r):?>
<tr class="hover:bg-slate-50">
<td class="px-3 py-2 font-mono text-[11px]"><?=e($r['transaction_number'])?></td>
<td class="px-3 py-2"><?=e($r['created_at'])?></td>
<td class="px-3 py-2"><?=e($r['cashier'])?></td>
<td class="px-3 py-2"><?=e($r['customer']??'Umum')?></td>
<td class="px-3 py-2 text-right font-semibold"><?=rupiah($r['grand_total'])?></td>
<td class="px-3 py-2 text-center"><?=e($r['payment_method'])?></td>
<td class="px-3 py-2 text-center"><span class="rounded-full px-2 py-0.5 text-[10px] <?=$r['status']=='completed'?'bg-emerald-50 text-emerald-600 ring-1 ring-emerald-200':($r['status']=='cancelled'?'bg-rose-50 text-rose-600':($r['status']=='corrected'?'bg-sky-50 text-sky-600 ring-1 ring-sky-200':'bg-amber-50 text-amber-600'))?>"><?=e($r['status'])?></span></td>
<td class="px-3 py-2 text-center flex justify-center gap-1">
<button type="button" data-modal-toggle="#detailSale<?=$r['id']?>" title="Detail Penjualan" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:border-emerald-500 hover:bg-emerald-50 hover:text-emerald-600 transition"><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button>
<a href="<?=url('/kasir/cetak-struk')?>?id=<?=$r['id']?>&print=1" target="_blank" title="Cetak Struk" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition"><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg></a>
<?php if(in_array($r['status'],['completed','corrected'],true)):?>
<button type="button" data-modal-toggle="#editItems<?=$r['id']?>" title="Koreksi Barang" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-amber-200 bg-amber-50 text-amber-600 hover:bg-amber-100 transition"><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>
<?php endif;?>
</td>
</tr>
<?php endforeach; if(!$rows) echo '<tr><td colspan="8" class="px-3 py-6 text-center text-slate-400">Tidak ada transaksi</td></tr>';?>
</tbody></table></div>
<div class="mt-3 flex justify-between text-xs text-slate-500"><span><?=$total?> transaksi • hal <?=$page?>/<?=$pages?></span><div class="flex gap-1"><?php for($i=1;$i<=$pages;$i++):?><a href="?q=<?=urlencode($q)?>&from=<?=$from?>&to=<?=$to?>&status=<?=$status?>&pay=<?=$pay?>&page=<?=$i?>" class="rounded-full border px-3 py-1 <?=$i==$page?'bg-emerald-600 text-white':''?>"><?=$i?></a><?php endfor;?></div></div>
</div>
</section></main>

<!-- Modals Detail & Koreksi Penjualan -->
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

<?php if(in_array($r['status'],['completed','corrected'],true)):?>
<!-- Modal Koreksi Barang -->
<div id="editItems<?=$r['id']?>" class="modal-dashboard hidden"><div class="modal-dialog max-w-lg"><div class="modal-content"><div class="flex justify-between items-center mb-3"><h3 class="text-sm font-semibold">Koreksi Barang <?=e($r['transaction_number'])?></h3><button type="button" data-modal-hide="#editItems<?=$r['id']?>" class="btn-close"></button></div>
<form method="POST" class="space-y-3">
<input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
<input type="hidden" name="act" value="koreksi_barang">
<input type="hidden" name="sale_id" value="<?=$r['id']?>">
<p class="text-[11px] text-slate-500">Ubah qty barang atau centang "Hapus" jika salah input produk. Stok gudang akan otomatis disesuaikan dan tercatat di audit log.</p>
<div class="space-y-2 max-h-64 overflow-y-auto border rounded-xl p-2 bg-slate-50">
<?php foreach($itemsBySale[$r['id']] ?? [] as $it):?>
<div class="flex items-center gap-2 rounded-lg bg-white p-2 border border-slate-200 text-xs">
<input type="hidden" name="item_id[]" value="<?=$it['id']?>">
<div class="flex-1 min-w-0"><p class="font-medium truncate"><?=e($it['product_name'])?></p><p class="text-[10px] text-slate-400">Harga: <?=rupiah($it['price'])?></p></div>
<div class="flex items-center gap-1"><span class="text-[10px] text-slate-400">Qty:</span><input type="number" name="qty[]" value="<?=$it['qty']?>" min="1" class="w-14 rounded-lg border px-2 py-1 text-center text-xs"></div>
<label class="flex items-center gap-1 text-[11px] text-rose-600 cursor-pointer ml-1"><input type="checkbox" name="delete_item[<?=$it['id']?>]" value="1" class="rounded border-rose-300"> Hapus</label>
</div>
<?php endforeach;?>
</div>
<div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-2">
<div class="flex items-center justify-between mb-2"><p class="text-[11px] font-semibold text-emerald-700">Tambah Barang</p><button type="button" onclick="addNewItem<?=$r['id']?>()" class="rounded-full border border-emerald-300 bg-white px-3 py-1 text-[11px] text-emerald-700 hover:bg-emerald-50">+ Baris</button></div>
<div id="newItems<?=$r['id']?>" class="space-y-2"></div>
</div>
<div><label class="block text-[11px] font-medium text-slate-600 mb-1">Alasan Koreksi <span class="text-rose-500">*</span></label><textarea name="reason" required placeholder="Contoh: Salah input produk / kelebihan 1 pcs" class="w-full rounded-xl border px-3 py-2 text-xs" rows="2"></textarea></div>
<div class="flex gap-2"><button type="submit" class="flex-1 rounded-xl bg-emerald-600 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700">Simpan Koreksi</button><button type="button" data-modal-hide="#editItems<?=$r['id']?>" class="rounded-xl border px-4 py-2 text-xs text-slate-600 hover:bg-slate-100">Batal</button></div>
</form></div></div></div>
<?php endif;?>
<?php endforeach;?>
<script>
const HIST_PRODUCTS=<?=json_encode($allProducts, JSON_UNESCAPED_UNICODE)?>;
function histProductOptions(){
  return HIST_PRODUCTS.map(p=>'<option value="'+p.id+'">'+p.sku+' — '+p.name+' (Rp '+Number(p.selling_price).toLocaleString('id-ID')+' • stok '+p.stock+')</option>').join('');
}
<?php foreach($rows as $r): if(!in_array($r['status'],['completed','corrected'],true)) continue;?>
function addNewItem<?=$r['id']?>(){
  const wrap=document.getElementById('newItems<?=$r['id']?>');
  const d=document.createElement('div');
  d.className='flex items-center gap-2 rounded-lg bg-white p-2 border border-emerald-200 text-xs';
  d.innerHTML='<select name="new_product_id[]" class="flex-1 min-w-0 rounded-lg border px-2 py-1 text-xs">'+histProductOptions()+'</select><div class="flex items-center gap-1"><span class="text-[10px] text-slate-400">Qty:</span><input type="number" name="new_qty[]" value="1" min="1" class="w-14 rounded-lg border px-2 py-1 text-center text-xs"></div><button type="button" class="text-rose-500 px-1" onclick="this.parentElement.remove()">×</button>';
  wrap.appendChild(d);
}
<?php endforeach;?>
document.addEventListener('keydown',e=>{
 if(e.key==='F1'){ e.preventDefault(); window.location.href='<?=url('/kasir/index')?>'; }
 if(e.key==='F3'){ e.preventDefault(); window.location.href='<?=url('/kasir/cek-harga')?>'; }
 if(e.key==='F4'){ e.preventDefault(); }
});
</script>
<?php include __DIR__.'/../components/footer.php';?></body></html>
