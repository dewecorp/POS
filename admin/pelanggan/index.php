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
 if($act==='create'){
  $name=trim($_POST['name']); $phone=trim($_POST['phone']??''); $address=trim($_POST['address']??''); $type=$_POST['type']??'umum';
  if($name===''){flash_set('error','Nama wajib'); redirect($_SERVER['REQUEST_URI']);}
  $nextId=(int)$pdo->query("SELECT COALESCE(MAX(id),0)+1 FROM customers")->fetchColumn();
  $code=gen_code('CUST', $nextId, 3);
  $chk=$pdo->prepare("SELECT COUNT(*) FROM customers WHERE code=?"); $chk->execute([$code]);
  while($chk->fetchColumn()>0){ $nextId++; $code=gen_code('CUST', $nextId, 3); $chk->execute([$code]); }
  try{$pdo->prepare("INSERT INTO customers (code,name,phone,address,type) VALUES (?,?,?,?,?)")->execute([$code,$name,$phone,$address,$type]); audit('CREATE_CUSTOMER','customers',null,$code); flash_set('success','Pelanggan ditambahkan');}catch(PDOException $e){flash_set('error','Kode sudah ada');}
  redirect($_SERVER['REQUEST_URI']);
 } elseif($act==='update'){
  $id=(int)$_POST['id']; $pdo->prepare("UPDATE customers SET code=?,name=?,phone=?,address=?,type=?,is_active=? WHERE id=?")->execute([trim($_POST['code']),trim($_POST['name']),trim($_POST['phone']),trim($_POST['address']),$_POST['type'],(int)($_POST['is_active']??1),$id]); audit('UPDATE_CUSTOMER','customers',$id,'update'); flash_set('success','Diupdate'); redirect($_SERVER['REQUEST_URI']);
 } elseif($act==='delete'){
  $id=(int)$_POST['id']; $c=$pdo->prepare("SELECT COUNT(*) FROM sales WHERE customer_id=?"); $c->execute([$id]); if($c->fetchColumn()>0){flash_set('error','Pelanggan sudah dipakai transaksi'); redirect($_SERVER['REQUEST_URI']);}
  $pdo->prepare("DELETE FROM customers WHERE id=?")->execute([$id]); audit('DELETE_CUSTOMER','customers',$id,'hapus'); flash_set('success','Dihapus'); redirect($_SERVER['REQUEST_URI']);
 }
}
$q=trim($_GET['q']??''); $page=max(1,(int)($_GET['page']??1)); $per=ITEMS_PER_PAGE; $where="WHERE 1"; $par=[];
if($q!==''){$where.=" AND (code LIKE ? OR name LIKE ? OR phone LIKE ?)"; $par[]="%$q%"; $par[]="%$q%"; $par[]="%$q%";}
$total=$pdo->prepare("SELECT COUNT(*) FROM customers $where"); $total->execute($par); $total=(int)$total->fetchColumn();
list($pages,$page,$off)=paginate_params($total,$page,$per);
$stmt=$pdo->prepare("SELECT * FROM customers $where ORDER BY id DESC LIMIT $per OFFSET $off"); $stmt->execute($par); $rows=$stmt->fetchAll();
?>
<!DOCTYPE html><html lang="id"><head><title>Pelanggan • <?=e((store_info()['name'] ?? APP_NAME))?></title><?php include __DIR__.'/../../components/head.php'; ?></head>
<body class="min-h-screen bg-slate-50 flex flex-col"><?php include __DIR__.'/../../components/header.php';?>
<main class="flex w-full flex-1 flex-col gap-6 px-4 pb-10 pt-5 sm:px-6 lg:px-8 lg:flex-row lg:items-start"><?php include __DIR__.'/../../components/sidebar_admin.php';?>
<section class="order-2 flex-1 space-y-4">
<nav class="text-[11px] text-slate-500">Dashboard / Pelanggan</nav>
<div class="flex justify-between items-center"><h2 class="text-lg font-semibold">Pelanggan</h2><button data-modal-toggle="#modalAdd" class="rounded-full bg-emerald-600 px-4 py-1.5 text-xs text-white">+ Tambah Pelanggan</button></div>
<form method="GET" class="rounded-2xl border bg-white p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 text-xs"><input name="q" value="<?=e($q)?>" placeholder="Cari pelanggan" class="rounded-xl border px-3 py-2 sm:col-span-2 lg:col-span-1"><button class="justify-self-start rounded-xl bg-emerald-600 px-4 py-2 text-white font-medium">Cari</button></form>
<div class="rounded-2xl border bg-white p-4 shadow-sm"><div class="overflow-hidden rounded-xl border"><table class="min-w-full divide-y text-xs"><thead class="bg-slate-100"><tr><th class="px-3 py-2 text-left">Kode</th><th class="px-3 py-2 text-left">Nama</th><th class="px-3 py-2 text-left">HP</th><th class="px-3 py-2 text-left">Tipe</th><th class="px-3 py-2 text-center">Status</th><th class="px-3 py-2 text-center">Aksi</th></tr></thead><tbody class="divide-y">
<?php foreach($rows as $r):?><tr class="hover:bg-slate-50"><td class="px-3 py-2 font-medium"><?=e($r['code'])?></td><td class="px-3 py-2"><?=e($r['name'])?></td><td class="px-3 py-2"><?=e($r['phone'])?></td><td class="px-3 py-2"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px]"><?=e($r['type'])?></span></td><td class="px-3 py-2 text-center"><?=$r['is_active']?'<span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] text-emerald-600 ring-1 ring-emerald-200">Aktif</span>':'<span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px]">Nonaktif</span>'?></td>
<td class="px-3 py-2 text-center flex justify-center gap-1"><button type="button" data-modal-toggle="#modalEdit<?=$r['id']?>" title="Edit" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:border-emerald-500 hover:bg-emerald-50 hover:text-emerald-600"><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button><form method="POST" class="inline" onsubmit="return confirm('Hapus?')"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button title="Hapus" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100"><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form></td></tr>
<?php endforeach; if(!$rows) echo '<tr><td colspan="6" class="px-3 py-6 text-center text-slate-400">Belum ada data</td></tr>';?>
</tbody></table></div>
<div class="mt-3 flex justify-between text-xs text-slate-500"><span><?=$total?> data</span><div class="flex gap-1"><?php for($i=1;$i<=$pages;$i++):?><a href="?q=<?=urlencode($q)?>&page=<?=$i?>" class="rounded-full border px-3 py-1 <?=$i==$page?'bg-emerald-600 text-white':''?>"><?=$i?></a><?php endfor;?></div></div>
</div></section></main>

