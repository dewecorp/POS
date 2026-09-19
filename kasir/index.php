<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/helper.php';
require_once __DIR__.'/../core/csrf.php';
require_login();
$pdo=db();
$methods=$pdo->query("SELECT * FROM payment_methods WHERE is_active=1 ORDER BY id")->fetchAll();
$customers=$pdo->query("SELECT * FROM customers WHERE is_active=1 ORDER BY name LIMIT 50")->fetchAll();
$store=$pdo->query("SELECT * FROM stores LIMIT 1")->fetch();
$tax=(float)($pdo->query("SELECT value FROM settings WHERE `key`='tax_percent'")->fetchColumn() ?: 0);
$quick=$pdo->query("SELECT * FROM products WHERE is_active=1 ORDER BY stock DESC LIMIT 12")->fetchAll();
?>
<!DOCTYPE html><html lang="id"><head><title>POS Kasir • <?=e((store_info()['name'] ?? APP_NAME))?></title><?php include __DIR__.'/../components/head.php'; ?></head>
<body class="min-h-screen bg-slate-50 flex flex-col">
<?php include __DIR__.'/../components/header.php'; ?>
<div class="border-b bg-white px-4 sm:px-6 lg:px-8 py-2.5 flex gap-2 text-sm overflow-auto">
<a href="<?=url('/kasir/index')?>" class="inline-flex items-center gap-2 rounded-full bg-emerald-600 px-5 py-2.5 text-white font-semibold shadow-sm"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>PENJUALAN <span class="rounded bg-white/20 px-1.5 py-0.5 text-[10px] font-bold">F1</span></a>
<a href="<?=url('/kasir/cek-harga')?>" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-2.5 font-medium text-slate-600 hover:bg-slate-50"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>CEK HARGA <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500">F3</span></a>
<a href="<?=url('/kasir/history')?>" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-2.5 font-medium text-slate-600 hover:bg-slate-50"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>HISTORY <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500">F4</span></a>
<button id="btnHelp" class="ml-auto inline-flex items-center gap-2 rounded-full border border-slate-200 px-4 py-2.5 font-medium text-slate-600 hover:bg-slate-50"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Shortcut</button>
</div>
<main class="flex w-full flex-1 flex-col gap-6 px-4 pb-10 pt-5 sm:px-6 lg:px-8">
<section class="flex flex-1 flex-col gap-4 lg:flex-row">
<!-- LEFT: search + quick -->
<div class="lg:w-[45%] space-y-3">
<div class="rounded-2xl border bg-white p-4 shadow-sm">
<div class="flex gap-2">
<input id="searchInput" autofocus placeholder="🔍 Cari nama / barcode / SKU (scan barcode langsung)" class="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm focus:border-emerald-500 focus:outline-none">
<button id="btnCari" class="rounded-xl bg-emerald-600 px-5 py-2 text-sm text-white">Cari</button>
</div>
<div id="searchResult" class="mt-3 max-h-[300px] overflow-auto divide-y border rounded-xl hidden"></div>
<div class="mt-2 flex gap-2 text-[11px] text-slate-500">
<span>F9 = Bayar</span><span>F8 = Parkir</span><span>ESC = Tutup modal</span>
</div>
</div>
<div class="rounded-2xl border bg-white p-4 shadow-sm">
<p class="text-xs font-semibold mb-2">Produk cepat</p>
<div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
<?php foreach($quick as $q):?><button onclick="addProduct(<?=$q['id']?>)" class="rounded-xl border px-3 py-2 text-left hover:border-emerald-400 bg-slate-50"><p class="text-xs font-medium truncate"><?=e($q['name'])?></p><p class="text-[11px] text-slate-500"><?=rupiah($q['selling_price'])?> • stok <?=$q['stock']?></p></button><?php endforeach;?>
</div>
</div>
<div class="rounded-2xl border bg-white p-3 shadow-sm flex gap-2">
<select id="customerSelect" class="flex-1 rounded-xl border px-3 py-2 text-xs"><option value="">Pelanggan: Umum</option><?php foreach($customers as $c):?><option value="<?=$c['id']?>"><?=e($c['name'])?> (<?=e($c['phone'])?>)</option><?php endforeach;?></select>
<button id="btnCariPelanggan" class="rounded-xl border px-3 py-2 text-xs">Cari</button>
</div>
</div>

