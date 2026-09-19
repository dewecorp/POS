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
  redirect($r==='admin' ? url('/admin') : url('/kasir'));
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
        redirect(($u['role']==='admin'||$u['role']==='manager'||$u['role']==='owner')? url('/admin') : url('/kasir'));
      }
    }
  }
}
$timeout=isset($_GET['timeout']);
$stLogo=store_logo_url();
$stName=store_info()['name'] ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="id"><head><title>Login • <?=e(APP_NAME)?></title><?php include __DIR__.'/components/head.php'; ?></head>
<body class="min-h-screen bg-slate-100">
<div class="flex min-h-screen items-center justify-center p-4 sm:p-6">
<div class="grid w-full max-w-4xl overflow-hidden rounded-3xl bg-white shadow-2xl shadow-slate-300/50 lg:grid-cols-2">

<!-- Panel brand -->
<div class="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-emerald-600 via-emerald-500 to-teal-500 p-8 text-white lg:flex">
<div class="absolute -right-16 -top-16 h-56 w-56 rounded-full bg-white/10"></div>
<div class="absolute -bottom-20 -left-10 h-64 w-64 rounded-full bg-white/10"></div>
<div class="relative">
<?php if($stLogo):?>
<div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 p-1.5 shadow-inner ring-1 ring-white/30 backdrop-blur"><img src="<?=e($stLogo)?>" alt="Logo" class="h-full w-full rounded-xl object-contain"></div>
<?php else:?>
<div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/20 text-base font-bold shadow-inner ring-1 ring-white/30 backdrop-blur">POS</div>
<?php endif;?>
<h1 class="mt-6 text-2xl font-bold leading-tight"><?=e($stName)?></h1>
<p class="mt-2 max-w-xs text-sm text-emerald-50/90">Kelola penjualan, stok, dan laporan toko dalam satu sistem yang cepat dan rapi.</p>
</div>
<ul class="relative space-y-3 text-sm text-emerald-50">
<li class="flex items-center gap-2"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-white/20"><svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span> Transaksi kasir cepat & akurat</li>
<li class="flex items-center gap-2"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-white/20"><svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span> Pantau stok & opname real-time</li>
<li class="flex items-center gap-2"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-white/20"><svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span> Laporan penjualan & laba otomatis</li>
</ul>
<p class="relative text-[11px] text-emerald-50/70">&copy; <?=date('Y')?> <?=e($stName)?></p>
</div>

<!-- Panel form -->
<div class="p-6 sm:p-8 lg:p-10">
<div class="mb-6 flex flex-col items-center text-center lg:items-start lg:text-left">
<?php if($stLogo):?>
<div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white p-1.5 shadow-lg ring-1 ring-slate-200 lg:hidden"><img src="<?=e($stLogo)?>" alt="Logo" class="h-full w-full rounded-xl object-contain"></div>
<?php else:?>
<div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-sky-500 text-white font-bold shadow-lg lg:hidden">POS</div>
<?php endif;?>
<h2 class="mt-4 text-xl font-bold text-slate-800 lg:mt-0">Selamat Datang</h2>
<p class="mt-1 text-xs text-slate-500">Masuk ke akun Anda untuk melanjutkan</p>
</div>
<?php if($msg||$err):?><div class="mb-4 flex items-start gap-2 rounded-xl bg-rose-50 border border-rose-200 px-3 py-2.5 text-xs text-rose-700"><svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-2.99l-6.93-12a2 2 0 00-3.48 0l-6.93 12A2 2 0 005.07 19z"/></svg><span><?=e($msg?:$err)?></span></div><?php endif;?>
<?php if($timeout):?><div class="mb-4 flex items-start gap-2 rounded-xl bg-amber-50 border border-amber-200 px-3 py-2.5 text-xs text-amber-700"><svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>Sesi habis, silakan login kembali</span></div><?php endif;?>
<?php if(!empty($_SESSION['login_attempts']) && $_SESSION['login_attempts']>=3):?><div class="mb-3 rounded-xl bg-amber-50 border border-amber-200 px-3 py-2 text-[11px] text-amber-700">Percobaan <?=$_SESSION['login_attempts']?>/5 — jika salah 5x akan dikunci 2 menit</div><?php endif;?>
<form method="POST" class="space-y-4">
<?=csrf_field()?>
<div>
<label class="mb-1.5 block text-xs font-medium text-slate-600">Username / Email</label>
<div class="relative">
<span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></span>
<input name="username" required value="<?=e($_POST['username']??'')?>" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-3 text-sm text-slate-800 transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-500/10" placeholder="admin / kasir">
</div>
</div>
<div>
<label class="mb-1.5 block text-xs font-medium text-slate-600">Password</label>
<div class="relative">
<span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></span>
<input type="password" id="pwd" name="password" required class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-sm text-slate-800 transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-500/10" placeholder="••••••••">
<button type="button" onclick="togglePwd('pwd',this)" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 transition hover:text-emerald-600" aria-label="Toggle password"><svg class="eye-open h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg><svg class="eye-closed h-4 w-4 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.024 10.024 0 012.19-3.435M9.88 9.88A3 3 0 1014.12 14.12M3 3l18 18"/></svg></button>
</div>
</div>
<button class="group flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-500 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-500/30 transition hover:from-emerald-700 hover:to-teal-600 hover:shadow-emerald-500/40 active:scale-[0.99]">
Masuk
<svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
</button>
</form>
<p class="mt-6 text-center text-[11px] text-slate-400 lg:text-left">Butuh bantuan akses? Hubungi administrator toko.</p>
</div>

</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>function togglePwd(id,btn){const i=document.getElementById(id);const o=btn.querySelector('.eye-open'),c=btn.querySelector('.eye-closed');const show=i.type==='password';i.type=show?'text':'password';o.classList.toggle('hidden',show);c.classList.toggle('hidden',!show);}</script>
</body></html>