<!-- Modals Edit Pelanggan -->
<?php foreach($rows as $r):?>
<div id="modalEdit<?=$r['id']?>" class="modal-dashboard hidden"><div class="modal-dialog max-w-sm"><div class="modal-content"><div class="flex justify-between mb-3"><h3 class="text-sm font-semibold">Edit Pelanggan</h3><button type="button" data-modal-hide="#modalEdit<?=$r['id']?>" class="btn-close"></button></div>
<form method="POST" class="space-y-2"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="update"><input type="hidden" name="id" value="<?=$r['id']?>"><input name="code" value="<?=e($r['code'])?>" required class="w-full rounded-xl border px-3 py-2 text-xs"><input name="name" value="<?=e($r['name'])?>" required class="w-full rounded-xl border px-3 py-2 text-xs"><input name="phone" value="<?=e($r['phone'])?>" class="w-full rounded-xl border px-3 py-2 text-xs"><textarea name="address" class="w-full rounded-xl border px-3 py-2 text-xs"><?=e($r['address'])?></textarea><select name="type" class="w-full rounded-xl border px-3 py-2 text-xs"><option value="umum" <?=$r['type']==='umum'?'selected':''?>>Umum</option><option value="member" <?=$r['type']==='member'?'selected':''?>>Member</option><option value="grosir" <?=$r['type']==='grosir'?'selected':''?>>Grosir</option></select><select name="is_active" class="w-full rounded-xl border px-3 py-2 text-xs"><option value="1" <?=$r['is_active']?'selected':''?>>Aktif</option><option value="0" <?=!$r['is_active']?'selected':''?>>Nonaktif</option></select><button class="w-full rounded-xl bg-emerald-600 py-2 text-xs text-white">Simpan</button></form></div></div></div>
<?php endforeach;?>
<div id="modalAdd" class="modal-dashboard hidden"><div class="modal-dialog max-w-sm"><div class="modal-content"><div class="flex justify-between mb-3"><h3 class="text-sm font-semibold">Tambah Pelanggan</h3><button data-modal-hide="#modalAdd" class="btn-close"></button></div>
<form method="POST" class="space-y-2"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="create"><div class="rounded-xl bg-emerald-50 border border-emerald-200 px-3 py-2 text-[11px] text-emerald-700 flex items-center gap-2"><svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> Kode pelanggan otomatis digenerate sistem</div><input name="name" required placeholder="Nama" class="w-full rounded-xl border px-3 py-2 text-xs"><input name="phone" placeholder="HP" class="w-full rounded-xl border px-3 py-2 text-xs"><textarea name="address" placeholder="Alamat" class="w-full rounded-xl border px-3 py-2 text-xs"></textarea><select name="type" class="w-full rounded-xl border px-3 py-2 text-xs"><option value="umum">Umum</option><option value="member">Member</option><option value="grosir">Grosir</option></select><button class="w-full rounded-xl bg-emerald-600 py-2 text-xs text-white">Simpan</button></form></div></div></div>
<?php include __DIR__.'/../../components/footer.php';?></body></html>