<!-- RIGHT: cart -->
<div class="lg:w-[55%] space-y-3">
<div class="rounded-2xl border bg-white p-4 shadow-sm">
<div class="flex justify-between items-center mb-2"><h3 class="text-sm font-semibold">Keranjang</h3><div class="flex gap-1"><button id="btnParkir" class="rounded-full border px-3 py-1 text-xs">Parkir (F8)</button><button id="btnLihatParkir" class="rounded-full border px-3 py-1 text-xs">Lihat Parkir</button><button id="btnClear" class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs text-rose-600">Kosongkan</button></div></div>
<div class="overflow-auto rounded-xl border max-h-[320px]">
<table class="min-w-full text-xs"><thead class="bg-slate-100"><tr><th class="px-2 py-2 text-left">Produk</th><th class="px-2 py-2 text-right">Harga</th><th class="px-2 py-2 text-center">Qty</th><th class="px-2 py-2 text-right">Subtotal</th><th class="px-2 py-2"></th></tr></thead><tbody id="cartBody"><tr><td colspan="5" class="px-3 py-8 text-center text-slate-400">Keranjang kosong — scan atau cari produk</td></tr></tbody></table>
</div>
<!-- totals -->
<div class="mt-3 space-y-1 text-sm">
<div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span id="tSubtotal" class="font-semibold">Rp 0</span></div>
<div class="flex gap-2 items-center"><span class="text-slate-500 text-xs">Diskon transaksi</span><select id="discType" class="rounded-lg border px-2 py-1 text-xs"><option value="none">-</option><option value="nominal">Rp</option><option value="percent">%</option></select><input id="discValue" type="number" value="0" class="w-24 rounded-lg border px-2 py-1 text-xs"></div>
<div class="flex justify-between"><span class="text-slate-500">Diskon</span><span id="tDiscount">- Rp 0</span></div>
<div class="flex justify-between"><span class="text-slate-500">Pajak (<?=$tax?>%)</span><span id="tTax">Rp 0</span></div>
<div class="flex justify-between"><span class="text-slate-500">Biaya tambahan</span><input id="addCost" type="number" value="0" class="w-24 rounded-lg border px-2 py-1 text-xs text-right"></div>
<div class="flex justify-between text-base font-bold border-t pt-2 mt-2"><span>Grand Total</span><span id="tGrand" class="text-emerald-600">Rp 0</span></div>
</div>
<div class="mt-3 grid grid-cols-2 gap-2">
<select id="payMethod" class="rounded-xl border px-3 py-2 text-sm"><?php foreach($methods as $m):?><option value="<?=e($m['code'])?>"><?=e($m['name'])?></option><?php endforeach;?></select>
<input id="paidAmount" type="text" inputmode="numeric" placeholder="Dibayar" class="rounded-xl border px-3 py-2 text-sm font-semibold">
</div>
<div class="flex justify-between text-sm mt-1"><span>Kembalian</span><span id="tChange" class="font-bold">Rp 0</span></div>
<button id="btnBayar" class="w-full rounded-xl bg-gradient-to-r from-emerald-600 to-emerald-500 py-3 text-sm font-semibold text-white shadow-lg mt-2">BAYAR (F9)</button>
</div>
</div>
</section></main>

<!-- modal parkir -->
<div id="modalParkir" class="modal-dashboard hidden"><div class="modal-dialog max-w-sm" style="max-width:380px !important;"><div class="modal-content"><div class="flex justify-between items-center mb-3"><h3 class="text-sm font-semibold">Parkir Transaksi</h3><button onclick="document.getElementById('modalParkir').classList.add('hidden')" class="btn-close"></button></div><div id="parkirList" class="space-y-2 text-xs max-h-72 overflow-y-auto"></div></div></div></div>

