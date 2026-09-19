<?php
require_once __DIR__.'/../../config/app.php';
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../core/auth.php';
require_once __DIR__.'/../../core/helper.php';
require_once __DIR__.'/../../core/csrf.php';
require_once __DIR__.'/../../core/audit.php';
require_role(['admin','owner']);
$pdo=db();
$store=$pdo->query("SELECT * FROM stores LIMIT 1")->fetch();
$settings=[]; foreach($pdo->query("SELECT * FROM settings") as $r) $settings[$r['key']]=$r['value'];
$methods=$pdo->query("SELECT * FROM payment_methods ORDER BY id")->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!csrf_verify($_POST['_csrf']??'')){flash_set('error','CSRF tidak valid'); redirect($_SERVER['REQUEST_URI']);}
 $act=$_POST['act']??'';
 if($act==='store'){
  $name=trim($_POST['name']??''); $addr=trim($_POST['address']??''); $phone=trim($_POST['phone']??''); $email=trim($_POST['email']??''); $footer=trim($_POST['receipt_footer']??'');
  $logo=$store['logo']??null;
  if(!empty($_FILES['logo']['name'])){
    $ext=strtolower(pathinfo($_FILES['logo']['name'],PATHINFO_EXTENSION)); $allowed=['jpg','jpeg','png','webp'];
    if(in_array($ext,$allowed)){ $fname='logo_'.time().'.'.$ext; if(!is_dir(__DIR__.'/../../uploads')) mkdir(__DIR__.'/../../uploads',0777,true); move_uploaded_file($_FILES['logo']['tmp_name'], __DIR__.'/../../uploads/'.$fname); $logo=$fname; }
  }
  $pdo->prepare("UPDATE stores SET name=?, address=?, phone=?, email=?, logo=?, receipt_footer=? WHERE id=?")->execute([$name,$addr,$phone,$email,$logo,$footer,$store['id']]);
  $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES ('store_name',?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")->execute([$name]);
  audit('UPDATE_SETTINGS','settings',$store['id'],'Update toko'); flash_set('success','Pengaturan toko disimpan'); redirect($_SERVER['REQUEST_URI']);
 }
 if($act==='tax'){
  $tax=(float)($_POST['tax_percent']??0);
  $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES ('tax_percent',?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")->execute([(string)$tax]);
  $pdo->prepare("UPDATE stores SET tax_percent=? WHERE id=?")->execute([$tax,$store['id']]);
  audit('UPDATE_SETTINGS','settings',null,"Update pajak $tax%"); flash_set('success','Pajak disimpan'); redirect($_SERVER['REQUEST_URI']);
 }
 if($act==='pay_add'){
  $code=trim($_POST['code']); $name=trim($_POST['name']); if($code&&$name){ $pdo->prepare("INSERT INTO payment_methods (code,name) VALUES (?,?)")->execute([$code,$name]); flash_set('success','Metode ditambahkan'); } redirect($_SERVER['REQUEST_URI']);
 }
 if($act==='pay_toggle'){
  $id=(int)$_POST['id']; $pdo->prepare("UPDATE payment_methods SET is_active = 1 - is_active WHERE id=?")->execute([$id]); redirect($_SERVER['REQUEST_URI']);
 }
 if($act==='pay_del'){
  $id=(int)$_POST['id']; $pdo->prepare("DELETE FROM payment_methods WHERE id=?")->execute([$id]); redirect($_SERVER['REQUEST_URI']);
 }
}
?>
<!DOCTYPE html><html lang="id"><head><title>Pengaturan • <?=e((store_info()['name'] ?? APP_NAME))?></title><?php include __DIR__.'/../../components/head.php'; ?></head>
<body class="min-h-screen bg-slate-50 flex flex-col"><?php include __DIR__.'/../../components/header.php';?>
<main class="flex w-full flex-1 flex-col gap-6 px-4 pb-10 pt-5 sm:px-6 lg:px-8 lg:flex-row lg:items-start"><?php include __DIR__.'/../../components/sidebar_admin.php';?>
<section class="order-2 flex-1 space-y-4">
<nav class="text-[11px] text-slate-500">Dashboard / Pengaturan</nav>
<h2 class="text-lg font-semibold">Pengaturan Toko</h2>
<div class="grid lg:grid-cols-2 gap-4">
<div class="rounded-2xl border bg-white p-4 shadow-sm"><h3 class="text-xs font-semibold mb-3">Info Toko & Struk</h3><form method="POST" enctype="multipart/form-data" class="space-y-2"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="store">
<input name="name" value="<?=e($store['name'])?>" required placeholder="Nama toko" class="w-full rounded-xl border px-3 py-2 text-xs"><textarea name="address" placeholder="Alamat" class="w-full rounded-xl border px-3 py-2 text-xs"><?=e($store['address'])?></textarea><input name="phone" value="<?=e($store['phone'])?>" placeholder="Telepon" class="w-full rounded-xl border px-3 py-2 text-xs"><input name="email" value="<?=e($store['email'])?>" placeholder="Email" class="w-full rounded-xl border px-3 py-2 text-xs"><textarea name="receipt_footer" placeholder="Footer struk" class="w-full rounded-xl border px-3 py-2 text-xs"><?=e($store['receipt_footer'])?></textarea>
<?php if($store['logo']):?><img src="<?=APP_URL?>/uploads/<?=e($store['logo'])?>" class="h-12 rounded"><?php endif;?>
<input type="file" name="logo" accept="image/*" class="w-full rounded-xl border px-3 py-2 text-xs">
<button class="w-full rounded-xl bg-emerald-600 py-2 text-xs text-white">Simpan Toko</button></form></div>
<div class="space-y-4">
<div class="rounded-2xl border bg-white p-4 shadow-sm"><h3 class="text-xs font-semibold mb-2">Pajak</h3><form method="POST" class="flex gap-2"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="tax"><input name="tax_percent" type="number" step="0.01" value="<?=e($settings['tax_percent']??0)?>" class="flex-1 rounded-xl border px-3 py-2 text-xs"><span class="py-2 text-xs">%</span><button class="rounded-xl bg-emerald-600 px-4 py-2 text-xs text-white">Simpan</button></form><p class="text-[11px] text-slate-400 mt-1">Diterapkan otomatis di POS (grand total)</p></div>
<div class="rounded-2xl border bg-white p-4 shadow-sm"><h3 class="text-xs font-semibold mb-2">Metode Pembayaran</h3><form method="POST" class="grid grid-cols-3 gap-2 mb-3"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="pay_add"><input name="code" required placeholder="kode (tunai)" class="rounded-xl border px-2 py-2 text-xs"><input name="name" required placeholder="Nama" class="rounded-xl border px-2 py-2 text-xs"><button class="rounded-xl bg-emerald-600 px-2 py-2 text-xs text-white">Tambah</button></form>
<div class="space-y-1"><?php foreach($methods as $m):?><div class="flex justify-between items-center rounded-xl border px-3 py-2 bg-slate-50 text-xs"><span><?=e($m['code'])?> - <?=e($m['name'])?> <?=$m['is_active']?'':'<span class="text-rose-600">(nonaktif)</span>'?></span><div class="flex gap-1"><form method="POST" class="inline"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="pay_toggle"><input type="hidden" name="id" value="<?=$m['id']?>"><button title="<?=$m['is_active']?'Nonaktifkan':'Aktifkan'?>" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:border-emerald-500 hover:text-emerald-600"><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg></button></form><form method="POST" class="inline" onsubmit="return confirm('Hapus?')"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="pay_del"><input type="hidden" name="id" value="<?=$m['id']?>"><button title="Hapus" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100"><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form></div></div><?php endforeach;?></div></div>
</div>
</div>
</section></main>
<?php include __DIR__.'/../../components/footer.php';?></body></html>
