<?php
/** POST /api/publishers/avatar.php — multipart: id, avatar */
require_once dirname(__DIR__).'/_bootstrap.php';
apiRequireMethod('POST');
apiRequireBearerAdmin();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if($id < 1) {
    $body = archivApiReadJsonBody();
    $id = isset($body['id']) ? (int)$body['id'] : 0;
}
if($id < 1) {
    apiJsonExit(array('error' => 'invalid_id'), 400);
}
$p = new Publisher;
$p->load_by_id($id);
if((int)$p->Index < 1) {
    apiJsonExit(array('error' => 'not_found'), 404);
}
if(empty($_FILES['avatar'])) {
    apiJsonExit(array('error' => 'no_file'), 400);
}
$result = archivEntityAvatarUpload(
    'Publishers',
    $id,
    $_FILES['avatar'],
    sprintf('Publisher-ID: %d', $id)
);
if(empty($result['ok'])) {
    apiJsonExit(array('error' => isset($result['error']) ? $result['error'] : 'upload_failed'), 400);
}
apiJsonExit(array('ok' => true, 'path' => $result['path']));
?>