<!-- struk preview modal -->
<div id="modalStruk" class="modal-dashboard hidden"><div class="modal-dialog max-w-sm" style="max-width:380px !important;"><div class="modal-content text-center p-4"><h3 class="text-sm font-semibold text-emerald-600">Transaksi Berhasil!</h3><p id="strukNo" class="text-xs text-slate-500 mt-1"></p><div id="strukPreview" class="mt-3 rounded-xl border bg-slate-50 p-3 text-left text-xs font-mono whitespace-pre-wrap max-h-56 overflow-y-auto"></div><div class="flex gap-2 mt-3"><a id="btnCetak" target="_blank" class="flex-1 rounded-xl bg-emerald-600 py-2 text-xs text-white text-center font-medium hover:bg-emerald-700 transition">Cetak Struk</a><button onclick="location.reload()" class="flex-1 rounded-xl border py-2 text-xs font-medium hover:bg-slate-50 transition">Transaksi Baru</button></div></div></div></div>

<div id="modalHelp" class="modal-dashboard hidden"><div class="modal-dialog"><div class="modal-content"><div class="flex justify-between mb-2"><h3 class="text-sm font-semibold">Shortcut</h3><button onclick="document.getElementById('modalHelp').classList.add('hidden')" class="btn-close"></button></div><ul class="text-xs space-y-1"><li>F1 - Penjualan</li><li>F2 - Fokus cari</li><li>F3 - Cek harga</li><li>F4 - History</li><li>F8 - Parkir</li><li>F9 - Bayar</li><li>ESC - Tutup modal</li></ul></div></div></div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const CSRF='<?=e(csrf_token())?>';
let cart=[];
const taxPercent=<?= (float)$tax ?>;

function rupiah(n){ return 'Rp ' + Number(n).toLocaleString('id-ID'); }
function parseNum(str){ return parseInt(String(str||'').replace(/\D/g,''),10)||0; }
function formatDigits(n){ return n ? Number(n).toLocaleString('id-ID') : ''; }
function calc(){
 let sub=0;
 cart.forEach(c=>{
  let d=0;
  if(c.disc_type==='percent') d=Math.round(c.price*c.qty*c.disc_value/100);
  else if(c.disc_type==='nominal') d=Math.min(c.disc_value,c.price*c.qty);
  c._disc=d; c._sub=c.price*c.qty - d; sub+=c._sub;
 });
 let dtype=document.getElementById('discType').value;
 let dval=parseFloat(document.getElementById('discValue').value)||0;
 let trxDisc=0;
 if(dtype==='percent') trxDisc=Math.round(sub*dval/100);
 else if(dtype==='nominal') trxDisc=Math.min(dval,sub);
 let after=sub-trxDisc;
 let tax=Math.round(after*taxPercent/100);
 let add=parseInt(document.getElementById('addCost').value)||0;
 let grand=after+tax+add;
 let paid=parseNum(document.getElementById('paidAmount').value);
 let change=paid-grand;
 document.getElementById('tSubtotal').textContent=rupiah(sub);
 document.getElementById('tDiscount').textContent='- '+rupiah(trxDisc + cart.reduce((a,c)=>a+c._disc,0));
 document.getElementById('tTax').textContent=rupiah(tax);
 document.getElementById('tGrand').textContent=rupiah(grand);
 document.getElementById('tChange').textContent=rupiah(change>0?change:0);
 document.getElementById('tChange').className=change>=0?'font-bold text-emerald-600':'font-bold text-rose-600';
 return {sub,trxDisc,tax,add,grand,paid,change};
}
function renderCart(){
 const tb=document.getElementById('cartBody');
 if(cart.length===0){ tb.innerHTML='<tr><td colspan="5" class="px-3 py-8 text-center text-slate-400">Keranjang kosong</td></tr>'; calc(); return; }
 let h='';
 cart.forEach((c,i)=>{
  h+=`<tr class="border-b"><td class="px-2 py-2"><div class="font-medium">${c.name}</div><div class="text-[10px] text-slate-400">${c.sku} • ${rupiah(c.price)}</div><div class="flex gap-1 mt-1"><select onchange="setDiscType(${i},this.value)" class="rounded border px-1 py-0.5 text-[10px]"><option value="none" ${c.disc_type==='none'?'selected':''}>-</option><option value="nominal" ${c.disc_type==='nominal'?'selected':''}>Rp</option><option value="percent" ${c.disc_type==='percent'?'selected':''}>%</option></select><input type="number" value="${c.disc_value}" onchange="setDiscVal(${i},this.value)" class="w-14 rounded border px-1 py-0.5 text-[10px]" placeholder="diskon"></div></td><td class="px-2 py-2 text-right">${rupiah(c.price)}</td><td class="px-2 py-2 text-center"><div class="flex items-center justify-center gap-1"><button onclick="chgQty(${i},-1)" class="h-6 w-6 rounded-full border">-</button><input type="number" value="${c.qty}" onchange="setQty(${i},this.value)" class="w-12 rounded border px-1 py-0.5 text-center text-xs"><button onclick="chgQty(${i},1)" class="h-6 w-6 rounded-full border">+</button></div></td><td class="px-2 py-2 text-right font-semibold">${rupiah(c._sub||c.price*c.qty)}</td><td class="px-2 py-2"><button onclick="removeItem(${i})" class="text-rose-500">×</button></td></tr>`;
 });
 tb.innerHTML=h; calc();
}
function chgQty(i,d){ cart[i].qty=Math.max(1, cart[i].qty+d); renderCart(); }
function setQty(i,v){ let n=parseInt(v)||1; if(n<1)n=1; cart[i].qty=n; renderCart(); }
function setDiscType(i,v){ cart[i].disc_type=v; renderCart(); }
function setDiscVal(i,v){ cart[i].disc_value=parseFloat(v)||0; renderCart(); }
function removeItem(i){ cart.splice(i,1); renderCart(); }

