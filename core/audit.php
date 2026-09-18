<?php
function audit($action,$module,$record_id=null,$desc='',$old=null,$new=null){
    try{
        $pdo=db();
        $u=current_user();
        $uid=$u['id']??null;
        $ip=$_SERVER['REMOTE_ADDR']??'';
        $ua=substr($_SERVER['HTTP_USER_AGENT']??'',0,500);
        $stmt=$pdo->prepare("INSERT INTO audit_logs (user_id,action,module,record_id,description,ip_address,user_agent,old_data,new_data) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$uid,$action,$module,$record_id,$desc,$ip,$ua,$old?json_encode($old,JSON_UNESCAPED_UNICODE):null,$new?json_encode($new,JSON_UNESCAPED_UNICODE):null]);
    }catch(Throwable $e){}
}
