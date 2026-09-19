<?php
require_once __DIR__.'/../../config/app.php';
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../core/auth.php';
require_once __DIR__.'/../../core/helper.php';
require_once __DIR__.'/../../core/csrf.php';
require_once __DIR__.'/../../core/audit.php';
require_role(['admin','manager','owner']);
$pdo=db();
$permsAll=$pdo->query("SELECT * FROM permissions ORDER BY module, code")->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!csrf_verify($_POST['_csrf']??'')){flash_set('error','CSRF tidak valid'); redirect($_SERVER['REQUEST_URI']);}
 $act=$_POST['act']??'';
 if($act==='create'){
  $name=trim($_POST['name']); $username=trim($_POST['username']); $email=trim($_POST['email']); $role=trim($_POST['role']); $pass=$_POST['password']??'';
  if($name===''||$username===''||$email===''||$pass===''){flash_set('error','Lengkapi field'); redirect($_SERVER['REQUEST_URI']);}
  if(!in_array($role,['admin','kasir','manager','owner'])) $role='kasir';
  $hash=password_hash($pass, PASSWORD_DEFAULT);
  try{
    $pdo->prepare("INSERT INTO users (name,username,email,password,role,is_active) VALUES (?,?,?,?,?,?)")->execute([$name,$username,$email,$hash,$role, (int)($_POST['is_active']??1)]);
    $uid=$pdo->lastInsertId();
    foreach($_POST['perms']??[] as $pid){ $pdo->prepare("INSERT INTO user_permissions (user_id,permission_id) VALUES (?,?)")->execute([$uid,(int)$pid]); }
    audit('CREATE_USER','users',$uid,"Buat user $username"); flash_set('success','User dibuat');
  }catch(PDOException $e){flash_set('error','Username/email sudah ada');}
  redirect($_SERVER['REQUEST_URI']);
 } elseif($act==='update'){
  $id=(int)$_POST['id']; $name=trim($_POST['name']); $username=trim($_POST['username']); $email=trim($_POST['email']); $role=trim($_POST['role']);
  if(!in_array($role,['admin','kasir','manager','owner'])) $role='kasir';
  $pdo->prepare("UPDATE users SET name=?,username=?,email=?,role=?,is_active=? WHERE id=?")->execute([$name,$username,$email,$role,(int)($_POST['is_active']??1),$id]);
  if(!empty($_POST['password'])){ $hash=password_hash($_POST['password'], PASSWORD_DEFAULT); $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash,$id]); }
  $pdo->prepare("DELETE FROM user_permissions WHERE user_id=?")->execute([$id]);
  foreach($_POST['perms']??[] as $pid){ $pdo->prepare("INSERT INTO user_permissions (user_id,permission_id) VALUES (?,?)")->execute([$id,(int)$pid]); }
  audit('UPDATE_USER','users',$id,"Update user $username"); flash_set('success','User diupdate'); redirect($_SERVER['REQUEST_URI']);
 } elseif($act==='delete'){
  $id=(int)$_POST['id']; if($id==current_user()['id']){flash_set('error','Tidak bisa hapus diri sendiri'); redirect($_SERVER['REQUEST_URI']);}
  $cnt=$pdo->prepare("SELECT COUNT(*) FROM sales WHERE cashier_id=?"); $cnt->execute([$id]); if($cnt->fetchColumn()>0){ $pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$id]); flash_set('success','User dinonaktifkan (punya transaksi)'); } else { $pdo->prepare("DELETE FROM user_permissions WHERE user_id=?")->execute([$id]); $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]); flash_set('success','User dihapus'); }
  audit('DELETE_USER','users',$id,'hapus/nonaktif'); redirect($_SERVER['REQUEST_URI']);
 }
}
$q=trim($_GET['q']??''); $page=max(1,(int)($_GET['page']??1)); $per=ITEMS_PER_PAGE;
$where="WHERE 1"; $par=[]; if($q!==''){ $where.=" AND (name LIKE ? OR username LIKE ? OR email LIKE ?)"; $par[]="%$q%"; $par[]="%$q%"; $par[]="%$q%"; }
$total=$pdo->prepare("SELECT COUNT(*) FROM users $where"); $total->execute($par); $total=(int)$total->fetchColumn();
list($pages,$page,$off)=paginate_params($total,$page,$per);
$stmt=$pdo->prepare("SELECT * FROM users $where ORDER BY id DESC LIMIT $per OFFSET $off"); $stmt->execute($par); $rows=$stmt->fetchAll();
?>
<!DOCTYPE html><html lang="id"><head><title>Pengguna • <?=e((store_info()['name'] ?? APP_NAME))?></title><?php include __DIR__.'/../../components/head.php'; ?></head>
<body class="min-h-screen bg-slate-50 flex flex-col"><?php include __DIR__.'/../../components/header.php';?>
<main class="flex w-full flex-1 flex-col gap-6 px-4 pb-10 pt-5 sm:px-6 lg:px-8 lg:flex-row lg:items-start"><?php include __DIR__.'/../../components/sidebar_admin.php';?>
<section class="order-2 flex-1 space-y-4">
<nav class="text-[11px] text-slate-500">Dashboard / Pengguna</nav>
<div class="flex justify-between items-center"><h2 class="text-lg font-semibold">Manajemen Pengguna</h2><button data-modal-toggle="#modalAdd" class="rounded-full bg-emerald-600 px-4 py-1.5 text-xs text-white">+ Tambah User</button></div>
<form method="GET" class="rounded-2xl border bg-white p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 text-xs"><input name="q" value="<?=e($q)?>" placeholder="Cari nama/username/email" class="rounded-xl border px-3 py-2 sm:col-span-2 lg:col-span-1"><button class="justify-self-start rounded-xl bg-emerald-600 px-4 py-2 text-white font-medium">Cari</button></form>
<div class="rounded-2xl border bg-white p-4 shadow-sm"><div class="overflow-auto rounded-xl border"><table class="min-w-full divide-y text-xs"><thead class="bg-slate-100"><tr><th class="px-3 py-2">Nama</th><th class="px-3 py-2">Username</th><th class="px-3 py-2">Email</th><th class="px-3 py-2">Role</th><th class="px-3 py-2">Status</th><th class="px-3 py-2 text-center">Aksi</th></tr></thead><tbody class="divide-y">
<?php foreach($rows as $r): $up=$pdo->prepare("SELECT p.code FROM permissions p JOIN user_permissions up ON up.permission_id=p.id WHERE up.user_id=?"); $up->execute([$r['id']]); $codes=array_column($up->fetchAll(),'code'); ?>
<tr class="hover:bg-slate-50"><td class="px-3 py-2 font-medium"><?=e($r['name'])?></td><td class="px-3 py-2"><?=e($r['username'])?></td><td class="px-3 py-2"><?=e($r['email'])?></td><td class="px-3 py-2"><span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px]"><?=e($r['role'])?></span></td><td class="px-3 py-2"><?=$r['is_active']?'<span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] text-emerald-600">Aktif</span>':'<span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px]">Nonaktif</span>'?></td>
<td class="px-3 py-2 text-center flex justify-center gap-1"><button type="button" data-modal-toggle="#edit<?=$r['id']?>" title="Edit" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:border-emerald-500 hover:bg-emerald-50 hover:text-emerald-600"><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button><form method="POST" class="inline" onsubmit="return confirm('Hapus/nonaktifkan?')"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button title="Hapus" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100"><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form></td></tr>
<?php endforeach; if(!$rows) echo '<tr><td colspan="6" class="px-3 py-6 text-center text-slate-400">Belum ada user</td></tr>';?>
</tbody></table></div>
<div class="mt-3 flex justify-between text-xs text-slate-500"><span><?=$total?> user</span><div class="flex gap-1"><?php for($i=1;$i<=$pages;$i++):?><a href="?q=<?=urlencode($q)?>&page=<?=$i?>" class="rounded-full border px-3 py-1 <?=$i==$page?'bg-emerald-600 text-white':''?>"><?=$i?></a><?php endfor;?></div></div>
</div></section></main>

