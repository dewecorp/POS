<?php
$u = current_user();
$role = $u['role'] ?? '';
$initial = strtoupper(substr($u['name'] ?? 'U',0,1) . substr(explode(' ', $u['name'] ?? 'U')[1] ?? '',0,1));
if($initial==='') $initial='U';
$stLogo = store_logo_url();
$stName = store_info()['name'] ?? APP_NAME;
$hariList = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
$bulanList = ['January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April','May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'];
$hariIni = $hariList[date('l')].', '.date('j').' '.$bulanList[date('F')].' '.date('Y');
?>
<header class="pos-header sticky top-0 z-50 flex items-center bg-emerald-600 shadow-md">
<div class="flex w-full items-center justify-between px-4 sm:px-6 lg:px-8">
<div class="flex items-center gap-3">
<?php if($stLogo): ?>
  <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 p-1 shadow-inner backdrop-blur ring-1 ring-white/20">
    <img src="<?=e($stLogo)?>" alt="Logo" class="h-full w-full object-contain rounded-lg">
  </div>
<?php else: ?>
  <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/20 text-white shadow-inner font-bold text-sm tracking-tight ring-1 ring-white/30">
    POS
  </div>
<?php endif; ?>
<div>
  <h1 class="text-sm font-bold tracking-tight text-white sm:text-base leading-tight"><?=e($stName)?></h1>
  <p class="text-[11px] text-emerald-100 sm:text-xs"><?= $role==='admin'?'Dashboard Admin':'POS Kasir'?></p>
</div>
</div>
<span class="hidden items-center gap-2 rounded-full bg-emerald-700/70 border border-emerald-500/50 px-3 py-1.5 text-xs font-medium text-white shadow-sm lg:inline-flex">
  <svg class="h-3.5 w-3.5 text-emerald-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
  <?=e($hariIni)?>
</span>
<div class="relative hidden md:block group">
<button type="button" class="inline-flex items-center gap-2 rounded-full bg-emerald-700/70 border border-emerald-500/50 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-emerald-700">
  <span class="h-2 w-2 rounded-full bg-emerald-300 animate-pulse"></span>
  <?=e($u['name']??'')?> <span class="text-emerald-200 text-[10px] uppercase font-bold tracking-wider">(<?=e($role)?>)</span>
  <svg class="h-3.5 w-3.5 transition-transform duration-200 group-hover:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
</button>
<div class="invisible absolute right-0 top-full z-50 w-56 pt-2 opacity-0 translate-y-1 transition-all duration-150 group-hover:visible group-hover:opacity-100 group-hover:translate-y-0">
  <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
    <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-3">
      <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white"><?=e($initial)?></div>
      <div class="min-w-0">
        <p class="truncate text-xs font-semibold text-slate-800"><?=e($u['name']??'')?></p>
        <p class="truncate text-[10px] uppercase tracking-wider text-slate-400"><?=e($role)?></p>
      </div>
    </div>
    <a href="<?=url('/logout')?>" class="flex items-center gap-2 px-4 py-2.5 text-xs font-medium text-slate-600 transition hover:bg-rose-50 hover:text-rose-600">
      <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Logout
    </a>
  </div>
</div>
</div>
<button class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-700/70 border border-emerald-500/50 text-white md:hidden hover:bg-emerald-700" id="menuToggle">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h16"/></svg>
</button>
</div>
<div class="border-t border-emerald-500/40 bg-emerald-700 px-4 pb-3 pt-2 md:hidden" id="mobileMenu" style="display:none">
  <div class="flex items-center justify-between text-xs text-white mb-2">
    <span><?=e($u['name']??'')?> (<?=e($role)?>)</span>
  </div>
  <a href="<?=url('/logout')?>" class="block w-full rounded-lg bg-rose-600 px-3 py-2 text-center text-xs font-medium text-white shadow">Logout</a>
</div>
</header>
