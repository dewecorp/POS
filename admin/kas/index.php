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
 $act=$_POST['act']??'';
 if($act==='cash'){
  $type=$_POST['type']=='out'?'out':'in'; $cat=trim($_POST['category']??''); $amt=parse_rupiah($_POST['amount']??0); $desc=trim($_POST['description']??'');
  if($amt<=0){flash_set('error','Nominal harus >0'); redirect($_SERVER['REQUEST_URI']);}
  $pdo->prepare("INSERT INTO cash_transactions (type,category,amount,description,created_by) VALUES (?,?,?,?,?)")->execute([$type,$cat,$amt,$desc, current_user()['id']]);
  audit('CASH_'.$type,'cash',null,"$cat ".rupiah($amt)); flash_set('success','Transaksi kas disimpan'); redirect($_SERVER['REQUEST_URI']);
 }
 if($act==='expense'){
  $cat=trim($_POST['category']??''); $amt=parse_rupiah($_POST['amount']??0); $desc=trim($_POST['description']??''); $date=$_POST['expense_date']??date('Y-m-d');
  if($amt<=0){flash_set('error','Nominal harus >0'); redirect($_SERVER['REQUEST_URI']);}
  $proof=null;
  if(!empty($_FILES['proof']['name'])){
    $ext=strtolower(pathinfo($_FILES['proof']['name'],PATHINFO_EXTENSION)); $allowed=['jpg','jpeg','png','pdf'];
    if(!in_array($ext,$allowed)){flash_set('error','Bukti harus jpg/png/pdf'); redirect($_SERVER['REQUEST_URI']);}
    $fname='proof_'.time().'_'.bin2hex(random_bytes(2)).'.'.$ext; if(!is_dir(__DIR__.'/../../uploads')) mkdir(__DIR__.'/../../uploads',0777,true);
    move_uploaded_file($_FILES['proof']['tmp_name'], __DIR__.'/../../uploads/'.$fname); $proof=$fname;
  }
  $pdo->prepare("INSERT INTO expenses (category,amount,description,proof,expense_date,created_by) VALUES (?,?,?,?,?,?)")->execute([$cat,$amt,$desc,$proof,$date,current_user()['id']]);
  $pdo->prepare("INSERT INTO cash_transactions (type,category,amount,description,created_by) VALUES ('out',?, ?, ?, ?)")->execute([$cat,$amt,$desc,current_user()['id']]);
  audit('CREATE_EXPENSE','cash',null,"Pengeluaran $cat ".rupiah($amt)); flash_set('success','Pengeluaran disimpan'); redirect($_SERVER['REQUEST_URI']);
 }
 if($act==='shift_open'){
  $bal=parse_rupiah($_POST['opening_balance']??0);
  $open=$pdo->prepare("SELECT id FROM cashier_shifts WHERE user_id=? AND status='open'"); $open->execute([current_user()['id']]);
  if($open->fetch()){flash_set('error','Masih ada shift terbuka'); redirect($_SERVER['REQUEST_URI']);}
  $pdo->prepare("INSERT INTO cashier_shifts (user_id,opening_balance) VALUES (?,?)")->execute([current_user()['id'],$bal]);
  audit('OPEN_SHIFT','cash',null,"Buka shift ".rupiah($bal)); flash_set('success','Shift dibuka'); redirect($_SERVER['REQUEST_URI']);
 }
 if($act==='shift_close'){
  $id=(int)($_POST['shift_id']??0); $actual=parse_rupiah($_POST['actual_balance']??0); $notes=trim($_POST['notes']??'');
  $s=$pdo->prepare("SELECT * FROM cashier_shifts WHERE id=? AND user_id=? AND status='open'"); $s->execute([$id,current_user()['id']]); $shift=$s->fetch();
  if(!$shift){flash_set('error','Shift tidak ditemukan'); redirect($_SERVER['REQUEST_URI']);}
  $today=date('Y-m-d');
  $cashSales=(int)$pdo->query("SELECT COALESCE(SUM(grand_total),0) FROM sales WHERE DATE(created_at)='$today' AND status='completed' AND cashier_id=". (int)current_user()['id'] ." AND payment_method='tunai'")->fetchColumn();
  $cashIn=(int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM cash_transactions WHERE type='in' AND DATE(created_at)='$today' AND created_by=". (int)current_user()['id'])->fetchColumn();
  $cashOut=(int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM cash_transactions WHERE type='out' AND DATE(created_at)='$today' AND created_by=". (int)current_user()['id'])->fetchColumn();
  $expected=$shift['opening_balance'] + $cashSales + $cashIn - $cashOut;
  $diff=$actual - $expected;
  $pdo->prepare("UPDATE cashier_shifts SET closing_balance=?, cash_sales=?, cash_in=?, cash_out=?, expected_balance=?, actual_balance=?, difference=?, status='closed', closed_at=NOW(), notes=? WHERE id=?")->execute([$actual,$cashSales,$cashIn,$cashOut,$expected,$actual,$diff,$notes,$id]);
  audit('CLOSE_SHIFT','cash',$id,"Tutup shift selisih ".rupiah($diff)); flash_set('success','Shift ditutup. Selisih '.rupiah($diff)); redirect($_SERVER['REQUEST_URI']);
 }
}
$today=date('Y-m-d');
$cashIn=(int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM cash_transactions WHERE type='in' AND DATE(created_at)='$today'")->fetchColumn();
$cashOut=(int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM cash_transactions WHERE type='out' AND DATE(created_at)='$today'")->fetchColumn();
$expToday=(int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date='$today'")->fetchColumn();
$balance=$cashIn - $cashOut;
$cashRows=$pdo->query("SELECT ct.*, u.name as user FROM cash_transactions ct LEFT JOIN users u ON u.id=ct.created_by ORDER BY ct.id DESC LIMIT 20")->fetchAll();
$expRows=$pdo->query("SELECT e.*, u.name as user FROM expenses e LEFT JOIN users u ON u.id=e.created_by ORDER BY e.id DESC LIMIT 10")->fetchAll();
$myShift=$pdo->prepare("SELECT * FROM cashier_shifts WHERE user_id=? AND status='open' ORDER BY id DESC LIMIT 1"); $myShift->execute([current_user()['id']]); $myShift=$myShift->fetch();
$shifts=$pdo->query("SELECT sh.*, u.name as user FROM cashier_shifts sh JOIN users u ON u.id=sh.user_id ORDER BY sh.id DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html><html lang="id"><head><title>Kas • <?=e((store_info()['name'] ?? APP_NAME))?></title><?php include __DIR__.'/../../components/head.php'; ?></head>
<body class="min-h-screen bg-slate-50 flex flex-col"><?php include __DIR__.'/../../components/header.php';?>
<main class="flex w-full flex-1 flex-col gap-6 px-4 pb-10 pt-5 sm:px-6 lg:px-8 lg:flex-row lg:items-start"><?php include __DIR__.'/../../components/sidebar_admin.php';?>
<section class="order-2 flex-1 space-y-4">
<nav class="text-[11px] text-slate-500">Dashboard / Kas</nav>
<h2 class="text-lg font-semibold">Kas & Pengeluaran</h2>
<div class="grid sm:grid-cols-3 gap-3">
<div class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-xs text-slate-500">Kas Masuk Hari Ini</p><p class="text-lg font-bold text-emerald-600"><?=rupiah($cashIn)?></p></div>
<div class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-xs text-slate-500">Kas Keluar Hari Ini</p><p class="text-lg font-bold text-rose-600"><?=rupiah($cashOut)?></p></div>
<div class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-xs text-slate-500">Saldo Kas Hari Ini</p><p class="text-lg font-bold <?= $balance>=0?'text-emerald-600':'text-rose-600'?>"><?=rupiah($balance)?></p></div>
</div>
<div class="grid lg:grid-cols-2 gap-4">
<div class="rounded-2xl border bg-white p-4 shadow-sm"><h3 class="text-xs font-semibold mb-2">Shift Kasir</h3>
<?php if($myShift):?><div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-xs">Shift terbuka sejak <?=e($myShift['opened_at'])?> — Saldo awal <?=rupiah($myShift['opening_balance'])?><form method="POST" class="mt-2 space-y-2"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="shift_close"><input type="hidden" name="shift_id" value="<?=$myShift['id']?>"><input name="actual_balance" placeholder="Kas fisik" required class="w-full rounded-xl border px-3 py-2"><input name="notes" placeholder="Keterangan selisih" class="w-full rounded-xl border px-3 py-2"><button class="w-full rounded-xl bg-rose-600 py-2 text-white">Tutup Shift</button></form></div>
<?php else:?><form method="POST" class="space-y-2"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="shift_open"><input name="opening_balance" placeholder="Saldo awal kas" required class="w-full rounded-xl border px-3 py-2 text-xs"><button class="w-full rounded-xl bg-emerald-600 py-2 text-white text-xs">Buka Shift</button></form><?php endif;?>
<div class="mt-3 overflow-auto rounded-xl border"><table class="min-w-full divide-y text-xs"><thead class="bg-slate-100"><tr><th class="px-2 py-1">Kasir</th><th class="px-2 py-1">Awal</th><th class="px-2 py-1">Akhir</th><th class="px-2 py-1">Selisih</th><th class="px-2 py-1">Status</th></tr></thead><tbody class="divide-y"><?php foreach($shifts as $sh):?><tr><td class="px-2 py-1"><?=e($sh['user'])?></td><td class="px-2 py-1"><?=rupiah($sh['opening_balance'])?></td><td class="px-2 py-1"><?=rupiah($sh['actual_balance']??0)?></td><td class="px-2 py-1 <?=($sh['difference']??0)!=0?'text-rose-600 font-bold':''?>"><?=rupiah($sh['difference']??0)?></td><td class="px-2 py-1"><?=e($sh['status'])?></td></tr><?php endforeach;?></tbody></table></div>
</div>
<div class="rounded-2xl border bg-white p-4 shadow-sm"><h3 class="text-xs font-semibold mb-2">Kas Masuk/Keluar</h3><form method="POST" class="grid grid-cols-2 gap-2 mb-3"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="cash"><select name="type" class="rounded-xl border px-2 py-2 text-xs"><option value="in">Masuk</option><option value="out">Keluar</option></select><select name="category" required class="rounded-xl border px-2 py-2 text-xs"><option value="">- Kategori -</option><optgroup label="Kas Masuk"><option>Penjualan Tunai</option><option>Modal Usaha</option><option>Pelunasan Piutang</option><option>Pendapatan Lain</option><option>Setoran Kas</option></optgroup><optgroup label="Kas Keluar"><option>Belanja Stok</option><option>Operasional</option><option>Gaji Karyawan</option><option>Transport</option><option>Utilitas</option><option>Pengeluaran Lain</option></optgroup></select><input name="amount" placeholder="Nominal" required class="rounded-xl border px-2 py-2 text-xs"><input name="description" placeholder="Keterangan" class="rounded-xl border px-2 py-2 text-xs"><button class="col-span-2 rounded-xl bg-emerald-600 py-2 text-white text-xs">Simpan Kas</button></form>
<form method="POST" enctype="multipart/form-data" class="border-t pt-3 space-y-2"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="expense"><p class="text-xs font-semibold">Tambah Pengeluaran</p><select name="category" required class="w-full rounded-xl border px-3 py-2 text-xs"><option value="">- Kategori Pengeluaran -</option><option>Listrik</option><option>Air</option><option>Internet & Telepon</option><option>Sewa Tempat</option><option>Gaji Karyawan</option><option>ATK</option><option>Transport</option><option>Pemeliharaan</option><option>Pemasaran</option><option>Pajak & Retribusi</option><option>Pengeluaran Lain</option></select><input name="amount" placeholder="Nominal" required class="w-full rounded-xl border px-3 py-2 text-xs"><input type="date" name="expense_date" value="<?=date('Y-m-d')?>" class="w-full rounded-xl border px-3 py-2 text-xs"><input name="description" placeholder="Keterangan" class="w-full rounded-xl border px-3 py-2 text-xs"><input type="file" name="proof" class="w-full rounded-xl border px-3 py-2 text-xs"><button class="w-full rounded-xl bg-amber-500 py-2 text-white text-xs">Simpan Pengeluaran</button></form>
</div>
</div>
<div class="rounded-2xl border bg-white p-4 shadow-sm"><h3 class="text-xs font-semibold mb-2">Mutasi Kas Terbaru</h3><div class="overflow-auto rounded-xl border"><table class="min-w-full divide-y text-xs"><thead class="bg-slate-100"><tr><th class="px-2 py-2">Tipe</th><th class="px-2 py-2">Kategori</th><th class="px-2 py-2 text-right">Nominal</th><th class="px-2 py-2">Keterangan</th><th class="px-2 py-2">Tanggal</th></tr></thead><tbody class="divide-y"><?php foreach($cashRows as $r):?><tr><td class="px-2 py-1"><span class="rounded-full px-2 py-0.5 text-[10px] <?=$r['type']=='in'?'bg-emerald-50 text-emerald-600':'bg-rose-50 text-rose-600'?>"><?=e($r['type'])?></span></td><td class="px-2 py-1"><?=e($r['category'])?></td><td class="px-2 py-1 text-right <?= $r['type']=='in'?'text-emerald-600':'text-rose-600'?>"><?=rupiah($r['amount'])?></td><td class="px-2 py-1"><?=e($r['description'])?></td><td class="px-2 py-1 text-[11px]"><?=e($r['created_at'])?></td></tr><?php endforeach;?></tbody></table></div></div>
<div class="rounded-2xl border bg-white p-4 shadow-sm"><h3 class="text-xs font-semibold mb-2">Pengeluaran Terbaru</h3><div class="overflow-auto rounded-xl border"><table class="min-w-full divide-y text-xs"><thead class="bg-slate-100"><tr><th class="px-2 py-2">Kategori</th><th class="px-2 py-2 text-right">Nominal</th><th class="px-2 py-2">Tanggal</th><th class="px-2 py-2">Bukti</th></tr></thead><tbody class="divide-y"><?php foreach($expRows as $e):?><tr><td class="px-2 py-1"><?=e($e['category'])?></td><td class="px-2 py-1 text-right"><?=rupiah($e['amount'])?></td><td class="px-2 py-1"><?=e($e['expense_date'])?></td><td class="px-2 py-1"><?=$e['proof']?'<a href="'.APP_URL.'/uploads/'.e($e['proof']).'" target="_blank" class="text-emerald-600">Lihat</a>':'-'?></td></tr><?php endforeach;?></tbody></table></div></div>
</section></main>
<?php include __DIR__.'/../../components/footer.php';?></body></html>
