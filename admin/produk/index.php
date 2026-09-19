<?php
require_once __DIR__.'/../../config/app.php';
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../core/auth.php';
require_once __DIR__.'/../../core/helper.php';
require_once __DIR__.'/../../core/csrf.php';
require_once __DIR__.'/../../core/audit.php';
require_role(['admin','manager','owner']);
$pdo=db();
$cats=$pdo->query("SELECT * FROM product_categories WHERE is_active=1 ORDER BY name")->fetchAll();
$units=$pdo->query("SELECT * FROM product_units ORDER BY name")->fetchAll();
$sups=$pdo->query("SELECT * FROM suppliers WHERE is_active=1 ORDER BY name")->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!csrf_verify($_POST['_csrf']??'')){flash_set('error','CSRF tidak valid'); redirect($_SERVER['REQUEST_URI']);}
 $act=$_POST['act']??'';
 $sku=trim($_POST['sku']??''); $barcode=trim($_POST['barcode']??''); $name=trim($_POST['name']??'');
 $cat = $_POST['category_id']? (int)$_POST['category_id']:null;
 $unit = $_POST['unit_id']? (int)$_POST['unit_id']:null;
 $sup = $_POST['supplier_id']? (int)$_POST['supplier_id']:null;
 $hb = parse_rupiah($_POST['purchase_price']??0); $hj=parse_rupiah($_POST['selling_price']??0); $hg=parse_rupiah($_POST['wholesale_price']??0);
 $st = (int)($_POST['stock']??0); $min=(int)($_POST['min_stock']??5); $loc=trim($_POST['location']??''); $active=(int)($_POST['is_active']??1);
 if($act==='create'){
   if($name===''||$hj<=0){flash_set('error','Nama & harga jual wajib'); redirect($_SERVER['REQUEST_URI']);}
   $nextId=(int)$pdo->query("SELECT COALESCE(MAX(id),0)+1 FROM products")->fetchColumn();
   $sku=gen_code('SKU', $nextId, 4);
   $chk=$pdo->prepare("SELECT COUNT(*) FROM products WHERE sku=?"); $chk->execute([$sku]); while($chk->fetchColumn()>0){ $nextId++; $sku=gen_code('SKU', $nextId, 4); $chk->execute([$sku]); }
   $barcode='899'.str_pad((string)mt_rand(0,9999999999),10,'0',STR_PAD_LEFT);
   $chkB=$pdo->prepare("SELECT COUNT(*) FROM products WHERE barcode=?"); $chkB->execute([$barcode]); while($chkB->fetchColumn()>0){ $barcode='899'.str_pad((string)mt_rand(0,9999999999),10,'0',STR_PAD_LEFT); $chkB->execute([$barcode]); }
   $photo=null;
  if(!empty($_FILES['photo']['name'])){
    $ext=strtolower(pathinfo($_FILES['photo']['name'],PATHINFO_EXTENSION));
    $allowed=['jpg','jpeg','png','webp']; $mime=mime_content_type($_FILES['photo']['tmp_name']);
    if(!in_array($ext,$allowed) || strpos($mime,'image')===false){flash_set('error','Foto harus jpg/png/webp'); redirect($_SERVER['REQUEST_URI']);}
    if($_FILES['photo']['size']> UPLOAD_MAX_MB*1024*1024){flash_set('error','Foto max '.UPLOAD_MAX_MB.'MB'); redirect($_SERVER['REQUEST_URI']);}
    $fname='prod_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
    if(!is_dir(__DIR__.'/../../uploads')) mkdir(__DIR__.'/../../uploads',0777,true);
    move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__.'/../../uploads/'.$fname); $photo=$fname;
  }
  try{
    $pdo->prepare("INSERT INTO products (sku,barcode,name,category_id,unit_id,purchase_price,selling_price,wholesale_price,stock,min_stock,location,supplier_id,photo,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
    ->execute([$sku,$barcode?:null,$name,$cat,$unit,$hb,$hj,$hg,$st,$min,$loc,$sup,$photo,$active]);
    if($st>0) $pdo->prepare("INSERT INTO stock_movements (product_id,type,qty_change,stock_before,stock_after,notes,created_by) VALUES (?, 'ADJUSTMENT', ?, 0, ?, 'Stok awal', ?)")->execute([$pdo->lastInsertId(),$st,$st, current_user()['id']]);
    audit('CREATE_PRODUCT','products',$pdo->lastInsertId(),"Buat produk $sku"); flash_set('success','Produk ditambahkan');
  }catch(PDOException $e){flash_set('error','SKU/Barcode sudah ada: '.$e->getMessage());}
  redirect($_SERVER['REQUEST_URI']);
 } elseif($act==='update'){
   $id=(int)$_POST['id']; $old=$pdo->prepare("SELECT * FROM products WHERE id=?"); $old->execute([$id]); $o=$old->fetch();
   if(!$o){flash_set('error','Produk tidak ditemukan'); redirect($_SERVER['REQUEST_URI']);}
   $sku=$o['sku']; $barcode=$o['barcode'];
   $photo=$o['photo'];
  if(!empty($_FILES['photo']['name'])){
    $ext=strtolower(pathinfo($_FILES['photo']['name'],PATHINFO_EXTENSION)); $allowed=['jpg','jpeg','png','webp']; $mime=mime_content_type($_FILES['photo']['tmp_name']);
    if(!in_array($ext,$allowed) || strpos($mime,'image')===false){flash_set('error','Foto harus image'); redirect($_SERVER['REQUEST_URI']);}
    $fname='prod_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext; if(!is_dir(__DIR__.'/../../uploads')) mkdir(__DIR__.'/../../uploads',0777,true);
    move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__.'/../../uploads/'.$fname); $photo=$fname;
  }
  if($o['selling_price']!=$hj){
    $pdo->prepare("INSERT INTO product_price_history (product_id,old_price,new_price,changed_by,reason) VALUES (?,?,?,?,?)")->execute([$id,$o['selling_price'],$hj, current_user()['id'],'Update produk']);
  }
  $pdo->prepare("UPDATE products SET sku=?,barcode=?,name=?,category_id=?,unit_id=?,purchase_price=?,selling_price=?,wholesale_price=?,min_stock=?,location=?,supplier_id=?,photo=?,is_active=? WHERE id=?")
  ->execute([$sku,$barcode?:null,$name,$cat,$unit,$hb,$hj,$hg,$min,$loc,$sup,$photo,$active,$id]);
  if($st!=$o['stock']){
    $diff=$st-$o['stock']; $pdo->prepare("UPDATE products SET stock=? WHERE id=?")->execute([$st,$id]);
    $pdo->prepare("INSERT INTO stock_movements (product_id,type,qty_change,stock_before,stock_after,notes,created_by) VALUES (?,?,?,?,?,?,?)")->execute([$id,'ADJUSTMENT',$diff,$o['stock'],$st,'Koreksi stok via master', current_user()['id']]);
  }
  audit('UPDATE_PRODUCT','products',$id,"Update produk $sku", $o, $_POST); flash_set('success','Produk diupdate'); redirect($_SERVER['REQUEST_URI']);
 } elseif($act==='delete'){
  $id=(int)$_POST['id'];
  $cnt=$pdo->prepare("SELECT COUNT(*) FROM sale_items WHERE product_id=?"); $cnt->execute([$id]);
  if($cnt->fetchColumn()>0){ $pdo->prepare("UPDATE products SET is_active=0 WHERE id=?")->execute([$id]); flash_set('success','Produk dinonaktifkan (sudah dipakai transaksi)'); }
  else { $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]); flash_set('success','Produk dihapus'); }
  audit('DELETE_PRODUCT','products',$id,'hapus/nonaktif'); redirect($_SERVER['REQUEST_URI']);
 }
}
$q=trim($_GET['q']??''); $catF=$_GET['cat']??''; $page=max(1,(int)($_GET['page']??1)); $per=ITEMS_PER_PAGE;
$where="WHERE 1"; $par=[];
if($q!==''){ $where.=" AND (p.sku LIKE ? OR p.barcode LIKE ? OR p.name LIKE ?)"; $par[]="%$q%"; $par[]="%$q%"; $par[]="%$q%"; }
if($catF!==''){ $where.=" AND p.category_id=?"; $par[]=(int)$catF; }
$total=$pdo->prepare("SELECT COUNT(*) FROM products p $where"); $total->execute($par); $total=(int)$total->fetchColumn();
list($pages,$page,$off)=paginate_params($total,$page,$per);
$stmt=$pdo->prepare("SELECT p.*, c.name as cat_name, u.name as unit_name FROM products p LEFT JOIN product_categories c ON c.id=p.category_id LEFT JOIN product_units u ON u.id=p.unit_id $where ORDER BY p.id DESC LIMIT $per OFFSET $off"); $stmt->execute($par); $rows=$stmt->fetchAll();
?>
<!DOCTYPE html><html lang="id"><head><title>Produk • <?=e((store_info()['name'] ?? APP_NAME))?></title><?php include __DIR__.'/../../components/head.php'; ?></head>
<body class="min-h-screen bg-slate-50 flex flex-col"><?php include __DIR__.'/../../components/header.php';?>
<main class="flex w-full flex-1 flex-col gap-6 px-4 pb-10 pt-5 sm:px-6 lg:px-8 lg:flex-row lg:items-start"><?php include __DIR__.'/../../components/sidebar_admin.php';?>
<section class="order-2 flex-1 space-y-4">
<nav class="text-[11px] text-slate-500">Dashboard / Produk</nav>
<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><h2 class="text-lg font-semibold">Master Produk</h2><div class="flex gap-2"><button data-modal-toggle="#modalAdd" class="rounded-full bg-emerald-600 px-4 py-1.5 text-xs text-white">+ Tambah Produk</button><a href="<?=url('/api/products')?>?export=csv" class="rounded-full border bg-white px-4 py-1.5 text-xs">Export CSV</a></div></div>
<form method="GET" class="rounded-2xl border bg-white p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 text-xs"><input name="q" value="<?=e($q)?>" placeholder="Cari SKU/barcode/nama" class="rounded-xl border px-3 py-2 sm:col-span-2 lg:col-span-1"><select name="cat" class="rounded-xl border px-3 py-2"><option value="">Semua kategori</option><?php foreach($cats as $c):?><option value="<?=$c['id']?>" <?=$catF==$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select><button class="justify-self-start rounded-xl bg-emerald-600 px-4 py-2 text-white font-medium">Cari</button></form>
<div class="rounded-2xl border bg-white p-4 shadow-sm"><div class="overflow-auto rounded-xl border"><table class="min-w-full divide-y text-xs"><thead class="bg-slate-100"><tr><th class="px-2 py-2 text-left">SKU</th><th class="px-2 py-2 text-left">Produk</th><th class="px-2 py-2 text-right">Beli</th><th class="px-2 py-2 text-right">Jual</th><th class="px-2 py-2 text-center">Stok</th><th class="px-2 py-2 text-center">Status</th><th class="px-2 py-2 text-center">Aksi</th></tr></thead><tbody class="divide-y">
<?php foreach($rows as $r):?><tr class="hover:bg-slate-50"><td class="px-2 py-2 font-mono text-[11px]"><?=e($r['sku'])?><br><span class="text-slate-400"><?=e($r['barcode']??'-')?></span></td><td class="px-2 py-2"><div class="flex items-center gap-2"><?php if(!empty($r['photo']) && file_exists(__DIR__.'/../../uploads/'.$r['photo'])):?><img src="<?=APP_URL?>/uploads/<?=e($r['photo'])?>" alt="<?=e($r['name'])?>" class="h-9 w-9 shrink-0 rounded-lg object-cover ring-1 ring-slate-200"><?php else:?><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-300 ring-1 ring-slate-200"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></span><?php endif;?><div><div class="font-medium"><?=e($r['name'])?></div><div class="text-[11px] text-slate-500"><?=e($r['cat_name']??'-')?> • <?=e($r['unit_name']??'-')?> • <?=e($r['location']??'-')?></div></div></div></td><td class="px-2 py-2 text-right"><?=rupiah($r['purchase_price'])?></td><td class="px-2 py-2 text-right font-semibold"><?=rupiah($r['selling_price'])?><br><span class="text-[10px] text-slate-400">Grosir <?=rupiah($r['wholesale_price'])?></span></td><td class="px-2 py-2 text-center <?=$r['stock']<=$r['min_stock']?'text-rose-600 font-bold':''?>"><?=$r['stock']?><br><span class="text-[10px] text-slate-400">min <?=$r['min_stock']?></span></td><td class="px-2 py-2 text-center"><?=$r['is_active']?'<span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] text-emerald-600 ring-1 ring-emerald-200">Aktif</span>':'<span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px]">Nonaktif</span>'?></td>
<td class="px-2 py-2 text-center"><div class="flex justify-center gap-1"><button type="button" data-modal-toggle="#modalEdit<?=$r['id']?>" title="Edit" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:border-emerald-500 hover:bg-emerald-50 hover:text-emerald-600"><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button><form method="POST" class="inline" onsubmit="return confirm('Hapus/nonaktifkan?')"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button title="Hapus / Nonaktifkan" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100"><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form></div></td></tr>
<?php endforeach; if(!$rows) echo '<tr><td colspan="7" class="px-3 py-6 text-center text-slate-400">Belum ada produk</td></tr>';?>
</tbody></table></div>
<div class="mt-3 flex justify-between text-xs text-slate-500"><span><?=$total?> produk • hal <?=$page?>/<?=$pages?></span><div class="flex gap-1"><?php for($i=1;$i<=$pages;$i++):?><a href="?q=<?=urlencode($q)?>&cat=<?=$catF?>&page=<?=$i?>" class="rounded-full border px-3 py-1 <?=$i==$page?'bg-emerald-600 text-white':''?>"><?=$i?></a><?php endfor;?></div></div>
</div></section></main>

