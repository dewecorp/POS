<?php
require_once __DIR__.'/../../config/app.php';
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../core/auth.php';
require_once __DIR__.'/../../core/helper.php';
require_role(['admin','manager','owner']);
$pdo=db();
$from=$_GET['from']??date('Y-m-01'); $to=$_GET['to']??date('Y-m-d'); $kasir=$_GET['kasir']??''; $cat=$_GET['cat']??''; $pay=$_GET['pay']??'';
$exp=isset($_GET['export']);
$printView=isset($_GET['print']);
$where="WHERE s.status='completed' AND DATE(s.created_at) BETWEEN ? AND ?"; $par=[$from,$to];
if($kasir!==''){ $where.=" AND s.cashier_id=?"; $par[]=(int)$kasir; }
if($pay!==''){ $where.=" AND s.payment_method=?"; $par[]=$pay; }
$catJoin=""; if($cat!==''){ $catJoin=" JOIN sale_items si2 ON si2.sale_id=s.id JOIN products p2 ON p2.id=si2.product_id AND p2.category_id=". (int)$cat; $where.=" AND p2.category_id=". (int)$cat; }
$sum=$pdo->prepare("SELECT COUNT(*) cnt, COALESCE(SUM(s.grand_total),0) total, COALESCE(SUM(s.discount_amount),0) disc, COALESCE(SUM(s.tax_amount),0) tax FROM sales s $catJoin $where"); $sum->execute($par); $sum=$sum->fetch();
$ret=$pdo->prepare("SELECT COALESCE(SUM(total_refund),0) FROM returns WHERE DATE(created_at) BETWEEN ? AND ?"); $ret->execute([$from,$to]); $ret=(int)$ret->fetchColumn();
$hpp=$pdo->prepare("SELECT COALESCE(SUM(si.qty * p.purchase_price),0) FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id $catJoin WHERE s.status='completed' AND DATE(s.created_at) BETWEEN ? AND ?" . ($kasir?" AND s.cashier_id=".(int)$kasir:"") . ($pay?" AND s.payment_method='". $pay ."'":"")); $hpp->execute([$from,$to]); $hpp=(int)$hpp->fetchColumn();
$bersih=(int)$sum['total'] - $ret;
$laba=$bersih - $hpp;

$perW="";
if($kasir!==''){ $perW.=" AND s.cashier_id=".(int)$kasir; }
if($pay!==''){ $perW.=" AND s.payment_method='". addslashes($pay) ."'"; }
$perProd=$pdo->query("SELECT p.name, SUM(si.qty) qty, SUM(si.subtotal) total FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id WHERE s.status='completed' AND DATE(s.created_at) BETWEEN '$from' AND '$to' $perW GROUP BY p.id ORDER BY qty DESC LIMIT 10")->fetchAll();
$perCat=$pdo->query("SELECT c.name, SUM(si.qty) qty, SUM(si.subtotal) total FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id JOIN product_categories c ON c.id=p.category_id WHERE s.status='completed' AND DATE(s.created_at) BETWEEN '$from' AND '$to' $perW GROUP BY c.id ORDER BY total DESC")->fetchAll();
$perPay=$pdo->query("SELECT payment_method, COUNT(*) cnt, SUM(grand_total) total FROM sales WHERE status='completed' AND DATE(created_at) BETWEEN '$from' AND '$to' $perW GROUP BY payment_method")->fetchAll();
$perDay=$pdo->query("SELECT DATE(created_at) d, COUNT(*) cnt, SUM(grand_total) total FROM sales WHERE status='completed' AND DATE(created_at) BETWEEN '$from' AND '$to' $perW GROUP BY DATE(created_at) ORDER BY d")->fetchAll();

$saleList=$pdo->query("SELECT s.id, s.transaction_number, s.created_at, s.grand_total, s.payment_method, u.name as cashier_name, c.name as customer_name FROM sales s JOIN users u ON u.id=s.cashier_id LEFT JOIN customers c ON c.id=s.customer_id WHERE s.status='completed' AND DATE(s.created_at) BETWEEN '$from' AND '$to' $perW ORDER BY s.id DESC")->fetchAll();

