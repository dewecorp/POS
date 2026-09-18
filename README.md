# POS Profesional — Point of Sale (PHP Native + MySQL)

Aplikasi POS modern, modular, aman — dibangun di atas template Tailwind yang sudah tersedia (`assets/css/dashboard.css`, `assets/js/dashboard.js`).

## Requirement
- PHP 8.0+ (tested 8.4.3)
- MySQL 8 / MariaDB 10+
- Apache (Laragon) — mod_rewrite opsional
- Extension: PDO MySQL, GD (upload)

## Instalasi
1. Clone / copy folder ke `D:/laragon/www/POS`
2. Buat database `pos_db` (otomatis oleh `database/schema.sql`)
3. Import schema & seed:
```
# PowerShell (Laragon)
Get-Content database/schema.sql | & "D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -u root
Get-Content database/seed.sql | & "D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -u root
```
4. Konfigurasi DB di `config/database.php` (host/user/pass) dan `config/app.php` (APP_URL, SESSION_TIMEOUT, ALLOW_NEGATIVE_STOCK, etc)
5. Pastikan folder `uploads/` writable
6. Akses `http://localhost/POS/login.php`

## Akun Demo (password: `123`)
- `admin` / `admin@pos.local` — Admin (akses penuh)
- `kasir` / `kasir@pos.local` — Kasir
- `manager` / `manager@pos.local` — Manager

> Ganti password setelah instalasi.

## Struktur Folder
```
config/        database.php, app.php
core/          auth.php, session.php, csrf.php, security.php, helper.php, audit.php, validation.php
components/    header.php, sidebar_admin.php, sidebar_kasir.php, footer.php
admin/         index.php (dashboard), produk/, kategori/, supplier/, pelanggan/, pembelian/, penjualan/, stok/, retur/, kas/, laporan/, pengguna/, pengaturan/, audit/
kasir/         index.php (POS), cek-harga.php, history.php, cetak-struk.php
api/           products.php, sales.php, customers.php, held.php, sale_items.php
assets/        css/dashboard.css, js/dashboard.js (template asli)
uploads/       foto produk & bukti pengeluaran
database/      schema.sql, seed.sql
```

## URL Aplikasi
- `/login.php`, `/logout.php`, `/index.php` (auto redirect by role)
- `/kasir/index.php` — POS Penjualan (F1/F2/F3/F4/F8/F9)
- `/kasir/cek-harga.php` — Cek harga (scan barcode)
- `/kasir/history.php` — History penjualan (filter tanggal/status/bayar)
- `/kasir/cetak-struk.php?id=ID&print=1` — Struk thermal 58/80mm
- `/admin/index.php` — Dashboard (penjualan hari ini, laba kotor, stok menipis, grafik 7 hari)
- `/admin/produk`, `/admin/kategori`, `/admin/supplier`, `/admin/pelanggan`
- `/admin/pembelian`, `/admin/stok`, `/admin/penjualan` (void/koreksi), `/admin/retur`, `/admin/kas` (shift), `/admin/laporan`, `/admin/pengguna`, `/admin/pengaturan`, `/admin/audit`

Clean URL (opsional via `.htaccess`): `/login`, `/admin`, `/kasir`

## Fitur Kasir
- Cari produk by nama/SKU/barcode (AJAX), scan barcode = keyboard Enter → auto add
- Keranjang: qty +/-, hapus, diskon per-item (nominal/%) dan diskon transaksi
- Pajak otomatis dari pengaturan toko, biaya tambahan
- Pelanggan (Umum/Member), metode bayar: Tunai/Transfer/QRIS/Debit/Kredit/E-Wallet (configurable)
- Validasi pembayaran kurang (tunai ditolak), hitung kembalian
- Parkir transaksi (held_transactions JSON) → buka/hapus
- Shortcut: F1 Penjualan, F2 cari, F3 cek harga, F4 history, F8 parkir, F9 bayar, ESC tutup modal
- Transaksi DB: `BEGIN` → insert sales/sale_items/payments → update stok → stock_movements → cash_transactions → `COMMIT` (ROLLBACK on fail)
- Stok negatif dicegah (ALLOW_NEGATIVE_STOCK=false)
- Nomor transaksi: `TRX-YYYYMMDD-00001` (FOR UPDATE, anti bentrok)

