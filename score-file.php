<?php
/**
 * Stream a ScoreFile PDF (upload under composition or local mirror).
 */
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
include __DIR__.'/common/include.php';

if(!isset($_SESSION['userid']) || !(int)$_SESSION['userid']) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unauthorized';
    exit;
}
if(!hasPermission('perm_read')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id < 1) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Bad request';
    exit;
}

$sf = new ScoreFile;
$sf->load_by_id($id);
if((int)$sf->Index < 1) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    exit;
}

$piece = new Composition;
$piece->load_by_id((int)$sf->Composition);
$path = ScoreMirror::resolveScorePath($sf, $piece);
if($path === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'File missing';
    exit;
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$types = array(
    'pdf' => 'application/pdf',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
);
$mime = isset($types[$ext]) ? $types[$ext] : 'application/octet-stream';
header('Content-Type: '.$mime);
header('Content-Disposition: inline; filename="'.basename($path).'"');
header('Content-Length: '.(string)filesize($path));
header('Cache-Control: private, no-store');
readfile($path);
exit;
?>
