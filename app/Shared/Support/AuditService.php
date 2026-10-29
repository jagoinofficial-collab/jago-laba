<?php
final class AuditService {
    public static function log(PDO $pdo,string $action,string $module='',?string $recordId=null,?string $referenceId=null,$before=null,$after=null,string $status='SUCCESS',string $notes=''): void {
        try{
            $st=$pdo->prepare("INSERT INTO system_audit_logs(event_id,event_time,user_id,username,module,action,record_id,reference_id,before_json,after_json,status,notes) VALUES(?,NOW(),?,?,?,?,?,?,?,?,?,?)");
            $st->execute(['AUD-'.date('YmdHis').'-'.strtoupper(substr(bin2hex(random_bytes(4)),0,6)),(string)($_SESSION['user_id']??''),(string)($_SESSION['username']??'system'),$module,$action,$recordId,$referenceId,$before===null?null:json_encode($before,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$after===null?null:json_encode($after,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$status,$notes]);
        }catch(Throwable $e){ error_log('Audit log failed: '.$e->getMessage()); }
    }
}