async function search(q){
 if(!q) return;
 const r=await fetch('<?=url('/api/products')?>?q='+encodeURIComponent(q));
 const j=await r.json();
 const box=document.getElementById('searchResult');
 if(!j.data || j.data.length===0){ box.innerHTML='<div class="p-3 text-xs text-slate-400">Tidak ditemukan</div>'; box.classList.remove('hidden'); return; }
 let h='';
 j.data.forEach(p=>{
  h+=`<button onclick="addProduct(${p.id})" class="w-full text-left px-3 py-2 hover:bg-slate-50 flex justify-between"><span><span class="font-medium text-xs">${p.name}</span><span class="text-[10px] text-slate-400 ml-2">${p.sku} • ${p.barcode||'-'}</span><span class="block text-xs">${rupiah(p.selling_price)} • stok ${p.stock}</span></span><span class="text-emerald-600 text-xs">+ Tambah</span></button>`;
 });
 box.innerHTML=h; box.classList.remove('hidden');
}
async function addProduct(id){
 let p=null;
 try{
  const r=await fetch('<?=url('/api/products')?>?id='+id);
  const j=await r.json();
  if(j.success && j.data && j.data.id) p=j.data;
 }catch(e){}
 if(!p || !p.id){
  const br=document.getElementById('searchInput').value.trim();
  if(br){
   try{ const rb=await fetch('<?=url('/api/products')?>?barcode='+encodeURIComponent(br)); const jb=await rb.json(); if(jb.success && jb.data) p=jb.data; }catch(e){}
  }
 }
 if(!p || !p.id){ Swal.fire({icon:'error',title:'Produk tidak ditemukan'}); return; }
 if(parseInt(p.stock)<=0){ Swal.fire({icon:'warning',title:'Stok habis — tetap ditambah? sesuai pengaturan'}); }
 let ex=cart.find(c=>c.id===p.id);
 if(ex){ ex.qty++; } else { cart.push({id:parseInt(p.id),sku:p.sku,name:p.name,price:parseInt(p.selling_price),qty:1,disc_type:'none',disc_value:0}); }
 document.getElementById('searchResult').classList.add('hidden');
 document.getElementById('searchInput').value='';
 renderCart();
}

let searchTimer=null;
document.getElementById('searchInput').addEventListener('input',e=>{
 clearTimeout(searchTimer);
 const v=e.target.value.trim();
 if(!v){
  document.getElementById('searchResult').classList.add('hidden');
  return;
 }
 searchTimer=setTimeout(()=>{
  fetch('<?=url('/api/products')?>?barcode='+encodeURIComponent(v)).then(r=>r.json()).then(j=>{
   if(j.success && j.data && j.data.id){
    addProduct(j.data.id);
   } else {
    search(v);
   }
  }).catch(()=>search(v));
 }, 300);
});

