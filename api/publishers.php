<?php
/**
 * GET /api/publishers.php?id=&q=&limit=
 * POST JSON: name, website?, address?
 * PATCH JSON: id, name?, website?, address?
 * DELETE ?id= / JSON id
 */
require_once __DIR__.'/_bootstrap.php';

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');
apiRequireBearerAdmin();

if($method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if($id > 0) {
        $p = new Publisher;
        $p->load_by_id($id);
        if((int)$p->Index < 1) {
            apiJsonExit(array('error' => 'not_found'), 404);
        }
        $avatar = archivEntityAvatarFind('Publishers', $id);
        apiJsonExit(array(
            'ok' => true,
            'publisher' => array(
                'id' => (int)$p->Index,
                'name' => archivPlainText($p->Name),
                'address' => archivPlainText($p->Address),
                'website' => archivPlainText($p->Website),
                'avatar' => $avatar !== '' ? $avatar : null,
            ),
        ));
    }
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 100;
    $where = '';
    if($q !== '') {
        $esc = mysqli_real_escape_string($GLOBALS['conn'], $q);
        $where = ' WHERE `Name` LIKE "%'.$esc.'%" OR `Website` LIKE "%'.$esc.'%" OR `Address` LIKE "%'.$esc.'%"';
    }
    $sql = sprintf(
        'SELECT `Index`, `Name`, `Address`, `Website` FROM `%sPublisher`%s ORDER BY `Name` ASC LIMIT %d;',
        $GLOBALS['dbprefix'],
        $where,
        $limit
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    $list = array();
    while($dbr && ($row = mysqli_fetch_assoc($dbr))) {
        $pid = (int)$row['Index'];
        $avatar = archivEntityAvatarFind('Publishers', $pid);
        $list[] = array(
            'id' => $pid,
            'name' => archivPlainText($row['Name']),
            'address' => archivPlainText($row['Address']),
            'website' => archivPlainText($row['Website']),
            'avatar' => $avatar !== '' ? $avatar : null,
        );
    }
    apiJsonExit(array('ok' => true, 'publishers' => $list));
}

$body = archivApiReadJsonBody();

if($method === 'POST') {
    $name = isset($body['name']) ? trim((string)$body['name']) : '';
    if($name === '') {
        apiJsonExit(array('error' => 'invalid_name'), 400);
    }
    $p = new Publisher;
    $p->Name = $name;
    $p->Address = isset($body['address']) ? trim((string)$body['address']) : '';
    $p->Website = isset($body['website']) ? trim((string)$body['website']) : '';
    $p->save();
    if((int)$p->Index < 1) {
        apiJsonExit(array('error' => 'save_failed'), 500);
    }
    apiJsonExit(array('ok' => true, 'id' => (int)$p->Index));
}

if($method === 'PATCH') {
    $id = isset($body['id']) ? (int)$body['id'] : 0;
    if($id < 1) {
        apiJsonExit(array('error' => 'invalid_id'), 400);
    }
    $p = new Publisher;
    $p->load_by_id($id);
    if((int)$p->Index < 1) {
        apiJsonExit(array('error' => 'not_found'), 404);
    }
    if(array_key_exists('name', $body)) {
        $name = trim((string)$body['name']);
        if($name === '') {
            apiJsonExit(array('error' => 'invalid_name'), 400);
        }
        $p->Name = $name;
    }
    if(array_key_exists('address', $body)) {
        $p->Address = trim((string)$body['address']);
    }
    if(array_key_exists('website', $body)) {
        $p->Website = trim((string)$body['website']);
    }
    $p->save();
    apiJsonExit(array('ok' => true, 'id' => (int)$p->Index));
}

if($method === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($body['id']) ? (int)$body['id'] : 0);
    if($id < 1) {
        apiJsonExit(array('error' => 'invalid_id'), 400);
    }
    $p = new Publisher;
    $p->load_by_id($id);
    if((int)$p->Index < 1) {
        apiJsonExit(array('error' => 'not_found'), 404);
    }
    if(!$p->delete()) {
        apiJsonExit(array('error' => 'delete_failed'), 500);
    }
    apiJsonExit(array('ok' => true));
}

apiJsonExit(array('error' => 'method_not_allowed'), 405);
?>