<!-- Modals Edit User -->
<?php foreach($rows as $r): $up=$pdo->prepare("SELECT p.code FROM permissions p JOIN user_permissions up ON up.permission_id=p.id WHERE up.user_id=?"); $up->execute([$r['id']]); $codes=array_column($up->fetchAll(),'code'); ?>
<div id="edit<?=$r['id']?>" class="modal-dashboard hidden"><div class="modal-dialog max-w-md" style="max-width:440px"><div class="modal-content"><div class="flex justify-between mb-3"><h3 class="text-sm font-semibold">Edit User</h3><button type="button" data-modal-hide="#edit<?=$r['id']?>" class="btn-close"></button></div>
<form method="POST" class="space-y-2"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="update"><input type="hidden" name="id" value="<?=$r['id']?>">
<input name="name" value="<?=e($r['name'])?>" required class="w-full rounded-xl border px-3 py-2 text-xs" placeholder="Nama"><input name="username" value="<?=e($r['username'])?>" required class="w-full rounded-xl border px-3 py-2 text-xs" placeholder="Username"><input name="email" value="<?=e($r['email'])?>" required class="w-full rounded-xl border px-3 py-2 text-xs" placeholder="Email">
<select name="role" class="w-full rounded-xl border px-3 py-2 text-xs"><option value="admin" <?=$r['role']=='admin'?'selected':''?>>Admin</option><option value="kasir" <?=$r['role']=='kasir'?'selected':''?>>Kasir</option><option value="manager" <?=$r['role']=='manager'?'selected':''?>>Manager</option><option value="owner" <?=$r['role']=='owner'?'selected':''?>>Owner</option></select>
<select name="is_active" class="w-full rounded-xl border px-3 py-2 text-xs"><option value="1" <?=$r['is_active']?'selected':''?>>Aktif</option><option value="0" <?=!$r['is_active']?'selected':''?>>Nonaktif</option></select>
<div class="relative"><input id="pwd<?=$r['id']?>" name="password" type="password" placeholder="Password baru (kosongkan jika tidak ganti)" class="w-full rounded-xl border px-3 py-2 pr-10 text-xs"><button type="button" onclick="togglePwd('pwd<?=$r['id']?>',this)" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600"><svg class="eye-open h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg><svg class="eye-closed h-4 w-4 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.024 10.024 0 012.19-3.435M9.88 9.88A3 3 0 1014.12 14.12M3 3l18 18"/></svg></button></div>
<div class="rounded-xl border p-2 max-h-[120px] overflow-auto"><p class="text-[11px] font-semibold mb-1">Permission (kasir granular, admin auto all)</p><div class="grid grid-cols-2 gap-1 text-[11px]"><?php foreach($permsAll as $p):?><label class="flex gap-1"><input type="checkbox" name="perms[]" value="<?=$p['id']?>" <?=in_array($p['code'],$codes)?'checked':''?>> <?=e($p['code'])?></label><?php endforeach;?></div></div>
<button class="w-full rounded-xl bg-emerald-600 py-2 text-xs text-white">Simpan</button></form></div></div></div>
<?php endforeach;?>
<div id="modalAdd" class="modal-dashboard hidden"><div class="modal-dialog max-w-md" style="max-width:440px"><div class="modal-content"><div class="flex justify-between mb-3"><h3 class="text-sm font-semibold">Tambah User</h3><button data-modal-hide="#modalAdd" class="btn-close"></button></div>
<form method="POST" class="space-y-2"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="create">
<input name="name" required placeholder="Nama" class="w-full rounded-xl border px-3 py-2 text-xs"><input name="username" required placeholder="Username" class="w-full rounded-xl border px-3 py-2 text-xs"><input name="email" required placeholder="Email" class="w-full rounded-xl border px-3 py-2 text-xs"><div class="relative"><input id="pwdNew" name="password" type="password" required placeholder="Password" class="w-full rounded-xl border px-3 py-2 pr-10 text-xs"><button type="button" onclick="togglePwd('pwdNew',this)" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600"><svg class="eye-open h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg><svg class="eye-closed h-4 w-4 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.024 10.024 0 012.19-3.435M9.88 9.88A3 3 0 1014.12 14.12M3 3l18 18"/></svg></button></div>
<select name="role" class="w-full rounded-xl border px-3 py-2 text-xs"><option value="kasir">Kasir</option><option value="admin">Admin</option><option value="manager">Manager</option><option value="owner">Owner</option></select>
<select name="is_active" class="w-full rounded-xl border px-3 py-2 text-xs"><option value="1">Aktif</option><option value="0">Nonaktif</option></select>
<div class="rounded-xl border p-2 max-h-[120px] overflow-auto"><p class="text-[11px] font-semibold mb-1">Permission</p><div class="grid grid-cols-2 gap-1 text-[11px]"><?php foreach($permsAll as $p):?><label class="flex gap-1"><input type="checkbox" name="perms[]" value="<?=$p['id']?>"> <?=e($p['code'])?></label><?php endforeach;?></div></div>
<button class="w-full rounded-xl bg-emerald-600 py-2 text-xs text-white">Buat User</button></form></div></div></div>
<script>function togglePwd(id,btn){const i=document.getElementById(id);const o=btn.querySelector('.eye-open'),c=btn.querySelector('.eye-closed');const s=i.type==='password';i.type=s?'text':'password';o.classList.toggle('hidden',s);c.classList.toggle('hidden',!s);}</script>
<?php include __DIR__.'/../../components/footer.php';?></body></html>