document.getElementById('searchInput').addEventListener('keydown',e=>{
 if(e.key==='Enter'){
  e.preventDefault();
  clearTimeout(searchTimer);
  let v=e.target.value.trim();
  if(!v) return;
  fetch('<?=url('/api/products')?>?barcode='+encodeURIComponent(v)).then(r=>r.json()).then(j=>{
   if(j.success && j.data && j.data.id){
    addProduct(j.data.id);
   } else {
    search(v);
   }
  }).catch(()=>search(v));
 }
});
document.getElementById('btnCari').addEventListener('click',()=>search(document.getElementById('searchInput').value.trim()));
document.getElementById('btnClear').addEventListener('click',()=>{ cart=[]; renderCart(); });
['discType','discValue','addCost'].forEach(id=>document.getElementById(id).addEventListener('input',calc));
const paidInput=document.getElementById('paidAmount');
paidInput.addEventListener('input', function(e){
 let curPos=this.selectionStart;
 let raw=this.value.replace(/\D/g,'');
 let n=parseInt(raw,10)||0;
 let formatted=n ? n.toLocaleString('id-ID') : '';
 this.value=formatted;
 calc();
});
document.getElementById('btnHelp').addEventListener('click',()=>document.getElementById('modalHelp').classList.remove('hidden'));

async function doBayar(){
 if(cart.length===0) return Swal.fire({icon:'warning',title:'Keranjang kosong'});
 let c=calc();
 if(c.change<0 && document.getElementById('payMethod').value==='tunai') return Swal.fire({icon:'error',title:'Pembayaran kurang',text:'Kembalian negatif'});
 const payload={
  items: cart.map(x=>({product_id:x.id,qty:x.qty,discount_type:x.disc_type,discount_value:x.disc_value})),
  customer_id: document.getElementById('customerSelect').value||null,
  discount_type: document.getElementById('discType').value,
  discount_value: parseFloat(document.getElementById('discValue').value)||0,
  tax_percent: taxPercent,
  additional_cost: parseInt(document.getElementById('addCost').value)||0,
  paid_amount: parseNum(document.getElementById('paidAmount').value)||c.grand,
  payment_method: document.getElementById('payMethod').value
 };
 const res=await fetch('<?=url('/api/sales')?>',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify(payload)});
 const j=await res.json();
 if(!j.success) return Swal.fire({icon:'error',title:'Gagal',text:j.message});
 document.getElementById('strukNo').textContent=j.data.transaction_number+' • '+rupiah(j.data.grand_total);
 document.getElementById('strukPreview').textContent='Transaksi: '+j.data.transaction_number+'\nTotal: '+rupiah(j.data.grand_total)+'\nKembalian: '+rupiah(j.data.change)+'\n\nTerima kasih!';
 document.getElementById('btnCetak').href='<?=url('/kasir/cetak-struk')?>?id='+j.data.sale_id;
 document.getElementById('modalStruk').classList.remove('hidden');
 cart=[]; renderCart();
}
document.getElementById('btnBayar').addEventListener('click',doBayar);

