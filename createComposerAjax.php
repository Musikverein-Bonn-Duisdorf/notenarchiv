<?php
/**
 * ARCHIV-48: Quick-create composer from composition form (session JSON).
 */
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
include_once 'common/include.php';
mysqli_select_db($GLOBALS['conn'], $sql['database']) or die(mysqli_error($GLOBALS['conn']));

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function archivQuickComposerJsonOut($payload, $code = 200) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if(!loggedIn()) {
    archivQuickComposerJsonOut(array('ok' => false, 'error' => 'not_logged_in'), 403);
}
if(!empty($_SESSION['singleUsePW'])) {
    archivQuickComposerJsonOut(array('ok' => false, 'error' => 'password_change_required'), 403);
}
if(empty($_SESSION['admin'])) {
    archivQuickComposerJsonOut(array('ok' => false, 'error' => 'forbidden'), 403);
}
if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    archivQuickComposerJsonOut(array('ok' => false, 'error' => 'method'), 405);
}

$firstName = isset($_POST['firstName']) ? trim((string)$_POST['firstName']) : '';
$lastName = isset($_POST['lastName']) ? trim((string)$_POST['lastName']) : '';
if($lastName === '') {
    archivQuickComposerJsonOut(array('ok' => false, 'error' => 'last_name_required'), 400);
}

$n = new Composer;
$n->FirstName = $firstName;
$n->LastName = $lastName;
$n->save();
$id = (int)$n->Index;
if($id < 1) {
    archivQuickComposerJsonOut(array('ok' => false, 'error' => 'save_failed'), 500);
}

archivQuickComposerJsonOut(array(
    'ok' => true,
    'id' => $id,
    'label' => archivComposerOptionLabel($firstName, $lastName),
));
