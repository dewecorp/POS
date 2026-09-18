USE pos_db;
INSERT INTO roles (code,name) VALUES ('admin','Administrator'),('manager','Manager'),('kasir','Kasir'),('owner','Owner');
INSERT INTO permissions (code,name,module) VALUES
('sales.create','Buat Penjualan','sales'),('sales.view','Lihat Penjualan','sales'),('sales.edit','Edit/Koreksi Penjualan','sales'),
('sales.cancel','Void Penjualan','sales'),('sales.return','Retur Penjualan','sales'),('sales.print','Cetak Struk','sales'),
('products.create','Buat Produk','products'),('products.view','Lihat Produk','products'),('products.edit','Edit Produk','products'),('products.delete','Hapus Produk','products'),
('inventory.view','Lihat Stok','inventory'),('inventory.adjust','Adjust Stok','inventory'),('inventory.opname','Stock Opname','inventory'),
('purchase.create','Buat Pembelian','purchase'),('purchase.view','Lihat Pembelian','purchase'),('purchase.edit','Edit Pembelian','purchase'),
('reports.view','Lihat Laporan','reports'),('reports.export','Export Laporan','reports'),
('users.manage','Kelola Pengguna','users'),('settings.manage','Kelola Pengaturan','settings'),('audit.view','Lihat Audit Log','audit'),
('cash.manage','Kelola Kas','cash'),('customers.manage','Kelola Pelanggan','customers'),('suppliers.manage','Kelola Supplier','suppliers');
INSERT INTO stores (name,address,phone,email,receipt_footer) VALUES ('Toko POS Profesional','Jl. Contoh No. 123, Jakarta','0812-3456-7890','toko@pos.local','Terima kasih telah berbelanja!');
INSERT INTO settings (`key`,`value`) VALUES ('tax_percent','0'),('currency','Rp'),('allow_negative_stock','0'),('receipt_footer','Terima kasih - Barang yang sudah dibeli tidak dapat ditukar kecuali cacat'),('store_name','Toko POS Profesional');
INSERT INTO payment_methods (code,name) VALUES ('tunai','Tunai'),('transfer','Transfer'),('qris','QRIS'),('debit','Debit'),('kredit','Kredit'),('ewallet','E-Wallet');
INSERT INTO product_categories (code,name,description) VALUES ('CAT001','Makanan','Kategori makanan'),('CAT002','Minuman','Kategori minuman'),('CAT003','Snack','Snack & biskuit'),('CAT004','Sembako','Sembako'),('CAT005','Elektronik','Elektronik kecil');
INSERT INTO product_units (code,name) VALUES ('PCS','Pcs'),('DUS','Dus'),('KG','Kg'),('LTR','Liter'),('PACK','Pack');
INSERT INTO suppliers (code,name,contact,phone,address) VALUES ('SUP001','PT Sumber Makmur','Budi','081111111111','Jakarta'),('SUP002','CV Jaya Abadi','Siti','082222222222','Bandung'),('SUP003','UD Berkah','Ahmad','083333333333','Surabaya');
INSERT INTO customers (code,name,phone,address,type) VALUES ('CUST001','Umum','-','-','umum'),('CUST002','Budi Santoso','081234567890','Jakarta','member'),('CUST003','Siti Rahmawati','082345678901','Bandung','member');
INSERT INTO users (name,username,email,password,role,is_active) VALUES
('Administrator','admin','admin@pos.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin',1),
('Kasir Utama','kasir','kasir@pos.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','kasir',1),
('Manager','manager','manager@pos.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','manager',1);
-- password for all: password
INSERT INTO products (sku,barcode,name,category_id,unit_id,purchase_price,selling_price,wholesale_price,stock,min_stock,location,supplier_id) VALUES
('SKU001','899000000001','Indomie Goreng 85g',1,1,2500,3500,3200,100,10,'RAK-A1',1),
('SKU002','899000000002','Aqua 600ml',2,1,2500,4000,3800,80,10,'RAK-A2',1),
('SKU003','899000000003','Poci Teh Botol 350ml',2,1,3000,5000,4700,60,10,'RAK-A2',2),
('SKU004','899000000004','Beras 5kg Premium',4,3,55000,65000,62000,20,5,'RAK-B1',3),
('SKU005','899000000005','Minyak Goreng 1L',4,4,14000,17000,16000,30,5,'RAK-B2',3),
('SKU006','899000000006','Kopi Kapal Api 30g',3,1,8000,10000,9500,50,10,'RAK-A3',1),
('SKU007','899000000007','Gula Pasir 1kg',4,3,12000,14500,13500,40,10,'RAK-B1',2),
('SKU008','899000000008','Tissue Paseo 250s',5,1,15000,18500,17500,25,5,'RAK-C1',2);
