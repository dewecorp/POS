<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/helper.php';
require_once __DIR__.'/../core/audit.php';
require_role(['admin','manager','owner']);
$pdo=db();
$today=date('Y-m-d');
$st=$pdo->query("SELECT COUNT(*) c, COALESCE(SUM(grand_total),0) s FROM sales WHERE DATE(created_at)='$today' AND status='completed'")->fetch();
$totalToday=(int)$st['s']; $trxToday=(int)$st['c'];
$soldToday=(int)($pdo->query("SELECT COALESCE(SUM(qty),0) FROM sale_items JOIN sales ON sales.id=sale_items.sale_id WHERE DATE(sales.created_at)='$today' AND sales.status='completed'")->fetchColumn() ?:0);
$discToday=(int)($pdo->query("SELECT COALESCE(SUM(discount_amount),0) FROM sales WHERE DATE(created_at)='$today' AND status='completed'")->fetchColumn() ?:0);
$retToday=(int)($pdo->query("SELECT COALESCE(SUM(total_refund),0) FROM returns WHERE DATE(created_at)='$today'")->fetchColumn() ?:0);
$low=$pdo->query("SELECT COUNT(*) FROM products WHERE stock <= min_stock AND is_active=1")->fetchColumn();
$out=$pdo->query("SELECT COUNT(*) FROM products WHERE stock=0 AND is_active=1")->fetchColumn();
$cashToday=(int)($pdo->query("SELECT COALESCE(SUM(amount),0) FROM cash_transactions WHERE type='in' AND DATE(created_at)='$today'")->fetchColumn() ?:0);
$expToday=(int)($pdo->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date='$today'")->fetchColumn() ?:0);
$recent=$pdo->query("SELECT s.transaction_number,s.grand_total,s.status,u.name as cashier FROM sales s JOIN users u ON u.id=s.cashier_id ORDER BY s.id DESC LIMIT 5")->fetchAll();
$top=$pdo->query("SELECT p.name, SUM(si.qty) q, SUM(si.subtotal) t FROM sale_items si JOIN products p ON p.id=si.product_id JOIN sales s ON s.id=si.sale_id WHERE s.status='completed' GROUP BY p.id ORDER BY q DESC LIMIT 5")->fetchAll();
$sales7=$pdo->query("SELECT DATE(created_at) d, COALESCE(SUM(grand_total),0) t FROM sales WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status='completed' GROUP BY DATE(created_at) ORDER BY d")->fetchAll();
$labels=array_column($sales7,'d'); $vals=array_column($sales7,'t');

// Data aktivitas (audit log) — hanya 24 jam terakhir, lebih lama dihapus
$pdo->exec("DELETE FROM audit_logs WHERE created_at < (NOW() - INTERVAL 24 HOUR)");
$actCount=(int)$pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$activities=$pdo->query("SELECT a.*, u.name as user FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 50")->fetchAll();

function act_meta($action){
    $a=strtoupper($action);
    if(strpos($a,'LOGIN_FAILED')!==false) return ['rose','<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-2.99l-6.93-12a2 2 0 00-3.48 0l-6.93 12A2 2 0 005.07 19z"/>'];
    if(strpos($a,'LOGIN')!==false) return ['emerald','<path stroke-linecap="round" stroke-linejoin="round" d="M11 16l4-4m0 0l-4-4m4 4H3m8 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h2a3 3 0 013 3v1"/>'];
    if(strpos($a,'LOGOUT')!==false) return ['slate','<path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>'];
    if(strpos($a,'DELETE')!==false) return ['rose','<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>'];
    if(strpos($a,'UPDATE')!==false || strpos($a,'EDIT')!==false || strpos($a,'CORRECT')!==false) return ['sky','<path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>'];
    if(strpos($a,'CANCEL')!==false || strpos($a,'VOID')!==false) return ['amber','<path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>'];
    if(strpos($a,'RETURN')!==false) return ['amber','<path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>'];
    if(strpos($a,'STOCK')!==false || strpos($a,'OPNAME')!==false) return ['amber','<path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>'];
    if(strpos($a,'CASH')!==false || strpos($a,'EXPENSE')!==false || strpos($a,'SHIFT')!==false) return ['sky','<path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>'];
    if(strpos($a,'PURCHASE')!==false) return ['emerald','<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>'];
    if(strpos($a,'SALE')!==false) return ['emerald','<path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>'];
    if(strpos($a,'CREATE')!==false) return ['emerald','<path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>'];
    return ['slate','<path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'];
}
$actColors=[
    'emerald'=>['dot'=>'bg-emerald-500 border-emerald-300 shadow-[0_0_0_4px_rgba(16,185,129,0.25)]','time'=>'text-emerald-600'],
    'sky'=>['dot'=>'bg-sky-500 border-sky-300 shadow-[0_0_0_4px_rgba(56,189,248,0.25)]','time'=>'text-sky-600'],
    'amber'=>['dot'=>'bg-amber-400 border-amber-300 shadow-[0_0_0_4px_rgba(251,191,36,0.25)]','time'=>'text-amber-600'],
    'rose'=>['dot'=>'bg-rose-500 border-rose-300 shadow-[0_0_0_4px_rgba(248,113,113,0.25)]','time'=>'text-rose-600'],
    'slate'=>['dot'=>'bg-slate-400 border-slate-300 shadow-[0_0_0_4px_rgba(148,163,184,0.25)]','time'=>'text-slate-500'],
];
$hariList=['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
$bulanList=['January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April','May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'];
function act_title($action){
    $map=[
        'CREATE_SALE'=>'Penjualan Baru','CANCEL_SALE'=>'Void Penjualan','EDIT_SALE'=>'Koreksi Penjualan','CORRECT_SALE_ITEMS'=>'Koreksi Item Penjualan',
        'LOGIN'=>'Login','LOGOUT'=>'Logout','LOGIN_FAILED'=>'Login Gagal',
        'CREATE_PRODUCT'=>'Tambah Produk','UPDATE_PRODUCT'=>'Ubah Produk','DELETE_PRODUCT'=>'Hapus Produk',
        'CREATE_CATEGORY'=>'Tambah Kategori','UPDATE_CATEGORY'=>'Ubah Kategori','DELETE_CATEGORY'=>'Hapus Kategori',
        'CREATE_SUPPLIER'=>'Tambah Supplier','UPDATE_SUPPLIER'=>'Ubah Supplier','DELETE_SUPPLIER'=>'Hapus Supplier',
        'CREATE_CUSTOMER'=>'Tambah Pelanggan','UPDATE_CUSTOMER'=>'Ubah Pelanggan','DELETE_CUSTOMER'=>'Hapus Pelanggan',
        'CREATE_USER'=>'Tambah Pengguna','UPDATE_USER'=>'Ubah Pengguna','DELETE_USER'=>'Hapus Pengguna',
        'CREATE_PURCHASE'=>'Pembelian Baru','RETURN_SALE'=>'Retur Penjualan',
        'UPDATE_STOCK'=>'Penyesuaian Stok','STOCK_OPNAME'=>'Stock Opname',
        'CREATE_EXPENSE'=>'Pengeluaran Baru','OPEN_SHIFT'=>'Buka Shift','CLOSE_SHIFT'=>'Tutup Shift',
        'UPDATE_SETTINGS'=>'Ubah Pengaturan','CASH_IN'=>'Kas Masuk','CASH_OUT'=>'Kas Keluar',
    ];
    if(isset($map[$action])) return $map[$action];
    return ucwords(strtolower(str_replace('_',' ',$action)));
}
?>
<!DOCTYPE html><html lang="id"><head><title>Dashboard • <?=e(APP_NAME)?></title><?php include __DIR__.'/../components/head.php'; ?></head>
<body class="min-h-screen bg-slate-50 text-slate-900 flex flex-col">
<?php include __DIR__.'/../components/header.php'; ?>
<main class="flex w-full flex-1 flex-col gap-6 px-4 pb-10 pt-5 sm:px-6 lg:px-8 lg:flex-row lg:items-start">
<?php include __DIR__.'/../components/sidebar_admin.php'; ?>
<section class="order-2 flex-1 space-y-5">
<nav class="text-[11px] text-slate-500">Dashboard</nav>
<div class="flex justify-end"><a href="<?=url('/kasir/index')?>" class="rounded-full bg-emerald-600 px-4 py-1.5 text-xs font-medium text-white shadow-sm">Buka POS</a></div>
<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
<article class="summary-card-primary rounded-2xl p-4"><div class="flex items-start justify-between"><div><p class="text-[11px] uppercase tracking-wide text-emerald-700 font-semibold">Penjualan hari ini</p><p class="mt-1 text-2xl font-bold text-slate-900"><?=rupiah($totalToday)?></p><p class="text-[11px] text-slate-500"><?=$trxToday?> transaksi • <?=$soldToday?> item</p></div><span class="icon-box-success"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm0 0V6m0 12v-2M8 12H6m12 0h-2"/></svg></span></div></article>
<article class="summary-card-danger rounded-2xl p-4"><div class="flex items-start justify-between"><div><p class="text-[11px] uppercase tracking-wide text-rose-600 font-semibold">Diskon & Retur</p><p class="mt-1 text-2xl font-bold text-slate-900"><?=rupiah($discToday)?></p><p class="text-[11px] text-rose-600">Retur: <?=rupiah($retToday)?></p></div><span class="icon-box-danger"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3-3m-3 3l3 3m-6 3a2 2 0 002 2h10a2 2 0 002-2v-5"/></svg></span></div></article>
<article class="summary-card-info rounded-2xl p-4"><div class="flex items-start justify-between"><div><p class="text-[11px] uppercase tracking-wide text-sky-600 font-semibold">Laba kotor hari ini</p><p class="mt-1 text-2xl font-bold text-slate-900"><?=rupiah(max(0,$totalToday-$pdo->query("SELECT COALESCE(SUM(si.qty*p.purchase_price),0) FROM sale_items si JOIN products p ON p.id=si.product_id JOIN sales s ON s.id=si.sale_id WHERE DATE(s.created_at)='$today' AND s.status='completed'")->fetchColumn()))?></p><p class="text-[11px] text-slate-500">Penjualan - HPP</p></div><span class="icon-box-info"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg></span></div></article>
<article class="summary-card-warning rounded-2xl p-4"><div class="flex items-start justify-between"><div><p class="text-[11px] uppercase tracking-wide text-amber-700 font-semibold">Stok</p><p class="mt-1 text-2xl font-bold text-slate-900"><?=$low?> menipis</p><p class="text-[11px] text-amber-600"><?=$out?> habis</p></div><span class="icon-box-warning"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5 13a4 4L19 7"/></svg></span></div></article>
</div>
<div class="grid gap-4 lg:grid-cols-3">
<section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2"><div class="flex items-center justify-between mb-3"><p class="text-xs font-semibold">Penjualan 7 hari</p></div><canvas id="chart7" height="120"></canvas></section>
<section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-semibold mb-3">Transaksi terbaru</p><div class="space-y-2"><?php foreach($recent as $r):?><div class="flex justify-between rounded-xl bg-slate-50 px-3 py-2 text-xs"><span><?=e($r['transaction_number'])?> — <?=e($r['cashier'])?></span><span class="font-semibold"><?=rupiah($r['grand_total'])?></span></div><?php endforeach; if(!$recent) echo '<p class="text-xs text-slate-400">Belum ada transaksi</p>';?></div>
<p class="text-xs font-semibold mt-4 mb-2">Produk terlaris</p><div class="space-y-1 text-xs"><?php foreach($top as $t):?><div class="flex justify-between"><span><?=e($t['name'])?></span><span><?=$t['q']?> pcs</span></div><?php endforeach; ?></div>
<div class="mt-3 rounded-xl bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-700">⚠ <?=$low?> produk butuh restock</div>
</section>
</div>
<div class="grid gap-4 lg:grid-cols-2">
<section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-semibold mb-2">Kas hari ini</p><div class="flex justify-between text-xs"><span>Masuk</span><span class="font-semibold text-emerald-600"><?=rupiah($cashToday+$totalToday)?></span></div><div class="flex justify-between text-xs"><span>Keluar</span><span class="font-semibold text-rose-600"><?=rupiah($expToday)?></span></div><div class="flex justify-between text-xs font-semibold border-t mt-2 pt-2"><span>Saldo kas</span><span><?=rupiah(($cashToday+$totalToday)-$expToday)?></span></div></section>
<section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-semibold mb-2">Aksi cepat</p><div class="grid grid-cols-2 gap-2 text-xs"><a href="<?=url('/admin/produk/index')?>" class="rounded-xl bg-emerald-50 px-3 py-2 text-center">Kelola Produk</a><a href="<?=url('/admin/pembelian/index')?>" class="rounded-xl bg-emerald-50 px-3 py-2 text-center">Pembelian</a><a href="<?=url('/admin/stok/index')?>" class="rounded-xl bg-amber-50 px-3 py-2 text-center">Stok Opname</a><a href="<?=url('/admin/laporan/index')?>" class="rounded-xl bg-sky-50 px-3 py-2 text-center">Laporan</a></div></section>
</div>
<section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
<div class="mb-3 flex items-center justify-between">
<div>
<p class="text-xs font-semibold">Data Aktivitas</p>
<p class="text-[11px] text-slate-400">Log aktivitas 24 jam terakhir</p>
</div>
<span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold text-emerald-600 ring-1 ring-emerald-500/20">
<span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
<?=$actCount?> aktivitas
</span>
</div>
<?php if($activities): ?>
<div class="max-h-[420px] overflow-y-auto scroll-thin pr-1">
<ol class="relative space-y-3 border-l border-slate-200 pl-3">
<?php foreach($activities as $a): $m=act_meta($a['action']); $c=$actColors[$m[0]]; $ts=strtotime($a['created_at']); ?>
<li class="group relative">
<div class="absolute -left-[9px] mt-1.5 flex h-3 w-3 items-center justify-center rounded-full border <?=$c['dot']?>"></div>
<div class="rounded-xl bg-slate-50 px-3 py-2">
<div class="flex items-center justify-between">
<div class="flex items-center gap-2">
<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0 <?=$c['time']?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><?=$m[1]?></svg>
<span class="text-[11px] font-semibold text-slate-800"><?=e(act_title($a['action']))?></span>
<span class="rounded-full bg-white px-2 py-0.5 text-[9px] font-medium tracking-wide text-slate-500 ring-1 ring-slate-200"><?=e(ucwords(str_replace('_',' ',$a['module'])))?></span>
</div>
<span class="text-[10px] <?=$c['time']?>"><?=time_ago($a['created_at'])?></span>
</div>
<p class="mt-0.5 text-[11px] text-slate-500"><?=e($a['description']?:'-')?></p>
<div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[10px] text-slate-400">
<span><?=e($a['user']??'Sistem')?></span>
<span><?=$hariList[date('l',$ts)]?>, <?=date('j',$ts)?> <?=$bulanList[date('F',$ts)]?> <?=date('Y',$ts)?></span>
<span><?=date('H:i',$ts)?> WIB</span>
</div>
</div>
</li>
<?php endforeach; ?>
</ol>
</div>
<?php else: ?>
<p class="py-6 text-center text-xs text-slate-400">Belum ada aktivitas dalam 24 jam terakhir</p>
<?php endif; ?>
</section>
</div>
</section></main>
<?php include __DIR__.'/../components/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const labels=<?=json_encode($labels)?>, vals=<?=json_encode(array_map('intval',$vals))?>;
if(document.getElementById('chart7')){
 new Chart(document.getElementById('chart7'),{type:'line',data:{labels,datasets:[{data:vals,borderColor:'#10b981',backgroundColor:'rgba(16,185,129,0.15)',fill:true,tension:0.35}]},options:{plugins:{legend:{display:false}},scales:{y:{ticks:{callback:v=>'Rp '+v}}}}});
}
</script>
</body></html>
