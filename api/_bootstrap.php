<?php
/**
 * Shared bootstrap for Archiv JSON API (ARCHIV-31).
 */
require_once dirname(__DIR__).'/libs/sessionBootstrap.php';
archivConfigureSession();
chdir(dirname(__DIR__));
require_once dirname(__DIR__).'/common/include.php';
mysqli_select_db($GLOBALS['conn'], $sql['database']) or die(mysqli_error($GLOBALS['conn']));

if(function_exists('archivApiCaptureRunNoteFromRequest')) {
    archivApiCaptureRunNoteFromRequest();
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function apiJsonExit($payload, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function apiRequireMethod($methods) {
    $allowed = is_array($methods) ? $methods : array($methods);
    $allowed = array_map('strtoupper', $allowed);
    $method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');
    if(!in_array($method, $allowed, true)) {
        apiJsonExit(array('error' => 'method_not_allowed'), 405);
    }
}

function apiRequireBearerAdmin() {
    $token = archivExtractBearerToken();
    if($token === '') {
        apiJsonExit(array('error' => 'unauthorized'), 401);
    }
    if(!archivValidateAppToken($token)) {
        apiJsonExit(array('error' => 'unauthorized'), 401);
    }
    if(empty($_SESSION['admin'])) {
        apiJsonExit(array('error' => 'forbidden'), 403);
    }
    return $token;
}
?>
