<?php
require __DIR__.'/../config/database.php';
$pdo=db();
$pdo->exec("UPDATE products SET stock=100 WHERE sku='SKU001'");
$pdo->exec("UPDATE products SET stock=80 WHERE sku='SKU002'");
$pdo->exec("UPDATE products SET stock=60 WHERE sku='SKU003'");
$pdo->exec("UPDATE products SET stock=20 WHERE sku='SKU004'");
$pdo->exec("UPDATE products SET stock=30 WHERE sku='SKU005'");
$pdo->exec("DELETE FROM sale_items WHERE sale_id IN (SELECT id FROM sales WHERE transaction_number LIKE 'TRX-%' AND DATE(created_at)=CURDATE())");
$pdo->exec("DELETE FROM sale_payments WHERE sale_id IN (SELECT id FROM sales WHERE transaction_number LIKE 'TRX-%' AND DATE(created_at)=CURDATE())");
$pdo->exec("DELETE FROM return_items WHERE return_id IN (SELECT id FROM returns WHERE DATE(created_at)=CURDATE() AND code LIKE 'RET-%')");
$pdo->exec("DELETE FROM stock_movements WHERE DATE(created_at)=CURDATE() AND type IN ('SALE','CORRECTION','RETURN_SALE')");
$pdo->exec("DELETE FROM returns WHERE DATE(created_at)=CURDATE() AND code LIKE 'RET-%'");
$pdo->exec("DELETE FROM cash_transactions WHERE DATE(created_at)=CURDATE() AND category='retur'");
$pdo->exec("DELETE FROM sales WHERE DATE(created_at)=CURDATE() AND transaction_number LIKE 'TRX-%'");
$pdo->exec("DELETE FROM audit_logs WHERE action='EDIT_SALE' AND description='koreksi test'");
echo "reset done\n";
foreach($pdo->query("SELECT sku,stock FROM products ORDER BY sku")->fetchAll() as $r) echo $r['sku']."=".$r['stock']." ";
