<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/helper.php';
require_login();
$pdo=db();
$role=current_user()['role']??'kasir';
$is_admin=in_array($role,['admin','manager','owner']);
$q=trim($_GET['q']??'');
$product=null;
if($q!==''){
 $s=$pdo->prepare("SELECT p.*, c.name as cat_name, u.name as unit_name FROM products p LEFT JOIN product_categories c ON c.id=p.category_id LEFT JOIN product_units u ON u.id=p.unit_id WHERE p.barcode=? OR p.sku=? OR p.name LIKE ? LIMIT 1");
 $s->execute([$q,$q,"%$q%"]); $product=$s->fetch();
}
?>
<!DOCTYPE html><html lang="id"><head><title>Cek Harga • <?=e((store_info()['name'] ?? APP_NAME))?></title><?php include __DIR__.'/../components/head.php'; ?></head>
<body class="min-h-screen bg-slate-50 flex flex-col"><?php include __DIR__.'/../components/header.php';?>
<div class="border-b bg-white px-4 sm:px-6 lg:px-8 py-2.5 flex gap-2 text-sm"><a href="<?=url('/kasir/index')?>" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-2.5 font-medium text-slate-600 hover:bg-slate-50"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>PENJUALAN <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500">F1</span></a><a href="<?=url('/kasir/cek-harga')?>" class="inline-flex items-center gap-2 rounded-full bg-emerald-600 px-5 py-2.5 text-white font-semibold shadow-sm"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>CEK HARGA <span class="rounded bg-white/20 px-1.5 py-0.5 text-[10px] font-bold">F3</span></a><a href="<?=url('/kasir/history')?>" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-2.5 font-medium text-slate-600 hover:bg-slate-50"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>HISTORY <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500">F4</span></a></div>
<main class="flex w-full flex-1 flex-col gap-6 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
<section class="flex-1 space-y-4">
<h2 class="text-lg font-semibold">Cek Harga</h2>
<form id="formCekHarga" class="flex gap-2" method="GET">
<input id="inputCekHarga" name="q" value="<?=e($q)?>" autofocus placeholder="Scan barcode / SKU / nama produk" class="flex-1 rounded-xl border px-3 py-3 text-sm focus:border-emerald-500 focus:outline-none">
<button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700 transition">Cari</button>
</form>
<script>
let timerCek=null;
const inp=document.getElementById('inputCekHarga');
const form=document.getElementById('formCekHarga');
if(inp && form){
  inp.addEventListener('input', function(){
    clearTimeout(timerCek);
    const val=this.value.trim();
    if(val.length >= 3){
      timerCek=setTimeout(()=>{ form.submit(); }, 400);
    }
  });
}
document.addEventListener('keydown',e=>{
 if(e.key==='F1'){ e.preventDefault(); window.location.href='<?=url('/kasir/index')?>'; }
 if(e.key==='F3'){ e.preventDefault(); if(inp) inp.focus(); }
 if(e.key==='F4'){ e.preventDefault(); window.location.href='<?=url('/kasir/history')?>'; }
});
</script>
<?php if($q!=='' && !$product):?><div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">Produk tidak ditemukan: <?=e($q)?></div><?php endif;?>
<?php if($product):?>
<div class="rounded-2xl border bg-white p-5 shadow-sm">
<div class="flex gap-4"><div class="h-20 w-20 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400"><?php if($product['photo']):?><img src="<?=APP_URL?>/uploads/<?=e($product['photo'])?>" class="h-20 w-20 rounded-xl object-cover"><?php else:?>No foto<?php endif;?></div>
<div><h3 class="text-lg font-semibold"><?=e($product['name'])?></h3><p class="text-xs text-slate-500">SKU <?=e($product['sku'])?> • Barcode <?=e($product['barcode']??'-')?> • <?=e($product['cat_name']??'-')?></p>
<p class="mt-2 text-2xl font-bold text-emerald-600"><?=rupiah($product['selling_price'])?></p>
<?php if($is_admin):?><p class="text-xs text-slate-500">Beli <?=rupiah($product['purchase_price'])?> • Grosir <?=rupiah($product['wholesale_price'])?> • Margin <?=rupiah($product['selling_price']-$product['purchase_price'])?></p><?php endif;?>
<p class="text-xs mt-1">Stok: <span class="<?=$product['stock']<= $product['min_stock']?'text-rose-600 font-bold':'text-emerald-600'?>"><?=$product['stock']?> (min <?=$product['min_stock']?>)</span> • <?=e($product['unit_name']??'')?> • Lokasi <?=e($product['location']??'-')?></p>
<p class="text-xs"><?=$product['is_active']?'<span class="text-emerald-600">Aktif</span>':'<span class="text-rose-600">Nonaktif</span>'?></p>
</div></div>
</div>
<?php endif;?>
</section></main>
<?php include __DIR__.'/../components/footer.php';?></body></html>