$users=$pdo->query("SELECT id,name FROM users WHERE role IN ('admin','kasir','manager') ORDER BY name")->fetchAll();
$cats=$pdo->query("SELECT id,name FROM product_categories ORDER BY name")->fetchAll();
$store=$pdo->query("SELECT * FROM stores LIMIT 1")->fetch() ?: ['name'=>APP_NAME,'address'=>'','phone'=>''];

if($exp){
 header('Content-Type: text/csv; charset=utf-8');
 header('Content-Disposition: attachment; filename="laporan_penjualan_'.$from.'_'.$to.'.csv"');
 $out=fopen('php://output','w');
 fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM untuk Excel
 fputcsv($out,['LAPORAN PENJUALAN']);
 fputcsv($out,['Toko', $store['name']]);
 fputcsv($out,['Periode', $from.' s/d '.$to]);
 fputcsv($out,[]);
 fputcsv($out,['RINGKASAN']);
 fputcsv($out,['Total Transaksi', $sum['cnt']]);
 fputcsv($out,['Total Penjualan (Kotor)', $sum['total']]);
 fputcsv($out,['Diskon', $sum['disc']]);
 fputcsv($out,['Retur', $ret]);
 fputcsv($out,['Penjualan Bersih', $bersih]);
 fputcsv($out,['Total HPP', $hpp]);
 fputcsv($out,['Laba Kotor', $laba]);
 fputcsv($out,[]);
 fputcsv($out,['PENJUALAN PER HARI']);
 fputcsv($out,['Tanggal','Jumlah Transaksi','Total Omset']);
 foreach($perDay as $d) fputcsv($out,[$d['d'],$d['cnt'],$d['total']]);
 fputcsv($out,[]);
 fputcsv($out,['TOP 10 PRODUK TERLARIS']);
 fputcsv($out,['Nama Produk','Qty Terjual','Total Penjualan']);
 foreach($perProd as $p) fputcsv($out,[$p['name'],$p['qty'],$p['total']]);
 fputcsv($out,[]);
 fputcsv($out,['DAFTAR TRANSAKSI']);
 fputcsv($out,['No Transaksi','Tanggal','Kasir','Pelanggan','Metode Bayar','Total']);
 foreach($saleList as $sl) fputcsv($out,[$sl['transaction_number'],$sl['created_at'],$sl['cashier_name'],$sl['customer_name']?:'Umum',$sl['payment_method'],$sl['grand_total']]);
 exit;
}

