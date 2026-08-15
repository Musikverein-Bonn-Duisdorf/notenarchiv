<?php
/** POST /api/compositions/cover.php — multipart: id, cover */
require_once dirname(__DIR__).'/_bootstrap.php';
apiRequireMethod('POST');
apiRequireBearerAdmin();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if($id < 1) {
    apiJsonExit(array('error' => 'invalid_id'), 400);
}
if(empty($_FILES['cover'])) {
    apiJsonExit(array('error' => 'no_file'), 400);
}
$result = archivCompositionCoverUpload($id, $_FILES['cover']);
if(empty($result['ok'])) {
    $code = (isset($result['error']) && $result['error'] === 'not_found') ? 404 : 400;
    apiJsonExit(array('error' => isset($result['error']) ? $result['error'] : 'upload_failed'), $code);
}
apiJsonExit(array('ok' => true, 'path' => $result['path']));
?>
