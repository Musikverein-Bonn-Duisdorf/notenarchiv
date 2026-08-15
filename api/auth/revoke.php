<?php
/** POST /api/auth/revoke.php — revoke current Bearer token. */
require_once dirname(__DIR__).'/_bootstrap.php';
apiRequireMethod('POST');

$token = archivExtractBearerToken();
if($token === '') {
    apiJsonExit(array('error' => 'unauthorized'), 401);
}
$ok = archivRevokeAppToken($token);
apiJsonExit(array('ok' => (bool)$ok));
?>