## Fitur Admin
- Master kategori/supplier/pelanggan/produk (SKU/barcode, harga beli/jual/grosir, stok/min, lokasi, foto upload validasi MIME/size, price history)
- Pembelian → stok bertambah + stock_movements PURCHASE
- Stok: adjust, opname (draft→approved, stok sistem→fisik, selisih, audit log), movement history, peringatan stok menipis di dashboard
- Penjualan: list + detail, void (alasan wajib, stok kembali, kas out, status cancelled, audit), koreksi (payment/customer + alasan, audit old/new)
- Retur: pilih transaksi → item/qty → refund proporsional → stok +, kas out
- Kas & pengeluaran + shift kasir (buka/tutup, saldo awal/fisik, expected, selisih)
- Laporan: filter periode/kasir/kategori/bayar, total/transaksi/diskon/retur/bersih/HPP/laba kotor, produk terlaris, per kategori, per bayar, grafik harian, export CSV
- Pengguna: role admin/kasir/manager/owner, permission granular, is_active, soft-delete jika punya transaksi
- Pengaturan toko: nama/alamat/telepon/email/logo/footer struk, pajak %, metode bayar
- Audit log: user/action/module/record_id/description/IP/UA/old/new/timestamp (filter module/action)

## Keamanan
- `password_hash` / `password_verify`, `session_regenerate_id`, cookie httponly, session timeout 1 jam
- CSRF token untuk semua POST & AJAX mutation (header X-CSRF-TOKEN)
- Brute-force: max 5 attempt → lock 10 menit
- PDO prepared statements (SQL injection aman), `e()` escape output (XSS)
- Authorization: `require_role` + `has_permission` di setiap endpoint (backend, bukan hanya hide tombol)
- Upload validasi MIME/extension/size, nama file random, tidak executable
- Error handling: user lihat pesan umum, detail hanya log server (APP_DEBUG=false)

## Database & Index
- 28 tabel (lihat `database/schema.sql`), foreign key, index pada barcode/sku/name/transaction_number/created_at/customer_id/cashier_id/category_id/supplier_id
- Pagination (ITEMS_PER_PAGE=15) di semua list

## Testing
Jalankan `php database/test_pos.php` (10 test):
1. Stok 10 jual 2 → 8
2. Diskon % akurat
3. Kembalian tunai benar
4. Pembayaran kurang ditolak
5. Void stok kembali
6. Retur stok & kas sesuai
7. Audit log koreksi tercatat
8. Kasir blocked akses admin
9. SQL injection blocked
10. CSRF invalid ditolak

Reset setelah test: `php database/reset_test.php`

## Potensi yang perlu diperhatikan
- `held_transactions.data` JSON — parkir transaksi belum terenkripsi, hapus data lama periodik
- Printer thermal: test di 58mm/80mm, sesuaikan CSS `@media print`
- Backup DB: gunakan mysqldump / phpMyAdmin manual (hosting harus mendukung)
- APP_DEBUG=false di production, ganti password demo, set APP_URL sesuai domain, aktifkan HTTPS → cookie secure auto

## Troubleshooting
- MySQL tidak connect: cek `D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root -e "SELECT 1"`
- Import gagal: pastikan `pos_db` belum ada constraint conflict, jalankan `SET FOREIGN_KEY_CHECKS=0` sebelum drop
- CSRF error: clear cache, pastikan header X-CSRF-TOKEN terkirim di fetch
- Stok negatif: set `ALLOW_NEGATIVE_STOCK` di `config/app.php` jika toko butuh over-sell
