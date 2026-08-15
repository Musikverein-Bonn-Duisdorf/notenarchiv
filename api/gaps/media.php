<?php
/** GET /api/gaps/media.php?limit=&scores=1 */
require_once dirname(__DIR__).'/_bootstrap.php';
apiRequireMethod('GET');
apiRequireBearerAdmin();

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$scores = isset($_GET['scores']) && (string)$_GET['scores'] === '1';
$gaps = archivApiMediaGaps($limit, $scores);
apiJsonExit(array('ok' => true, 'gaps' => $gaps));
?>
