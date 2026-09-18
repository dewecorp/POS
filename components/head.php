<?php
$favLogo = function_exists('store_logo_url') ? store_logo_url() : null;
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php if($favLogo): ?>
<link rel="icon" type="image/png" href="<?=e($favLogo)?>">
<link rel="shortcut icon" type="image/png" href="<?=e($favLogo)?>">
<link rel="apple-touch-icon" href="<?=e($favLogo)?>">
<?php endif; ?>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="<?=APP_URL?>/assets/css/dashboard.css?v=<?=file_exists(__DIR__.'/../assets/css/dashboard.css') ? filemtime(__DIR__.'/../assets/css/dashboard.css') : '1'?>">
