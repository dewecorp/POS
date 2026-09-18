<?php
function v_required($v,$f){ return ($v===''||$v===null)?"$f wajib diisi":null; }
function v_numeric($v,$f){ return !is_numeric($v)?"$f harus angka":null; }
function v_int($v,$f){ return filter_var($v,FILTER_VALIDATE_INT)===false?"$f harus bilangan bulat":null; }
function v_min($v,$min,$f){ return $v<$min?"$f minimal $min":null; }
function v_maxlen($v,$m,$f){ return mb_strlen((string)$v)>$m?"$f maksimal $m karakter":null; }
function v_email($v){ return !filter_var($v,FILTER_VALIDATE_EMAIL)?"Email tidak valid":null; }
function collect_errors(array $rules): array {
    $e=[]; foreach($rules as $r){ if($r) $e[]=$r; } return $e;
}
