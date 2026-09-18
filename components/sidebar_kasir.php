<?php
$uri=$_SERVER['REQUEST_URI']??'';
function kas_item($p,$uri,$label,$icon){
    $active = strpos($uri,$p)!==false;
    $cls = $active
        ? 'flex items-center gap-2 rounded-lg bg-white/20 px-3 py-2 text-xs font-semibold text-white shadow-sm ring-1 ring-white/20'
        : 'flex items-center gap-2 rounded-lg px-3 py-2 text-xs text-emerald-100 hover:bg-white/10 hover:text-white transition';
    $ic = $active ? 'text-white' : 'text-emerald-200';
    echo '<li><a href="'.APP_URL.$p.'" class="'.$cls.'"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 '.$ic.'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">'.$icon.'</svg><span>'.$label.'</span></a></li>';
}
?>
<aside id="sidebar" class="order-1 w-full hidden lg:flex lg:w-64 lg:shrink-0 lg:sticky lg:top-14 lg:h-auto lg:flex-col lg:self-start">
<div class="sidebar-scroll sidebar-card flex w-full flex-1 flex-col rounded-2xl bg-gradient-to-br from-emerald-600 to-emerald-500 p-3 shadow-sm">
<nav class="flex-1 text-xs text-white">
<p class="mb-1.5 px-2 text-[10px] font-bold uppercase tracking-[0.15em] text-emerald-100">Menu Kasir</p>
<ul class="space-y-1">
<?php
kas_item('/kasir/index.php',$uri,'Penjualan','<path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>');
kas_item('/kasir/cek-harga.php',$uri,'Cek Harga','<path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>');
kas_item('/kasir/history.php',$uri,'History','<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>');
kas_item('/admin/index.php',$uri,'Dashboard Admin','<path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6"/>');
?>
</ul>
</nav>
</div>
</aside>