if($printView): ?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Cetak Laporan Penjualan (<?=$from?> - <?=$to?>)</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
@media print{
  .no-print{display:none !important}
  @page{size:A4 portrait;margin:10mm}
  body{margin:0;padding:0;background:#fff}
}
</style>
</head><body class="bg-slate-50 p-6 text-slate-800 font-sans">
<div class="max-w-4xl mx-auto bg-white p-8 rounded-xl shadow-sm border border-slate-200">
  <div class="flex justify-between items-start border-b pb-4 mb-6">
    <div>
      <h1 class="text-2xl font-bold uppercase tracking-wider text-slate-900"><?=e($store['name'])?></h1>
      <p class="text-xs text-slate-500 mt-1"><?=e($store['address'])?> <?=!empty($store['phone'])?'| Telp: '.e($store['phone']):''?></p>
      <h2 class="text-sm font-semibold text-emerald-700 mt-3">LAPORAN PENJUALAN & LABA</h2>
    </div>
    <div class="text-right text-xs">
      <p><span class="font-semibold text-slate-600">Periode:</span> <?=date('d/m/Y',strtotime($from))?> - <?=date('d/m/Y',strtotime($to))?></p>
      <p><span class="font-semibold text-slate-600">Dicetak:</span> <?=date('d/m/Y H:i')?></p>
      <p><span class="font-semibold text-slate-600">Oleh:</span> <?=e(current_user()['name'])?></p>
    </div>
  </div>

  <div class="no-print mb-6 flex gap-2">
    <button onclick="window.print()" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-xs font-semibold hover:bg-emerald-700 transition">Print / Simpan PDF</button>
    <a href="index.php?from=<?=$from?>&to=<?=$to?>" class="px-4 py-2 border rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">Kembali</a>
  </div>

  <div class="grid grid-cols-3 gap-3 mb-6 text-xs">
    <div class="border rounded-lg p-3 bg-slate-50">
      <span class="text-slate-500">Total Transaksi</span>
      <div class="text-lg font-bold text-slate-900"><?=$sum['cnt']?></div>
    </div>
    <div class="border rounded-lg p-3 bg-slate-50">
      <span class="text-slate-500">Penjualan Bersih</span>
      <div class="text-lg font-bold text-emerald-700"><?=rupiah($bersih)?></div>
    </div>
    <div class="border rounded-lg p-3 bg-slate-50">
      <span class="text-slate-500">Laba Kotor</span>
      <div class="text-lg font-bold text-emerald-700"><?=rupiah($laba)?></div>
    </div>
  </div>

  <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Ringkasan Keuangan</h3>
  <table class="w-full text-xs border mb-6">
    <tr class="border-b"><td class="p-2 font-medium bg-slate-50 w-1/3">Total Omset Kotor</td><td class="p-2 font-semibold text-right"><?=rupiah($sum['total'])?></td></tr>
    <tr class="border-b"><td class="p-2 font-medium bg-slate-50">Diskon Diberikan</td><td class="p-2 font-semibold text-right text-rose-600">-<?=rupiah($sum['disc'])?></td></tr>
    <tr class="border-b"><td class="p-2 font-medium bg-slate-50">Total Retur Penjualan</td><td class="p-2 font-semibold text-right text-rose-600">-<?=rupiah($ret)?></td></tr>
    <tr class="border-b"><td class="p-2 font-medium bg-slate-50">Total Beban Pokok (HPP)</td><td class="p-2 font-semibold text-right text-amber-700"><?=rupiah($hpp)?></td></tr>
    <tr class="border-b bg-emerald-50"><td class="p-2 font-bold text-emerald-900">Estimasi Laba Kotor</td><td class="p-2 font-bold text-emerald-800 text-right"><?=rupiah($laba)?></td></tr>
  </table>

  <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Daftar Transaksi (Total <?=$sum['cnt']?>)</h3>
  <table class="w-full text-[11px] border mb-6">
    <thead class="bg-slate-100 border-b">
      <tr>
        <th class="p-2 text-left">No Transaksi</th>
        <th class="p-2 text-left">Waktu</th>
        <th class="p-2 text-left">Kasir</th>
        <th class="p-2 text-left">Pelanggan</th>
        <th class="p-2 text-center">Bayar</th>
        <th class="p-2 text-right">Total</th>
      </tr>
    </thead>
    <tbody class="divide-y">
      <?php foreach($saleList as $sl): ?>
      <tr>
        <td class="p-2 font-mono font-medium"><?=e($sl['transaction_number'])?></td>
        <td class="p-2"><?=date('d/m H:i',strtotime($sl['created_at']))?></td>
        <td class="p-2"><?=e($sl['cashier_name'])?></td>
        <td class="p-2"><?=e($sl['customer_name']?:'Umum')?></td>
        <td class="p-2 text-center uppercase"><?=e($sl['payment_method'])?></td>
        <td class="p-2 text-right font-medium"><?=rupiah($sl['grand_total'])?></td>
      </tr>
      <?php endforeach; if(!$saleList): ?>
      <tr><td colspan="6" class="p-4 text-center text-slate-400">Tidak ada transaksi pada periode ini</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="grid grid-cols-2 gap-4 mt-6">
    <div>
      <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Top 5 Produk Terlaris</h3>
      <table class="w-full text-[11px] border">
        <thead class="bg-slate-50 border-b">
          <tr><th class="p-1.5 text-left">Produk</th><th class="p-1.5 text-center">Qty</th><th class="p-1.5 text-right">Total</th></tr>
        </thead>
        <tbody class="divide-y">
          <?php foreach(array_slice($perProd,0,5) as $p): ?>
          <tr><td class="p-1.5"><?=e($p['name'])?></td><td class="p-1.5 text-center"><?=$p['qty']?></td><td class="p-1.5 text-right"><?=rupiah($p['total'])?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div>
      <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Metode Pembayaran</h3>
      <table class="w-full text-[11px] border">
        <thead class="bg-slate-50 border-b">
          <tr><th class="p-1.5 text-left">Metode</th><th class="p-1.5 text-center">Trx</th><th class="p-1.5 text-right">Total</th></tr>
        </thead>
        <tbody class="divide-y">
          <?php foreach($perPay as $p): ?>
          <tr><td class="p-1.5 uppercase"><?=e($p['payment_method'])?></td><td class="p-1.5 text-center"><?=$p['cnt']?></td><td class="p-1.5 text-right"><?=rupiah($p['total'])?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body></html>
<?php exit; endif; ?>
<!DOCTYPE html><html lang="id"><head><title>Laporan • <?=e(APP_NAME)?></title><?php include __DIR__.'/../../components/head.php'; ?></head>
<body class="min-h-screen bg-slate-50 flex flex-col"><?php include __DIR__.'/../../components/header.php';?>
<main class="flex w-full flex-1 flex-col gap-6 px-4 pb-10 pt-5 sm:px-6 lg:px-8 lg:flex-row lg:items-start"><?php include __DIR__.'/../../components/sidebar_admin.php';?>
<section class="order-2 flex-1 space-y-4">
<nav class="text-[11px] text-slate-500">Dashboard / Laporan</nav>
<h2 class="text-lg font-semibold">Laporan Penjualan & Laba</h2>
<form method="GET" class="rounded-2xl border bg-white p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 text-xs">
<input type="date" name="from" value="<?=e($from)?>" class="rounded-xl border px-3 py-2">
<input type="date" name="to" value="<?=e($to)?>" class="rounded-xl border px-3 py-2">
<select name="kasir" class="rounded-xl border px-3 py-2"><option value="">Semua kasir</option><?php foreach($users as $u):?><option value="<?=$u['id']?>" <?=$kasir==$u['id']?'selected':''?>><?=e($u['name'])?></option><?php endforeach;?></select>
<select name="cat" class="rounded-xl border px-3 py-2"><option value="">Semua kategori</option><?php foreach($cats as $c):?><option value="<?=$c['id']?>" <?=$cat==$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select>
<select name="pay" class="rounded-xl border px-3 py-2"><option value="">Semua bayar</option><option value="tunai" <?=$pay=='tunai'?'selected':''?>>Tunai</option><option value="transfer" <?=$pay=='transfer'?'selected':''?>>Transfer</option><option value="qris" <?=$pay=='qris'?'selected':''?>>QRIS</option></select>
<button type="submit" class="justify-self-start rounded-xl bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700 transition">Filter</button>
<button type="submit" name="export" value="1" class="rounded-xl border border-slate-300 bg-white px-3 py-2 font-medium text-slate-700 hover:bg-slate-50 transition inline-flex items-center justify-center gap-1.5">
  <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
  Export Excel
</button>
<a href="?from=<?=urlencode($from)?>&to=<?=urlencode($to)?>&kasir=<?=urlencode($kasir)?>&cat=<?=urlencode($cat)?>&pay=<?=urlencode($pay)?>&print=1" target="_blank" class="rounded-xl border border-slate-300 bg-white px-3 py-2 font-medium text-slate-700 hover:bg-slate-50 transition inline-flex items-center gap-1.5">
  <svg class="h-4 w-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
  Cetak / PDF
</a>
</form>
<div class="grid sm:grid-cols-3 xl:grid-cols-6 gap-3 text-xs">
<div class="rounded-2xl border bg-white p-3"><p class="text-slate-500">Transaksi</p><p class="text-lg font-bold"><?=$sum['cnt']?></p></div>
<div class="rounded-2xl border bg-white p-3"><p class="text-slate-500">Penjualan</p><p class="text-lg font-bold"><?=rupiah($sum['total'])?></p></div>
<div class="rounded-2xl border bg-white p-3"><p class="text-slate-500">Diskon</p><p class="text-lg font-bold"><?=rupiah($sum['disc'])?></p></div>
<div class="rounded-2xl border bg-white p-3"><p class="text-slate-500">Retur</p><p class="text-lg font-bold text-rose-600"><?=rupiah($ret)?></p></div>
<div class="rounded-2xl border bg-white p-3"><p class="text-slate-500">Bersih</p><p class="text-lg font-bold text-emerald-600"><?=rupiah($bersih)?></p></div>
<div class="rounded-2xl border bg-white p-3"><p class="text-slate-500">Laba Kotor</p><p class="text-lg font-bold text-emerald-600"><?=rupiah($laba)?></p><p class="text-[10px] text-slate-400">Bersih - HPP (<?=rupiah($hpp)?>)</p></div>
</div>
<div class="grid lg:grid-cols-2 gap-4">
<div class="rounded-2xl border bg-white p-4"><p class="text-xs font-semibold mb-2">Produk Terlaris</p><table class="min-w-full text-xs"><thead class="bg-slate-100"><tr><th class="px-2 py-1 text-left">Produk</th><th class="px-2 py-1 text-right">Qty</th><th class="px-2 py-1 text-right">Total</th></tr></thead><tbody class="divide-y"><?php foreach($perProd as $r):?><tr><td class="px-2 py-1"><?=e($r['name'])?></td><td class="px-2 py-1 text-right"><?=$r['qty']?></td><td class="px-2 py-1 text-right"><?=rupiah($r['total'])?></td></tr><?php endforeach; if(!$perProd) echo '<tr><td colspan="3" class="px-2 py-4 text-center text-slate-400">Tidak ada data</td></tr>';?></tbody></table></div>
<div class="rounded-2xl border bg-white p-4"><p class="text-xs font-semibold mb-2">Penjualan per Kategori</p><table class="min-w-full text-xs"><thead class="bg-slate-100"><tr><th class="px-2 py-1 text-left">Kategori</th><th class="px-2 py-1 text-right">Qty</th><th class="px-2 py-1 text-right">Total</th></tr></thead><tbody class="divide-y"><?php foreach($perCat as $r):?><tr><td class="px-2 py-1"><?=e($r['name'])?></td><td class="px-2 py-1 text-right"><?=$r['qty']?></td><td class="px-2 py-1 text-right"><?=rupiah($r['total'])?></td></tr><?php endforeach; if(!$perCat) echo '<tr><td colspan="3" class="px-2 py-4 text-center text-slate-400">Tidak ada data</td></tr>';?></tbody></table></div>
</div>
<div class="rounded-2xl border bg-white p-4"><p class="text-xs font-semibold mb-2">Grafik Harian</p><canvas id="chartDay" height="80"></canvas></div>
<div class="rounded-2xl border bg-white p-4"><p class="text-xs font-semibold mb-2">Per Metode Bayar</p><div class="flex gap-2 flex-wrap"><?php foreach($perPay as $p):?><span class="rounded-full bg-slate-100 px-3 py-1 text-xs"><?=e($p['payment_method'])?>: <?=$p['cnt']?> (<?=rupiah($p['total'])?>)</span><?php endforeach;?></div></div>
</section></main>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const labels=<?=json_encode(array_column($perDay,'d'))?>, vals=<?=json_encode(array_map(fn($x)=>(int)$x['total'],$perDay))?>;
if(document.getElementById('chartDay')) new Chart(document.getElementById('chartDay'),{type:'bar',data:{labels,datasets:[{data:vals,backgroundColor:'#10b981'}]},options:{plugins:{legend:{display:false}},scales:{y:{ticks:{callback:v=>'Rp '+v}}}}});
</script>
<?php include __DIR__.'/../../components/footer.php';?></body></html>
