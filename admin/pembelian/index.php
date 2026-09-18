<?php
require_once __DIR__.'/../../config/app.php';
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../core/auth.php';
require_once __DIR__.'/../../core/helper.php';
require_once __DIR__.'/../../core/csrf.php';
require_once __DIR__.'/../../core/audit.php';
require_role(['admin','manager','owner']);
$pdo=db();
$suppliers=$pdo->query("SELECT * FROM suppliers WHERE is_active=1 ORDER BY name")->fetchAll();
$products=$pdo->query("SELECT id,sku,name,stock FROM products WHERE is_active=1 ORDER BY name")->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!csrf_verify($_POST['_csrf']??'')){flash_set('error','CSRF tidak valid'); redirect($_SERVER['REQUEST_URI']);}
 $supplier_id=(int)($_POST['supplier_id']??0); $invoice=trim($_POST['invoice_number']??''); $date=$_POST['purchase_date']??date('Y-m-d');
 $pids=$_POST['product_id']??[]; $qtys=$_POST['qty']??[]; $prices=$_POST['price']??[];
 if($supplier_id==0||$invoice===''||empty($pids)){flash_set('error','Lengkapi supplier, faktur, dan item'); redirect($_SERVER['REQUEST_URI']);}
 try{
  $pdo->beginTransaction();
  $total=0; $items=[];
  foreach($pids as $i=>$pid){
    $pid=(int)$pid; $q=(int)($qtys[$i]??0); $pr=parse_rupiah($prices[$i]??0);
    if($pid==0||$q<=0||$pr<=0) continue;
    $p=$pdo->prepare("SELECT stock FROM products WHERE id=? FOR UPDATE"); $p->execute([$pid]); $stock=(int)$p->fetchColumn();
    $sub=$q*$pr; $total+=$sub; $items[]=['pid'=>$pid,'qty'=>$q,'price'=>$pr,'sub'=>$sub,'before'=>$stock,'after'=>$stock+$q];
  }
  if(empty($items)) throw new Exception('Item tidak valid');
  $pdo->prepare("INSERT INTO purchases (invoice_number,supplier_id,user_id,total,purchase_date) VALUES (?,?,?,?,?)")->execute([$invoice,$supplier_id,current_user()['id'],$total,$date]);
  $pid=$pdo->lastInsertId();
  foreach($items as $it){
    $pdo->prepare("INSERT INTO purchase_items (purchase_id,product_id,qty,price,subtotal) VALUES (?,?,?,?,?)")->execute([$pid,$it['pid'],$it['qty'],$it['price'],$it['sub']]);
    $pdo->prepare("UPDATE products SET stock=? WHERE id=?")->execute([$it['after'],$it['pid']]);
    $pdo->prepare("INSERT INTO stock_movements (product_id,type,reference_type,reference_id,qty_change,stock_before,stock_after,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?)")->execute([$it['pid'],'PURCHASE','purchase',$pid,$it['qty'],$it['before'],$it['after'],"Pembelian $invoice", current_user()['id']]);
  }
  $pdo->commit(); audit('CREATE_PURCHASE','purchases',$pid,"Pembelian $invoice total ".rupiah($total)); flash_set('success','Pembelian disimpan, stok bertambah');
 }catch(Exception $e){ if($pdo->inTransaction()) $pdo->rollBack(); flash_set('error',$e->getMessage()); }
 redirect($_SERVER['REQUEST_URI']);
}
$q=trim($_GET['q']??''); $page=max(1,(int)($_GET['page']??1)); $per=10;
$where="WHERE 1"; $par=[]; if($q!==''){ $where.=" AND (pu.invoice_number LIKE ? OR s.name LIKE ?)"; $par[]="%$q%"; $par[]="%$q%"; }
$total=$pdo->prepare("SELECT COUNT(*) FROM purchases pu JOIN suppliers s ON s.id=pu.supplier_id $where"); $total->execute($par); $total=(int)$total->fetchColumn();
list($pages,$page,$off)=paginate_params($total,$page,$per);
$stmt=$pdo->prepare("SELECT pu.*, s.name as supplier, u.name as user FROM purchases pu JOIN suppliers s ON s.id=pu.supplier_id JOIN users u ON u.id=pu.user_id $where ORDER BY pu.id DESC LIMIT $per OFFSET $off"); $stmt->execute($par); $rows=$stmt->fetchAll();
?>
<!DOCTYPE html><html lang="id"><head><title>Pembelian • <?=e(APP_NAME)?></title><?php include __DIR__.'/../../components/head.php'; ?></head>
<body class="min-h-screen bg-slate-50 flex flex-col"><?php include __DIR__.'/../../components/header.php';?>
<main class="flex w-full flex-1 flex-col gap-6 px-4 pb-10 pt-5 sm:px-6 lg:px-8 lg:flex-row lg:items-start"><?php include __DIR__.'/../../components/sidebar_admin.php';?>
<section class="order-2 flex-1 space-y-4">
<nav class="text-[11px] text-slate-500">Dashboard / Pembelian</nav>
<div class="flex justify-between items-center"><h2 class="text-lg font-semibold">Pembelian</h2><button data-modal-toggle="#modalAdd" class="rounded-full bg-emerald-600 px-4 py-1.5 text-xs text-white">+ Buat Pembelian</button></div>
<form method="GET" class="rounded-2xl border bg-white p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 text-xs"><input name="q" value="<?=e($q)?>" placeholder="Cari faktur / supplier" class="rounded-xl border px-3 py-2 sm:col-span-2 lg:col-span-1"><button class="justify-self-start rounded-xl bg-emerald-600 px-4 py-2 text-white font-medium">Cari</button></form>
<div class="rounded-2xl border bg-white p-4 shadow-sm"><div class="overflow-auto rounded-xl border"><table class="min-w-full divide-y text-xs"><thead class="bg-slate-100"><tr><th class="px-3 py-2 text-left">Faktur</th><th class="px-3 py-2 text-left">Supplier</th><th class="px-3 py-2 text-left">Tanggal</th><th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2 text-left">Oleh</th></tr></thead><tbody class="divide-y">
<?php foreach($rows as $r):?><tr class="hover:bg-slate-50"><td class="px-3 py-2 font-mono"><?=e($r['invoice_number'])?></td><td class="px-3 py-2"><?=e($r['supplier'])?></td><td class="px-3 py-2"><?=e($r['purchase_date'])?></td><td class="px-3 py-2 text-right font-semibold"><?=rupiah($r['total'])?></td><td class="px-3 py-2"><?=e($r['user'])?></td></tr><?php endforeach; if(!$rows) echo '<tr><td colspan="5" class="px-3 py-6 text-center text-slate-400">Belum ada pembelian</td></tr>';?>
</tbody></table></div>
<div class="mt-3 flex justify-between text-xs text-slate-500"><span><?=$total?> data</span><div class="flex gap-1"><?php for($i=1;$i<=$pages;$i++):?><a href="?q=<?=urlencode($q)?>&page=<?=$i?>" class="rounded-full border px-3 py-1 <?=$i==$page?'bg-emerald-600 text-white':''?>"><?=$i?></a><?php endfor;?></div></div>
</div></section></main>
<div id="modalAdd" class="modal-dashboard hidden"><div class="modal-dialog" style="max-width:700px"><div class="modal-content"><div class="flex justify-between mb-3"><h3 class="text-sm font-semibold">Buat Pembelian</h3><button data-modal-hide="#modalAdd" class="btn-close"></button></div>
<form method="POST" id="formBeli" class="space-y-3"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
<div class="grid sm:grid-cols-3 gap-2"><select name="supplier_id" required class="rounded-xl border px-3 py-2 text-xs"><option value="">- Supplier -</option><?php foreach($suppliers as $s):?><option value="<?=$s['id']?>"><?=e($s['name'])?></option><?php endforeach;?></select><input name="invoice_number" required placeholder="No faktur INV-001" class="rounded-xl border px-3 py-2 text-xs"><input type="date" name="purchase_date" value="<?=date('Y-m-d')?>" class="rounded-xl border px-3 py-2 text-xs"></div>
<div id="items" class="space-y-2"></div>
<button type="button" onclick="addRow()" class="rounded-full border px-3 py-1 text-xs">+ Tambah Baris</button>
<button class="w-full rounded-xl bg-emerald-600 py-2 text-xs text-white">Simpan Pembelian (stok +)</button>
</form></div></div></div>
<script>
const products=<?=json_encode($products)?>;
function addRow(){
 const d=document.createElement('div'); d.className='grid grid-cols-12 gap-1';
 let opts=products.map(p=>`<option value="${p.id}">${p.sku} - ${p.name} (stok ${p.stock})</option>`).join('');
 d.innerHTML=`<select name="product_id[]" class="col-span-6 rounded-xl border px-2 py-2 text-xs">${opts}</select><input name="qty[]" type="number" value="1" min="1" class="col-span-2 rounded-xl border px-2 py-2 text-xs"><input name="price[]" placeholder="Harga beli" class="col-span-3 rounded-xl border px-2 py-2 text-xs"><button type="button" onclick="this.parentElement.remove()" class="col-span-1 text-rose-500">×</button>`;
 document.getElementById('items').appendChild(d);
}
addRow();
</script>
<?php include __DIR__.'/../../components/footer.php';?></body></html>
