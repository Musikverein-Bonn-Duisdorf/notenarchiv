<?php
/** POST /api/auth/token.php — login + password → Bearer token (admin only). */
require_once dirname(__DIR__).'/_bootstrap.php';
apiRequireMethod('POST');

$body = archivApiReadJsonBody();
$login = isset($body['login']) ? $body['login'] : (isset($_POST['login']) ? $_POST['login'] : '');
$password = isset($body['password']) ? $body['password'] : (isset($_POST['password']) ? $_POST['password'] : '');
$device = isset($body['device']) ? $body['device'] : (isset($_POST['device']) ? $_POST['device'] : 'api');

if(!validateUser($login, $password)) {
    apiJsonExit(array('error' => 'invalid_credentials'), 401);
}
if(empty($_SESSION['admin'])) {
    apiJsonExit(array('error' => 'forbidden'), 403);
}
$token = archivCreateAppToken((int)$_SESSION['userid'], $device);
if(!$token) {
    apiJsonExit(array('error' => 'token_create_failed'), 500);
}
apiJsonExit(array(
    'ok' => true,
    'token' => $token,
    'user' => array(
        'id' => (int)$_SESSION['userid'],
        'name' => isset($_SESSION['username']) ? (string)$_SESSION['username'] : '',
    ),
));
?>
