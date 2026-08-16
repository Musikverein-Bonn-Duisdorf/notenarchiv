<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
include 'common/include.php';

header('Content-Type: text/html; charset=utf-8');

if(!isset($_SESSION['userid']) || !(int)$_SESSION['userid']) {
    http_response_code(401);
    header('X-Has-More: 0');
    echo '';
    exit;
}

$type = isset($_GET['type']) ? (string)$_GET['type'] : '';
$cursor = isset($_GET['cursor']) ? (string)$_GET['cursor'] : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$sort = isset($_GET['sort']) ? (string)$_GET['sort'] : '';
$dir = isset($_GET['dir']) ? (string)$_GET['dir'] : 'asc';

if($type !== 'log' && !hasPermission('perm_read')) {
    http_response_code(403);
    header('X-Has-More: 0');
    echo '';
    exit;
}

$result = array('html' => '', 'nextCursor' => $cursor, 'hasMore' => false);

switch($type) {
case 'log':
    if(!hasPermission('perm_write')) {
        http_response_code(403);
        header('X-Has-More: 0');
        exit;
    }
    if(!isset($_GET['limit']) || (int)$_GET['limit'] < 1) {
        $limit = listChunkLogConfiguredLimit();
    }
    $q = isset($_GET['q']) ? (string)$_GET['q'] : '';
    $result = listChunkLog($cursor !== '' ? (int)$cursor : 0, $limit, $q);
    break;

case 'compositions':
    $result = listChunkCompositions($cursor !== '' ? (int)$cursor : 0, $limit, $sort, $dir);
    break;

case 'composers':
    $result = listChunkComposers($cursor !== '' ? (int)$cursor : 0, $limit, $sort, $dir);
    break;

case 'publishers':
    $result = listChunkPublishers($cursor !== '' ? (int)$cursor : 0, $limit, $sort, $dir);
    break;

case 'collections':
    $includeArchived = isset($_GET['archived']) && (string)$_GET['archived'] === '1';
    $result = listChunkCollections(
        $cursor !== '' ? (int)$cursor : 0,
        $limit,
        $sort,
        $dir,
        $includeArchived
    );
    break;

default:
    http_response_code(400);
    header('X-Has-More: 0');
    echo '';
    exit;
}

header('X-Has-More: '.($result['hasMore'] ? '1' : '0'));
header('X-Next-Cursor: '.$result['nextCursor']);
echo $result['html'];
?>
