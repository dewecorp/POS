<?php
require_once __DIR__.'/../../config/app.php';
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../core/auth.php';
require_once __DIR__.'/../../core/helper.php';
require_once __DIR__.'/../../core/csrf.php';
require_once __DIR__.'/../../core/audit.php';
require_role(['admin','manager','owner']);
$pdo=db();
$products=$pdo->query("SELECT id,sku,name,stock,min_stock FROM products ORDER BY name")->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['act']??'')==='adjust'){
 if(!csrf_verify($_POST['_csrf']??'')){flash_set('error','CSRF tidak valid'); redirect($_SERVER['REQUEST_URI']);}
 $pid=(int)$_POST['product_id']; $qty=(int)$_POST['qty_change']; $notes=trim($_POST['notes']??'');
 $p=$pdo->prepare("SELECT stock FROM products WHERE id=? FOR UPDATE"); $pdo->beginTransaction(); $p->execute([$pid]); $stock=(int)$p->fetchColumn();
 $new=$stock+$qty;
 if(!ALLOW_NEGATIVE_STOCK && $new<0){ $pdo->rollBack(); flash_set('error','Stok tidak boleh negatif'); redirect($_SERVER['REQUEST_URI']);}
 $pdo->prepare("UPDATE products SET stock=? WHERE id=?")->execute([$new,$pid]);
 $pdo->prepare("INSERT INTO stock_movements (product_id,type,qty_change,stock_before,stock_after,notes,created_by) VALUES (?,?,?,?,?,?,?)")->execute([$pid,'ADJUSTMENT',$qty,$stock,$new,$notes, current_user()['id']]);
 $pdo->commit(); audit('UPDATE_STOCK','inventory',$pid,"Adjust stok $qty"); flash_set('success','Stok diadjust');
 redirect($_SERVER['REQUEST_URI']);
}
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['act']??'')==='opname_create'){
 if(!csrf_verify($_POST['_csrf']??'')){flash_set('error','CSRF tidak valid'); redirect($_SERVER['REQUEST_URI']);}
 $code='OP-'.date('YmdHis'); $pids=$_POST['op_product']??[]; $phys=$_POST['op_physical']??[];
 $pdo->beginTransaction();
 $pdo->prepare("INSERT INTO stock_opnames (code,user_id,status) VALUES (?,?, 'draft')")->execute([$code, current_user()['id']]);
 $oid=$pdo->lastInsertId();
 foreach($pids as $i=>$pid){
   $pid=(int)$pid; $ph=(int)($phys[$i]??0);
   $s=$pdo->prepare("SELECT stock FROM products WHERE id=? FOR UPDATE"); $s->execute([$pid]); $sys=(int)$s->fetchColumn();
   $diff=$ph-$sys;
   $pdo->prepare("INSERT INTO stock_opname_items (opname_id,product_id,system_stock,physical_stock,difference) VALUES (?,?,?,?,?)")->execute([$oid,$pid,$sys,$ph,$diff]);
 }
 $pdo->commit(); flash_set('success','Opname dibuat: '.$code); redirect($_SERVER['REQUEST_URI']);
}
if(isset($_GET['approve'])){
 $id=(int)$_GET['approve']; if(!csrf_verify($_GET['_csrf']??'')){flash_set('error','CSRF tidak valid'); redirect('index.php');}
 $op=$pdo->prepare("SELECT * FROM stock_opnames WHERE id=?"); $op->execute([$id]); $op=$op->fetch();
 if($op && $op['status']=='draft'){
   $pdo->beginTransaction();
   $items=$pdo->prepare("SELECT * FROM stock_opname_items WHERE opname_id=?"); $items->execute([$id]); $its=$items->fetchAll();
   foreach($its as $it){
     $pdo->prepare("UPDATE products SET stock=? WHERE id=?")->execute([$it['physical_stock'],$it['product_id']]);
     $pdo->prepare("INSERT INTO stock_movements (product_id,type,reference_type,reference_id,qty_change,stock_before,stock_after,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?)")->execute([$it['product_id'],'STOCK_OPNAME','opname',$id,$it['difference'],$it['system_stock'],$it['physical_stock'],"Opname ". $op['code'], current_user()['id']]);
   }
   $pdo->prepare("UPDATE stock_opnames SET status='approved', approved_at=NOW() WHERE id=?")->execute([$id]);
   $pdo->commit(); audit('STOCK_OPNAME','inventory',$id,"Approve opname ".$op['code']); flash_set('success','Opname disahkan, stok diperbarui');
 }
 redirect('index.php');
}
$q=trim($_GET['q']??''); $type=$_GET['type']??''; $page=max(1,(int)($_GET['page']??1)); $per=15;
$where="WHERE 1"; $par=[];
if($q!==''){ $where.=" AND (p.name LIKE ? OR p.sku LIKE ?)"; $par[]="%$q%"; $par[]="%$q%"; }
if($type && in_array($type,['PURCHASE','SALE','RETURN_SALE','ADJUSTMENT','STOCK_OPNAME','CORRECTION'])){ $where.=" AND sm.type=?"; $par[]=$type; }
$total=$pdo->prepare("SELECT COUNT(*) FROM stock_movements sm JOIN products p ON p.id=sm.product_id $where"); $total->execute($par); $total=(int)$total->fetchColumn();
list($pages,$page,$off)=paginate_params($total,$page,$per);
$stmt=$pdo->prepare("SELECT sm.*, p.name, p.sku FROM stock_movements sm JOIN products p ON p.id=sm.product_id $where ORDER BY sm.id DESC LIMIT $per OFFSET $off"); $stmt->execute($par); $moves=$stmt->fetchAll();
$opnames=$pdo->query("SELECT o.*, u.name as user FROM stock_opnames o JOIN users u ON u.id=o.user_id ORDER BY o.id DESC LIMIT 10")->fetchAll();
$low=$pdo->query("SELECT * FROM products WHERE stock <= min_stock AND is_active=1 ORDER BY stock ASC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html><html lang="id"><head><title>Stok • <?=e((store_info()['name'] ?? APP_NAME))?></title><?php include __DIR__.'/../../components/head.php'; ?></head>
<body class="min-h-screen bg-slate-50 flex flex-col"><?php include __DIR__.'/../../components/header.php';?>
<main class="flex w-full flex-1 flex-col gap-6 px-4 pb-10 pt-5 sm:px-6 lg:px-8 lg:flex-row lg:items-start"><?php include __DIR__.'/../../components/sidebar_admin.php';?>
<section class="order-2 flex-1 space-y-4">
<nav class="text-[11px] text-slate-500">Dashboard / Stok</nav>
<h2 class="text-lg font-semibold">Manajemen Stok</h2>
<?php if($low):?><div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-xs text-amber-700">⚠ <?=count($low)?> produk menipis: <?php foreach($low as $l) echo e($l['name'])." (".$l['stock'].") ";?></div><?php endif;?>
<div class="grid lg:grid-cols-2 gap-4">
<div class="rounded-2xl border bg-white p-4 shadow-sm"><h3 class="text-xs font-semibold mb-2">Adjust Stok</h3><form method="POST" class="space-y-2"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="adjust"><select name="product_id" required class="w-full rounded-xl border px-3 py-2 text-xs"><?php foreach($products as $p):?><option value="<?=$p['id']?>"><?=e($p['sku'])?> - <?=e($p['name'])?> (stok <?=$p['stock']?>)</option><?php endforeach;?></select><input name="qty_change" type="number" required placeholder="Qty change (+10 / -5)" class="w-full rounded-xl border px-3 py-2 text-xs"><input name="notes" placeholder="Keterangan" class="w-full rounded-xl border px-3 py-2 text-xs"><button class="w-full rounded-xl bg-emerald-600 py-2 text-xs text-white">Simpan Adjust</button></form></div>
<div class="rounded-2xl border bg-white p-4 shadow-sm"><h3 class="text-xs font-semibold mb-2">Buat Stock Opname</h3><form method="POST" id="opForm" class="space-y-2"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="opname_create"><div id="opItems" class="space-y-1"></div><button type="button" onclick="addOp()" class="rounded-full border px-3 py-1 text-xs">+ Baris</button><button class="w-full rounded-xl bg-emerald-600 py-2 text-xs text-white">Buat Opname (draft)</button></form></div>
</div>
<div class="rounded-2xl border bg-white p-4 shadow-sm"><h3 class="text-xs font-semibold mb-2">Opname Terbaru</h3><div class="overflow-auto rounded-xl border"><table class="min-w-full divide-y text-xs"><thead class="bg-slate-100"><tr><th class="px-2 py-2">Kode</th><th class="px-2 py-2">Oleh</th><th class="px-2 py-2">Status</th><th class="px-2 py-2">Aksi</th></tr></thead><tbody class="divide-y"><?php foreach($opnames as $o):?><tr><td class="px-2 py-2 font-mono"><?=e($o['code'])?></td><td class="px-2 py-2"><?=e($o['user'])?></td><td class="px-2 py-2"><?=e($o['status'])?></td><td class="px-2 py-2"><?php if($o['status']=='draft'):?><a href="?approve=<?=$o['id']?>&_csrf=<?=e(csrf_token())?>" onclick="return confirm('Sahkan opname? Stok akan diperbarui')" title="Sahkan Opname" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-600 hover:bg-emerald-100"><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></a><?php else:?><span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-600 ring-1 ring-emerald-500/20">Approved</span><?php endif;?></td></tr><?php endforeach;?></tbody></table></div></div>
<form method="GET" class="rounded-2xl border bg-white p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 text-xs"><input name="q" value="<?=e($q)?>" placeholder="Cari produk" class="rounded-xl border px-3 py-2 sm:col-span-2 lg:col-span-1"><select name="type" class="rounded-xl border px-3 py-2"><option value="">Semua tipe</option><option value="PURCHASE" <?=$type=='PURCHASE'?'selected':''?>>PURCHASE</option><option value="SALE" <?=$type=='SALE'?'selected':''?>>SALE</option><option value="ADJUSTMENT" <?=$type=='ADJUSTMENT'?'selected':''?>>ADJUSTMENT</option><option value="STOCK_OPNAME" <?=$type=='STOCK_OPNAME'?'selected':''?>>OPNAME</option><option value="CORRECTION" <?=$type=='CORRECTION'?'selected':''?>>CORRECTION</option></select><button class="justify-self-start rounded-xl bg-emerald-600 px-4 py-2 text-white font-medium">Filter</button></form>
<div class="rounded-2xl border bg-white p-4 shadow-sm"><div class="overflow-auto rounded-xl border"><table class="min-w-full divide-y text-xs"><thead class="bg-slate-100"><tr><th class="px-2 py-2">Produk</th><th class="px-2 py-2">Tipe</th><th class="px-2 py-2 text-center">Qty</th><th class="px-2 py-2">Before→After</th><th class="px-2 py-2">Tanggal</th></tr></thead><tbody class="divide-y"><?php foreach($moves as $m):?><tr><td class="px-2 py-2"><?=e($m['sku'])?> <?=e($m['name'])?></td><td class="px-2 py-2"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px]"><?=e($m['type'])?></span></td><td class="px-2 py-2 text-center <?= $m['qty_change']>0?'text-emerald-600':'text-rose-600'?>"><?= $m['qty_change']>0?'+':''?><?=$m['qty_change']?></td><td class="px-2 py-2"><?=$m['stock_before']?> → <?=$m['stock_after']?></td><td class="px-2 py-2 text-[11px]"><?=e($m['created_at'])?></td></tr><?php endforeach; if(!$moves) echo '<tr><td colspan="5" class="px-3 py-6 text-center text-slate-400">Belum ada movement</td></tr>';?></tbody></table></div>
<div class="mt-3 flex justify-between text-xs text-slate-500"><span><?=$total?> movement</span><div class="flex gap-1"><?php for($i=1;$i<=$pages;$i++):?><a href="?q=<?=urlencode($q)?>&type=<?=$type?>&page=<?=$i?>" class="rounded-full border px-3 py-1 <?=$i==$page?'bg-emerald-600 text-white':''?>"><?=$i?></a><?php endfor;?></div></div>
</div>
</section></main>
<script>
const prods=<?=json_encode($products)?>;
function addOp(){
 const d=document.createElement('div'); d.className='grid grid-cols-12 gap-1';
 let opts=prods.map(p=>`<option value="${p.id}">${p.sku} ${p.name} (sys ${p.stock})</option>`).join('');
 d.innerHTML=`<select name="op_product[]" class="col-span-7 rounded-xl border px-2 py-2 text-xs">${opts}</select><input name="op_physical[]" type="number" placeholder="Fisik" class="col-span-4 rounded-xl border px-2 py-2 text-xs"><button type="button" onclick="this.parentElement.remove()" class="col-span-1 text-rose-500">×</button>`;
 document.getElementById('opItems').appendChild(d);
}
addOp();
</script>
<?php include __DIR__.'/../../components/footer.php';?></body></html>
