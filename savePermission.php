<?php
/**
 * Toggle one local Archiv permission (ARCHIV-42).
 */
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
include_once 'common/include.php';
mysqli_select_db($GLOBALS['conn'], $sql['database']) or die(mysqli_error($GLOBALS['conn']));

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function archivPermJsonOut($payload, $code = 200) {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

if(!loggedIn()) {
    archivPermJsonOut(array('ok' => false, 'error' => 'not_logged_in'), 403);
}
if(!empty($_SESSION['singleUsePW'])) {
    archivPermJsonOut(array('ok' => false, 'error' => 'password_change_required'), 403);
}
if(!hasPermission('perm_editPermissions')) {
    archivPermJsonOut(array('ok' => false, 'error' => 'forbidden'), 403);
}
if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    archivPermJsonOut(array('ok' => false, 'error' => 'method'), 405);
}

$userId = isset($_POST['user']) ? (int)$_POST['user'] : 0;
$perm = isset($_POST['perm']) ? (string)$_POST['perm'] : '';
$value = isset($_POST['value']) ? (int)$_POST['value'] : 0;
$sessionUserId = isset($_SESSION['userid']) ? (int)$_SESSION['userid'] : 0;

$result = ArchivPermissions::setPermission($userId, $perm, $value, $sessionUserId);
if($result === 'cannot_remove_own_edit') {
    archivPermJsonOut(array('ok' => false, 'error' => 'cannot_remove_own_edit'), 400);
}
if($result !== true) {
    archivPermJsonOut(array('ok' => false, 'error' => is_string($result) ? $result : 'invalid'), 400);
}

if($sessionUserId === $userId) {
    refreshSessionAdmin();
}

archivPermJsonOut(array(
    'ok' => true,
    'user' => $userId,
    'perm' => $perm,
    'value' => $value ? 1 : 0,
));
?>
