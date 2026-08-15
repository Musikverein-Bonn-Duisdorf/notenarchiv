<?php
/**
 * GET /api/composers.php?id=&q=&limit=
 * POST JSON: firstName?, lastName
 * PATCH JSON: id, firstName?, lastName?
 */
require_once __DIR__.'/_bootstrap.php';

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');
apiRequireBearerAdmin();

if($method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if($id > 0) {
        $c = new Composer;
        $c->load_by_id($id);
        if((int)$c->Index < 1) {
            apiJsonExit(array('error' => 'not_found'), 404);
        }
        $avatar = archivEntityAvatarFind('Composers', $id);
        apiJsonExit(array(
            'ok' => true,
            'composer' => array(
                'id' => (int)$c->Index,
                'firstName' => (string)$c->FirstName,
                'lastName' => (string)$c->LastName,
                'avatar' => $avatar !== '' ? $avatar : null,
            ),
        ));
    }

    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 50;
    $where = '';
    if($q !== '') {
        $esc = mysqli_real_escape_string($GLOBALS['conn'], $q);
        $where = ' WHERE `LastName` LIKE "%'.$esc.'%" OR `FirstName` LIKE "%'.$esc.'%"';
    }
    $sql = sprintf(
        'SELECT `Index`, `FirstName`, `LastName` FROM `%sComposer`%s ORDER BY `LastName` ASC, `FirstName` ASC LIMIT %d;',
        $GLOBALS['dbprefix'],
        $where,
        $limit
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    $list = array();
    while($dbr && ($row = mysqli_fetch_assoc($dbr))) {
        $cid = (int)$row['Index'];
        $avatar = archivEntityAvatarFind('Composers', $cid);
        $list[] = array(
            'id' => $cid,
            'firstName' => (string)$row['FirstName'],
            'lastName' => (string)$row['LastName'],
            'avatar' => $avatar !== '' ? $avatar : null,
        );
    }
    apiJsonExit(array('ok' => true, 'composers' => $list));
}

$body = archivApiReadJsonBody();

if($method === 'POST') {
    $last = isset($body['lastName']) ? trim((string)$body['lastName']) : '';
    if($last === '') {
        apiJsonExit(array('error' => 'invalid_lastName'), 400);
    }
    $c = new Composer;
    $c->FirstName = isset($body['firstName']) ? trim((string)$body['firstName']) : '';
    $c->LastName = $last;
    $c->save();
    if((int)$c->Index < 1) {
        apiJsonExit(array('error' => 'save_failed'), 500);
    }
    apiJsonExit(array('ok' => true, 'id' => (int)$c->Index));
}

if($method === 'PATCH') {
    $id = isset($body['id']) ? (int)$body['id'] : 0;
    if($id < 1) {
        apiJsonExit(array('error' => 'invalid_id'), 400);
    }
    $c = new Composer;
    $c->load_by_id($id);
    if((int)$c->Index < 1) {
        apiJsonExit(array('error' => 'not_found'), 404);
    }
    if(array_key_exists('firstName', $body)) {
        $c->FirstName = trim((string)$body['firstName']);
    }
    if(array_key_exists('lastName', $body)) {
        $last = trim((string)$body['lastName']);
        if($last === '') {
            apiJsonExit(array('error' => 'invalid_lastName'), 400);
        }
        $c->LastName = $last;
    }
    $c->save();
    apiJsonExit(array('ok' => true, 'id' => (int)$c->Index));
}

apiJsonExit(array('error' => 'method_not_allowed'), 405);
?>