document.getElementById('btnParkir').addEventListener('click',async()=>{
 if(cart.length===0) return Swal.fire({icon:'warning',title:'Keranjang kosong'});
 const payload={items:cart,customer_id:document.getElementById('customerSelect').value,discount_type:document.getElementById('discType').value,discount_value:document.getElementById('discValue').value,additional_cost:document.getElementById('addCost').value,paid:parseNum(document.getElementById('paidAmount').value),payMethod:document.getElementById('payMethod').value};
 const r=await fetch('<?=url('/api/held')?>',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify(payload)});
 const j=await r.json();
 if(j.success){ Swal.fire({icon:'success',title:j.message,text:j.data.code}); cart=[]; renderCart(); } else Swal.fire({icon:'error',title:j.message});
});
document.getElementById('btnLihatParkir').addEventListener('click',async()=>{
 const r=await fetch('<?=url('/api/held')?>'); const j=await r.json();
 let h='';
 (j.data||[]).forEach(p=>{ h+=`<div class="flex justify-between rounded-xl border px-3 py-2"><span>${p.code} — ${p.created_at}</span><div class="flex gap-1"><button onclick="loadParkir(${p.id})" class="rounded-full bg-emerald-600 px-3 py-1 text-white">Buka</button><button onclick="hapusParkir(${p.id})" class="rounded-full border px-3 py-1 text-rose-600">Hapus</button></div></div>`; });
 document.getElementById('parkirList').innerHTML=h||'<p class="text-slate-400">Tidak ada parkir</p>';
 document.getElementById('modalParkir').classList.remove('hidden');
});
async function loadParkir(id){
 const r=await fetch('<?=url('/api/held')?>?id='+id); const j=await r.json();
 if(!j.success) return Swal.fire({icon:'error',title:j.message});
 const d=j.data;
 cart=d.items||[]; if(d.customer_id) document.getElementById('customerSelect').value=d.customer_id;
 if(d.discount_type) document.getElementById('discType').value=d.discount_type;
 if(d.discount_value) document.getElementById('discValue').value=d.discount_value;
 if(d.additional_cost) document.getElementById('addCost').value=d.additional_cost;
 if(d.paid) document.getElementById('paidAmount').value=formatDigits(d.paid);
 if(d.payMethod) document.getElementById('payMethod').value=d.payMethod;
 document.getElementById('modalParkir').classList.add('hidden');
 renderCart(); Swal.fire({icon:'success',title:'Transaksi dipulihkan',text:j.code});
}
async function hapusParkir(id){
 const conf=await Swal.fire({
  icon: 'warning',
  title: 'Hapus Parkir?',
  text: 'Transaksi tertahan ini akan dihapus permanen.',
  showCancelButton: true,
  confirmButtonText: 'Ya, Hapus',
  cancelButtonText: 'Batal',
  buttonsStyling: false,
  customClass: {
   confirmButton: 'swal-btn-confirm swal-btn-danger',
   cancelButton: 'swal-btn-cancel'
  }
 });
 if(!conf.isConfirmed) return;
 const r=await fetch('<?=url('/api/held')?>?id='+id,{method:'DELETE',headers:{'X-CSRF-TOKEN':CSRF}});
 const j=await r.json();
 Swal.fire({icon:'success',title:j.message,timer:1500,showConfirmButton:false});
 document.getElementById('btnLihatParkir').click();
}

// Intercept link logout kasir
document.querySelectorAll('a[href*="logout.php"]').forEach(function(link) {
 link.addEventListener('click', function(e) {
  e.preventDefault();
  var href = this.getAttribute('href');
  Swal.fire({
   icon: 'warning',
   title: 'Konfirmasi Logout',
   text: 'Apakah Anda yakin ingin keluar dari sistem kasir?',
   showCancelButton: true,
   confirmButtonText: 'Ya, Keluar',
   cancelButtonText: 'Batal',
   buttonsStyling: false,
   customClass: {
    confirmButton: 'swal-btn-confirm',
    cancelButton: 'swal-btn-cancel'
   }
  }).then(function(result) {
   if (result.isConfirmed) window.location.href = href;
  });
 });
});
document.addEventListener('keydown',e=>{
 if(e.key==='F1'){ e.preventDefault(); window.location.href='<?=url('/kasir/index')?>'; }
 if(e.key==='F2'){ e.preventDefault(); document.getElementById('searchInput').focus(); }
 if(e.key==='F3'){ e.preventDefault(); window.location.href='<?=url('/kasir/cek-harga')?>'; }
 if(e.key==='F4'){ e.preventDefault(); window.location.href='<?=url('/kasir/history')?>'; }
 if(e.key==='F8'){ e.preventDefault(); document.getElementById('btnParkir').click(); }
 if(e.key==='F9'){ e.preventDefault(); doBayar(); }
 if(e.key==='Escape'){ document.querySelectorAll('.modal-dashboard').forEach(m=>m.classList.add('hidden')); }
});
renderCart();
</script>
</body></html>
