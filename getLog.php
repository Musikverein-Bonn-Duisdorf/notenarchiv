<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
include 'common/include.php';
mysqli_select_db($GLOBALS['conn'], $sql['database']) or die(mysqli_error($GLOBALS['conn']));

if(!loggedIn()) {
    http_response_code(403);
    die('forbidden');
}
if(!hasPermission('perm_showLog')) {
    http_response_code(403);
    die('forbidden');
}

$maxIndex = archivRequest('maxIndex');
if($maxIndex === null || !is_numeric($maxIndex)) {
    http_response_code(400);
    die('invalid maxIndex');
}

$topTimestamp = archivRequest('topTimestamp');
if($topTimestamp === null) {
    $topTimestamp = '';
}

$limit = archivRequest('limit');
$limit = ($limit !== null && is_numeric($limit)) ? (int)$limit : 0;

echo logPollNextHtml((int)$maxIndex, (string)$topTimestamp, $limit);
?>