<!-- Modals Edit Produk -->
<?php foreach($rows as $r):?>
<div id="modalEdit<?=$r['id']?>" class="modal-dashboard hidden"><div class="modal-dialog flex max-w-[650px] w-full"><div class="modal-content w-full flex flex-col"><div class="flex items-center justify-between mb-3 shrink-0"><h3 class="text-sm font-semibold text-slate-800">Edit Produk</h3><button type="button" data-modal-hide="#modalEdit<?=$r['id']?>" class="btn-close"></button></div>
<form method="POST" enctype="multipart/form-data" class="grid gap-2 sm:grid-cols-2 flex-1"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="update"><input type="hidden" name="id" value="<?=$r['id']?>">
<div class="sm:col-span-2"><label class="block text-[10px] font-medium text-slate-500 mb-1">Nama Produk</label><input name="name" value="<?=e($r['name'])?>" required placeholder="Nama produk" class="w-full rounded-xl border px-3 py-2 text-xs"></div>
<div class="sm:col-span-1"><label class="block text-[10px] font-medium text-slate-500 mb-1">Kategori</label><select name="category_id" class="w-full rounded-xl border px-3 py-2 text-xs"><option value="">- Kategori -</option><?php foreach($cats as $c):?><option value="<?=$c['id']?>" <?=$r['category_id']==$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select></div>
<div class="sm:col-span-1"><label class="block text-[10px] font-medium text-slate-500 mb-1">Satuan</label><select name="unit_id" class="w-full rounded-xl border px-3 py-2 text-xs"><option value="">- Satuan -</option><?php foreach($units as $u):?><option value="<?=$u['id']?>" <?=$r['unit_id']==$u['id']?'selected':''?>><?=e($u['name'])?></option><?php endforeach;?></select></div>
<div class="sm:col-span-1"><label class="block text-[10px] font-medium text-slate-500 mb-1">Harga Beli</label><input name="purchase_price" value="<?=e($r['purchase_price'])?>" placeholder="0" class="w-full rounded-xl border px-3 py-2 text-xs"></div><div class="sm:col-span-1"><label class="block text-[10px] font-medium text-slate-500 mb-1">Harga Jual</label><input name="selling_price" value="<?=e($r['selling_price'])?>" required placeholder="0" class="w-full rounded-xl border px-3 py-2 text-xs"></div>
<div class="sm:col-span-1"><label class="block text-[10px] font-medium text-slate-500 mb-1">Harga Grosir</label><input name="wholesale_price" value="<?=e($r['wholesale_price'])?>" placeholder="0" class="w-full rounded-xl border px-3 py-2 text-xs"></div><div class="sm:col-span-1"><label class="block text-[10px] font-medium text-slate-500 mb-1">Stok</label><input name="stock" type="number" value="<?=e($r['stock'])?>" placeholder="0" class="w-full rounded-xl border px-3 py-2 text-xs"></div>
<div class="sm:col-span-1"><label class="block text-[10px] font-medium text-slate-500 mb-1">Min Stok</label><input name="min_stock" type="number" value="<?=e($r['min_stock'])?>" placeholder="5" class="w-full rounded-xl border px-3 py-2 text-xs"></div><div class="sm:col-span-1"><label class="block text-[10px] font-medium text-slate-500 mb-1">Lokasi / Rak</label><input name="location" value="<?=e($r['location'])?>" placeholder="RAK-01" class="w-full rounded-xl border px-3 py-2 text-xs"></div>
<div class="sm:col-span-1"><label class="block text-[10px] font-medium text-slate-500 mb-1">Supplier</label><select name="supplier_id" class="w-full rounded-xl border px-3 py-2 text-xs"><option value="">- Supplier -</option><?php foreach($sups as $s):?><option value="<?=$s['id']?>" <?=$r['supplier_id']==$s['id']?'selected':''?>><?=e($s['name'])?></option><?php endforeach;?></select></div>
<div class="sm:col-span-1"><label class="block text-[10px] font-medium text-slate-500 mb-1">Status</label><select name="is_active" class="w-full rounded-xl border px-3 py-2 text-xs"><option value="1" <?=$r['is_active']?'selected':''?>>Aktif</option><option value="0" <?=!$r['is_active']?'selected':''?>>Nonaktif</option></select></div>
<div class="sm:col-span-2"><label class="block text-[10px] font-medium text-slate-500 mb-1">Foto Produk</label><input type="file" name="photo" accept="image/*" class="w-full rounded-xl border px-3 py-2 text-xs bg-white file:mr-3 file:rounded-full file:border-0 file:bg-emerald-600 file:px-3 file:py-1 file:text-xs file:text-white hover:file:bg-emerald-700"></div>
<div class="sm:col-span-2 pt-1"><button type="submit" class="w-full rounded-full bg-emerald-600 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 transition">Simpan Perubahan</button></div></form></div></div></div>
<?php endforeach;?>
<div id="modalAdd" class="modal-dashboard hidden"><div class="modal-dialog flex max-w-[650px] w-full"><div class="modal-content w-full flex flex-col"><div class="flex items-center justify-between mb-3 shrink-0"><h3 class="text-sm font-semibold text-slate-800">Tambah Produk</h3><button type="button" data-modal-hide="#modalAdd" class="btn-close"></button></div>
<div class="sm:col-span-2 mb-1 rounded-xl bg-emerald-50 border border-emerald-200 px-3 py-2 text-[11px] text-emerald-700 flex items-center gap-2"><svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> SKU & Barcode otomatis digenerate sistem</div>
<form method="POST" enctype="multipart/form-data" class="grid gap-2 sm:grid-cols-2 flex-1"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="create">
<div class="sm:col-span-2"><label class="block text-[10px] font-medium text-slate-500 mb-1">Nama Produk</label><input name="name" required placeholder="Nama produk" class="w-full rounded-xl border px-3 py-2 text-xs"></div>
<div class="sm:col-span-1"><label class="block text-[10px] font-medium text-slate-500 mb-1">Kategori</label><select name="category_id" class="w-full rounded-xl border px-3 py-2 text-xs"><option value="">- Kategori -</option><?php foreach($cats as $c):?><option value="<?=$c['id']?>"><?=e($c['name'])?></option><?php endforeach;?></select></div>
<div class="sm:col-span-1"><label class="block text-[10px] font-medium text-slate-500 mb-1">Satuan</label><select name="unit_id" class="w-full rounded-xl border px-3 py-2 text-xs"><option value="">- Satuan -</option><?php foreach($units as $u):?><option value="<?=$u['id']?>"><?=e($u['name'])?></option><?php endforeach;?></select></div>
<div class="sm:col-span-1"><label class="block text-[10px] font-medium text-slate-500 mb-1">Harga Beli</label><input name="purchase_price" placeholder="0" class="w-full rounded-xl border px-3 py-2 text-xs"></div><div class="sm:col-span-1"><label class="block text-[10px] font-medium text-slate-500 mb-1">Harga Jual</label><input name="selling_price" required placeholder="0" class="w-full rounded-xl border px-3 py-2 text-xs"></div>
<div class="sm:col-span-1"><label class="block text-[10px] font-medium text-slate-500 mb-1">Harga Grosir</label><input name="wholesale_price" placeholder="0" class="w-full rounded-xl border px-3 py-2 text-xs"></div><div class="sm:col-span-1"><label class="block text-[10px] font-medium text-slate-500 mb-1">Stok</label><input name="stock" type="number" value="0" placeholder="0" class="w-full rounded-xl border px-3 py-2 text-xs"></div>
<div class="sm:col-span-1"><label class="block text-[10px] font-medium text-slate-500 mb-1">Min Stok</label><input name="min_stock" type="number" value="5" placeholder="5" class="w-full rounded-xl border px-3 py-2 text-xs"></div><div class="sm:col-span-1"><label class="block text-[10px] font-medium text-slate-500 mb-1">Lokasi / Rak</label><input name="location" placeholder="RAK-01" class="w-full rounded-xl border px-3 py-2 text-xs"></div>
<div class="sm:col-span-1"><label class="block text-[10px] font-medium text-slate-500 mb-1">Supplier</label><select name="supplier_id" class="w-full rounded-xl border px-3 py-2 text-xs"><option value="">- Supplier -</option><?php foreach($sups as $s):?><option value="<?=$s['id']?>"><?=e($s['name'])?></option><?php endforeach;?></select></div>
<div class="sm:col-span-1"><label class="block text-[10px] font-medium text-slate-500 mb-1">Status</label><select name="is_active" class="w-full rounded-xl border px-3 py-2 text-xs"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
<div class="sm:col-span-2"><label class="block text-[10px] font-medium text-slate-500 mb-1">Foto Produk</label><input type="file" name="photo" accept="image/*" class="w-full rounded-xl border px-3 py-2 text-xs bg-white file:mr-3 file:rounded-full file:border-0 file:bg-emerald-600 file:px-3 file:py-1 file:text-xs file:text-white hover:file:bg-emerald-700"></div>
<div class="sm:col-span-2 pt-1"><button type="submit" class="w-full rounded-full bg-emerald-600 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 transition">Simpan Produk</button></div></form></div></div></div>
<?php include __DIR__.'/../../components/footer.php';?></body></html>
