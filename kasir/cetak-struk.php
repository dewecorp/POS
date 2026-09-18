<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/helper.php';
require_login();
$pdo=db();
$id=(int)($_GET['id']??0);
$stmt=$pdo->prepare("SELECT s.*, u.name as cashier, c.name as customer, st.name as store_name, st.address, st.phone FROM sales s JOIN users u ON u.id=s.cashier_id LEFT JOIN customers c ON c.id=s.customer_id LEFT JOIN stores st ON st.id=1 WHERE s.id=?");
$stmt->execute([$id]); $sale=$stmt->fetch();
if(!$sale) die('Transaksi tidak ditemukan');
$items=$pdo->prepare("SELECT * FROM sale_items WHERE sale_id=?"); $items->execute([$id]); $items=$items->fetchAll();
$store_name=$sale['store_name']??APP_NAME; $size=$_GET['size']??'58'; // 58 or 80
$print=isset($_GET['print']);
$maxWidth=$size==='80'?'max-w-[360px]':'max-w-[280px]';
$pageSize=$size==='80'?'80mm':'58mm';
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Struk <?=e($sale['transaction_number'])?></title><script src="https://cdn.tailwindcss.com"></script>
<style>
@media print{
  .no-print{display:none !important}
  @page{size:<?=$pageSize?> auto;margin:2mm}
  body{margin:0;padding:0;background:#fff}
  .receipt-box{max-width:100% !important;box-shadow:none !important;padding:0 !important;border:none !important}
}
</style>
</head><body class="bg-slate-100 flex flex-col items-center p-4">
<div class="no-print mb-3 flex items-center gap-2 text-xs bg-white p-2 rounded-xl border shadow-sm">
  <span class="text-slate-500 font-medium">Ukuran Kertas:</span>
  <a href="?id=<?=$id?>&size=58<?=$print?'&print=1':''?>" class="px-2.5 py-1 rounded-lg <?=$size==='58'?'bg-emerald-600 text-white font-semibold':'bg-slate-100 text-slate-700 hover:bg-slate-200'?>">58mm</a>
  <a href="?id=<?=$id?>&size=80<?=$print?'&print=1':''?>" class="px-2.5 py-1 rounded-lg <?=$size==='80'?'bg-emerald-600 text-white font-semibold':'bg-slate-100 text-slate-700 hover:bg-slate-200'?>">80mm</a>
</div>
<div class="receipt-box w-full <?=$maxWidth?> bg-white p-4 font-mono text-xs shadow-lg rounded-xl border">
<div class="text-center border-b border-dashed pb-2">
<p class="font-bold text-sm tracking-wide uppercase"><?=e($store_name)?></p><p class="text-[11px] text-slate-600"><?=e($sale['address']??'')?></p><p class="text-[11px]"><?=e($sale['phone']??'')?></p>
</div>
<div class="py-2 text-[11px] space-y-0.5 border-b border-dashed">
<div class="flex justify-between"><span>No: <?=e($sale['transaction_number'])?></span><span><?=date('d/m/y H:i',strtotime($sale['created_at']))?></span></div>
<p>Kasir: <?=e($sale['cashier'])?></p>
<p>Pelanggan: <?=e($sale['customer']??'Umum')?></p>
<p>Metode: <?=strtoupper(e($sale['payment_method']))?></p>
</div>
<table class="w-full text-[11px] my-2"><thead><tr class="border-b"><th class="text-left py-1">Item</th><th class="text-center py-1">Qty</th><th class="text-right py-1">Subtotal</th></tr></thead><tbody class="divide-y divide-dashed">
<?php foreach($items as $it):?><tr>
<td class="py-1 leading-tight"><div class="font-medium"><?=e($it['product_name'])?></div><div class="text-[10px] text-slate-500"><?=rupiah($it['price'])?><?php if($it['discount_amount']>0):?> <span class="text-rose-500">(-<?=rupiah($it['discount_amount'])?>)</span><?php endif;?></div></td>
<td class="text-center align-top py-1"><?=$it['qty']?></td>
<td class="text-right align-top py-1 font-medium"><?=rupiah($it['subtotal'])?></td>
</tr><?php endforeach;?>
</tbody></table>
<div class="border-t border-dashed pt-2 space-y-0.5 text-[11px]">
<div class="flex justify-between"><span>Subtotal</span><span><?=rupiah($sale['subtotal'])?></span></div>
<?php if($sale['discount_amount']>0):?><div class="flex justify-between text-rose-600"><span>Diskon</span><span>-<?=rupiah($sale['discount_amount'])?></span></div><?php endif;?>
<?php if($sale['tax_amount']>0):?><div class="flex justify-between"><span>Pajak (<?=e($sale['tax_percent'])?>%)</span><span><?=rupiah($sale['tax_amount'])?></span></div><?php endif;?>
<?php if($sale['additional_cost']>0):?><div class="flex justify-between"><span>Biaya Lain</span><span><?=rupiah($sale['additional_cost'])?></span></div><?php endif;?>
<div class="flex justify-between font-bold text-sm border-t border-dashed pt-1"><span>Total</span><span><?=rupiah($sale['grand_total'])?></span></div>
<div class="flex justify-between"><span>Bayar</span><span><?=rupiah($sale['paid_amount'])?></span></div>
<div class="flex justify-between"><span>Kembalian</span><span><?=rupiah($sale['change_amount'])?></span></div>
</div>
<p class="text-center mt-3 border-t border-dashed pt-2 text-[11px]"><?=e($pdo->query("SELECT value FROM settings WHERE `key`='receipt_footer'")->fetchColumn() ?: 'Terima kasih atas kunjungan Anda')?></p>
<p class="text-center text-[10px] text-slate-400">Status: <?=strtoupper(e($sale['status']))?></p>
<div class="no-print mt-4 flex gap-2">
  <button onclick="window.print()" class="flex-1 rounded-xl bg-emerald-600 py-2 font-semibold text-white text-xs hover:bg-emerald-700 transition">Cetak</button>
  <a href="<?=APP_URL?>/kasir/history.php" class="flex-1 rounded-xl border border-slate-300 py-2 text-center text-xs font-semibold hover:bg-slate-100 transition">Kembali</a>
</div>
</div>
<?php if($print):?><script>window.print()</script><?php endif;?>
</body></html>
