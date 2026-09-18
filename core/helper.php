<?php
require_once __DIR__ . '/../config/app.php';
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function url($path){
    $path = '/'.ltrim((string)$path, '/');
    if(defined('PRETTY_URL') && PRETTY_URL){
        $path = preg_replace('#/index(?:\.php)?$#', '', $path);
        $path = preg_replace('#\.php$#', '', $path);
        if($path === '') $path = '/';
        return APP_URL.$path;
    }
    // Fallback tanpa rewrite: resolve ke file .php yang benar-benar ada
    $root = dirname(__DIR__);
    if(substr($path,-4)==='.php' && is_file($root.$path)) return APP_URL.$path;
    if(is_file($root.$path.'.php')) return APP_URL.$path.'.php';
    if(is_file($root.rtrim($path,'/').'/index.php')) return APP_URL.rtrim($path,'/').'/index.php';
    return APP_URL.$path;
}
function nav_active($p,$uri){
    $base = rtrim((string)parse_url(APP_URL, PHP_URL_PATH), '/');
    $req  = (string)parse_url($uri, PHP_URL_PATH);
    if($base !== '' && strpos($req, $base) === 0){ $req = substr($req, strlen($base)); }
    $req   = rtrim(preg_replace('#(?:/index)?\.php$#', '', $req), '/');
    $match = rtrim(preg_replace('#(?:/index)?\.php$#', '', $p), '/');
    if($req === '') $req = '/';
    if($match === '') $match = '/';
    return $req === $match;
}
function rupiah($n){ return 'Rp ' . number_format((int)$n,0,',','.'); }
function rupiah_input($n){ return number_format((int)$n,0,',','.'); }
function parse_rupiah($s){ return (int)preg_replace('/[^0-9]/','',(string)$s); }
function now(){ return date('Y-m-d H:i:s'); }
function today(){ return date('Y-m-d'); }
function json_ok($msg='OK',$data=[]){ header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>$msg,'data'=>$data], JSON_UNESCAPED_UNICODE); exit; }
function json_fail($msg='Gagal',$errors=[],$code=400){ http_response_code($code); header('Content-Type: application/json'); echo json_encode(['success'=>false,'message'=>$msg,'errors'=>$errors], JSON_UNESCAPED_UNICODE); exit; }
function redirect($url){ header("Location: $url"); exit; }
function old($k,$d=''){ return e($_SESSION['_old'][$k] ?? $d); }
function paginate_params($total,$page,$perPage){ $pages=max(1,(int)ceil($total/$perPage)); $page=max(1,min($pages,(int)$page)); $offset=($page-1)*$perPage; return [$pages,$page,$offset]; }
function trx_number(PDO $pdo): string {
    $date=date('Ymd');
    $prefix="TRX-{$date}-";
    $stmt=$pdo->prepare("SELECT transaction_number FROM sales WHERE transaction_number LIKE ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$prefix.'%']);
    $row=$stmt->fetch();
    $seq=1;
    if($row){ $seq=(int)substr($row['transaction_number'],-5)+1; }
    return $prefix . str_pad((string)$seq,5,'0',STR_PAD_LEFT);
}
function gen_code($prefix,$n,$len=4){ return $prefix . str_pad((string)$n,$len,'0',STR_PAD_LEFT); }
function store_info(){
    static $s = null;
    if($s === null){
        try {
            $s = db()->query("SELECT * FROM stores LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch(Throwable $e){
            $s = [];
        }
    }
    return $s;
}
function store_logo_url(){
    $st = store_info();
    if(!empty($st['logo']) && file_exists(__DIR__ . '/../uploads/' . $st['logo'])){
        return APP_URL . '/uploads/' . $st['logo'];
    }
    return null;
}
function time_ago($datetime){
    if(empty($datetime)) return '-';
    $ts = strtotime($datetime);
    if($ts === false) return '-';
    $diff = time() - $ts;
    if($diff < 0) $diff = 0;
    if($diff < 60) return 'baru saja';
    $m = (int)floor($diff/60);
    if($m < 60) return $m.' menit lalu';
    $h = (int)floor($m/60);
    if($h < 24) return $h.' jam lalu';
    $d = (int)floor($h/24);
    if($d < 30) return $d.' hari lalu';
    $mo = (int)floor($d/30);
    if($mo < 12) return $mo.' bulan lalu';
    return (int)floor($mo/12).' tahun lalu';
}
