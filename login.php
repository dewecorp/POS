<?php
require_once __DIR__.'/config/app.php';
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/core/session.php';
require_once __DIR__.'/core/helper.php';
require_once __DIR__.'/core/security.php';
require_once __DIR__.'/core/csrf.php';
require_once __DIR__.'/core/auth.php';
require_once __DIR__.'/core/audit.php';
if(is_logged_in()){
  $r=$_SESSION['user']['role']??'kasir';
  redirect($r==='admin' ? APP_URL.'/admin/index.php' : APP_URL.'/kasir/index.php');
}
$err=flash_get('error');
$flashSuccess=flash_get('success');
$msg=$flashSuccess ?: '';
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!csrf_verify($_POST['_csrf']??'')){ $msg='CSRF tidak valid. Silakan refresh halaman.'; }
  else{
    $username=trim($_POST['username']??'');
    $password=$_POST['password']??'';
    if($username===''||$password===''){ $msg='Username dan password wajib diisi'; }
    else{
      $pdo=db();
      $stmt=$pdo->prepare("SELECT * FROM users WHERE username=? OR email=? LIMIT 1");
      $stmt->execute([$username,$username]);
      $u=$stmt->fetch();
      if(!$u){ $msg='Akun tidak ditemukan: '.e($username); }
      elseif(!$u['is_active']){ $msg='Akun dinonaktifkan. Hubungi admin.'; }
      elseif(!password_verify($password,$u['password'])){ $msg='Password salah untuk '.e($username); audit('LOGIN_FAILED','auth',$u['id'],"Login gagal: $username"); }
      else {
        brute_reset();
        $perms=user_permissions($pdo,(int)$u['id']);
        login_user(['id'=>$u['id'],'name'=>$u['name'],'username'=>$u['username'],'email'=>$u['email'],'role'=>$u['role'],'permissions'=>$perms]);
        $pdo->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$u['id']]);
        audit('LOGIN','auth',$u['id'],'Login berhasil');
        redirect(($u['role']==='admin'||$u['role']==='manager'||$u['role']==='owner')? APP_URL.'/admin/index.php' : APP_URL.'/kasir/index.php');
      }
    }
  }
}
$timeout=isset($_GET['timeout']);
?>
<!DOCTYPE html>
<html lang="id"><head><title>Login • <?=e(APP_NAME)?></title><?php include __DIR__.'/components/head.php'; ?></head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-4">
<div class="w-full max-w-md">
<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/50">
<div class="flex flex-col items-center text-center mb-5"><div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-sky-500 text-white font-bold shadow-lg">POS</div><h1 class="mt-3 text-lg font-semibold"><?=e(APP_NAME)?></h1><p class="text-xs text-slate-500">Masuk untuk melanjutkan</p></div>
<?php if($msg||$err):?><div class="mb-3 rounded-xl bg-rose-50 border border-rose-200 px-3 py-2 text-xs text-rose-700"><?=e($msg?:$err)?></div><?php endif;?>
<?php if($timeout):?><div class="mb-3 rounded-xl bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-700">Sesi habis, silakan login kembali</div><?php endif;?>
<?php if(!empty($_SESSION['login_attempts']) && $_SESSION['login_attempts']>=3):?><div class="mb-1 text-[11px] text-amber-600">Percobaan <?=$_SESSION['login_attempts']?>/5 — jika salah 5x akan dikunci 2 menit</div><?php endif;?>
<form method="POST" class="space-y-3">
<?=csrf_field()?>
<div><label class="mb-1 block text-xs font-medium text-slate-600">Username / Email</label><input name="username" required value="<?=e($_POST['username']??'')?>" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 px-3 text-xs focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20" placeholder="admin / kasir"></div>
<div><label class="mb-1 block text-xs font-medium text-slate-600">Password</label><div class="relative"><input type="password" id="pwd" name="password" required class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 px-3 pr-10 text-xs focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20" placeholder="••••••••"><button type="button" onclick="togglePwd('pwd',this)" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600" aria-label="Toggle password"><svg class="eye-open h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg><svg class="eye-closed h-4 w-4 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.024 10.024 0 012.19-3.435M9.88 9.88A3 3 0 1014.12 14.12M3 3l18 18"/></svg></button></div></div>
<button class="w-full rounded-xl bg-gradient-to-r from-emerald-600 to-sky-500 py-2.5 text-xs font-semibold text-white shadow-lg hover:from-emerald-700">Masuk</button>
</form>
<div class="mt-4 rounded-xl bg-slate-50 border border-slate-200 p-3 text-[11px] text-slate-600">
<p class="font-semibold mb-1">Akun demo (password: 123)</p>
<p>admin / admin@pos.local — Admin</p><p>kasir / kasir@pos.local — Kasir</p><p>manager / manager@pos.local — Manager</p>
</div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>function togglePwd(id,btn){const i=document.getElementById(id);const o=btn.querySelector('.eye-open'),c=btn.querySelector('.eye-closed');const show=i.type==='password';i.type=show?'text':'password';o.classList.toggle('hidden',show);c.classList.toggle('hidden',!show);}</script>
</body></html>
