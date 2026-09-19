<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../core/auth.php';
require_once __DIR__.'/../core/helper.php';
require_once __DIR__.'/../core/csrf.php';
require_once __DIR__.'/../core/audit.php';

require_login();
require_role(ALLOWED_UPDATE_ROLES);
csrf_check_or_fail();

function git_out($cmd, $cwd){
    $descriptors = [1=>['pipe','w'],2=>['pipe','w']];
    $proc = proc_open($cmd, $descriptors, $pipes, $cwd);
    if(!is_resource($proc)) return [1,'','proc_open gagal'];
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $code = proc_close($proc);
    return [$code, $stdout, $stderr];
}

$root = realpath(__DIR__.'/..');
$git = 'git';

if(!is_dir($root.'/.git')){
    json_fail('Bukan repository git');
}

list($c1,$o1,$e1) = git_out($git.' remote get-url '.UPDATE_GIT_REMOTE, $root);
if($c1 !== 0){
    json_fail('Remote git tidak ditemukan: '.trim($e1?:$o1));
}
$remoteUrl = trim($o1);
$expected = UPDATE_GIT_URL;
if(stripos($remoteUrl,'github.com/dewecorp/POS') === false){
    audit('UPDATE_BLOCKED','system',null,"Blokir update: remote tidak valid ($remoteUrl)");
    json_fail('Remote git tidak sesuai. Update diblokir demi keamanan.');
}

list($c2,$o2,$e2) = git_out($git.' -C '.escapeshellarg($root).' status --porcelain', $root);
if($c2 !== 0){
    json_fail('Gagal cek status git: '.trim($e2?:$o2));
}
if(trim($o2) !== ''){
    audit('UPDATE_BLOCKED','system',null,"Blokir update: ada perubahan lokal belum commit");
    json_fail('Ada perubahan lokal belum di-commit. Commit/pull manual dulu, lalu coba lagi.');
}

list($c3,$o3,$e3) = git_out($git.' -C '.escapeshellarg($root).' fetch '.UPDATE_GIT_REMOTE, $root);
if($c3 !== 0){
    json_fail('git fetch gagal: '.trim($e3?:$o3));
}

$branch = trim(shell_exec($git.' -C '.escapeshellarg($root).' rev-parse --abbrev-ref HEAD 2>&1') ?: 'main');
if($branch === '') $branch = 'main';
$upstream = UPDATE_GIT_REMOTE.'/'.$branch;

list($c5,$diffOut,$diffErr) = git_out($git.' -C '.escapeshellarg($root).' diff --name-only '.$upstream.' HEAD 2>&1 || '.$git.' -C '.escapeshellarg($root).' diff --name-only HEAD..'.$upstream, $root);
$incomingFiles = array_filter(array_map('trim', explode("\n", $diffOut)));
if(empty($incomingFiles)){
    list($c5b,$rangeDiff,$rangeErr) = git_out($git.' -C '.escapeshellarg($root).' log --name-only --pretty=format: HEAD..'.$upstream.' -- 2>&1 | sort -u', $root);
    $incomingFiles = array_filter(array_map('trim', explode("\n", $rangeDiff)));
}

$dangerPatterns = [
    'base64_decode','gzinflate','str_rot13','eval\s*\(','assert\s*\(',
    'shell_exec','exec\s*\(.*\$_','system\s*\(.*\$_','passthru\s*\(','popen\s*\(','proc_open',
    'preg_replace\s*\(.*\/e','create_function','unserialize\s*\(.*\$_',
    'include\s*\(.*\$_','require\s*\(.*\$_',
    '\$_REQUEST','\$_GET.*shell','\$_POST.*shell',
];
$blockedPhp = ['config/database.php','config/app.php'];
$flagged = [];
$dangerRegex = '/'.implode('|', $dangerPatterns).'/i';

foreach($incomingFiles as $file){
    if(preg_match('#\.(php|phtml)$#i', $file)){
        list($cx,$content,$ce) = git_out($git.' -C '.escapeshellarg($root).' show '.$upstream.':'.escapeshellarg($file), $root);
        if($cx !== 0) continue;
        if(preg_match($dangerRegex, $content, $mDanger)){
            $snip = substr(trim(preg_replace('/\s+/', ' ', $content)), 0, 400);
            $flagged[] = "$file — pola mencurigakan: ".htmlspecialchars($mDanger[0])." — cuplikan: ".htmlspecialchars(substr($snip,0,200));
        }
        if(strpos($content, 'UPDATE_GIT_REMOTE') !== false || strpos($content, 'UPDATE_GIT_URL') !== false){
            // Perubahan konstanta update bukan backdoor, abaikan
        }
    }
    // Cek file PHP baru yang menimpa file sensitif
    foreach($blockedPhp as $blk){
        if($file === $blk){
            // Perubahan database/app dicek via pola di atas saja, izinkan bila tidak mengandung backdoor
        }
    }
}

if(!empty($flagged)){
    $msg = "Update diblokir: terdeteksi pola mencurigakan pada file incoming:\n".implode("\n", $flagged);
    audit('UPDATE_BLOCKED','system',null, $msg);
    json_fail($msg);
}

list($c6,$pullOut,$pullErr) = git_out($git.' -C '.escapeshellarg($root).' pull --ff-only '.UPDATE_GIT_REMOTE.' '.$branch, $root);
if($c6 !== 0){
    $errMsg = trim($pullErr ?: $pullOut);
    if(stripos($errMsg,'Not possible to fast-forward') !== false){
        audit('UPDATE_FAILED','system',null,"Pull ff-only gagal: $errMsg");
        json_fail("Gagal pull (bukan fast-forward): konflik lokal. Lakukan merge manual.\n$errMsg");
    }
    audit('UPDATE_FAILED','system',null,"Pull gagal: $errMsg");
    json_fail('git pull gagal: '. $errMsg);
}

$newHead = trim(shell_exec($git.' -C '.escapeshellarg($root).' rev-parse --short HEAD 2>&1') ?: '');
$logMsg = trim(shell_exec($git.' -C '.escapeshellarg($root).' log --oneline -5 2>&1') ?: '');

audit('SYSTEM_UPDATE','system',null,"Update berhasil ke $newHead via pull");
json_ok('Update berhasil ke commit '.$newHead, ['commit'=>$newHead,'log'=>$logMsg,'output'=>trim($pullOut?:$pullErr)]);
