<?php
/**
 * GET /api/collections.php?id=&archived=1
 * POST JSON: name, archived?
 * PATCH JSON: id, name?, archived?
 * DELETE JSON/query: id
 */
require_once __DIR__.'/_bootstrap.php';

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');
apiRequireBearerAdmin();

if($method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if($id > 0) {
        $col = new Collections;
        $col->load_by_id($id);
        if((int)$col->Index < 1) {
            apiJsonExit(array('error' => 'not_found'), 404);
        }
        apiJsonExit(array(
            'ok' => true,
            'collection' => array(
                'id' => (int)$col->Index,
                'name' => archivPlainText($col->Name),
                'archived' => (int)$col->Archived ? true : false,
                'items' => $col->getItemsChipSpec(),
            ),
        ));
    }
    $includeArchived = isset($_GET['archived']) && (string)$_GET['archived'] === '1';
    $where = $includeArchived ? '' : ' WHERE COALESCE(`Archived`, 0) = 0';
    $sql = sprintf(
        'SELECT `Index`, `Name`, `Archived` FROM `%sCollection`%s ORDER BY `Index` DESC LIMIT 200;',
        $GLOBALS['dbprefix'],
        $where
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    $list = array();
    while($dbr && ($row = mysqli_fetch_assoc($dbr))) {
        $list[] = array(
            'id' => (int)$row['Index'],
            'name' => archivPlainText($row['Name']),
            'archived' => !empty($row['Archived']),
        );
    }
    apiJsonExit(array('ok' => true, 'collections' => $list));
}

$body = archivApiReadJsonBody();

if($method === 'POST') {
    $name = isset($body['name']) ? trim((string)$body['name']) : '';
    if($name === '') {
        apiJsonExit(array('error' => 'invalid_name'), 400);
    }
    $col = new Collections;
    $col->Name = $name;
    $col->Archived = !empty($body['archived']) ? 1 : 0;
    $col->save();
    if((int)$col->Index < 1) {
        apiJsonExit(array('error' => 'save_failed'), 500);
    }
    apiJsonExit(array('ok' => true, 'id' => (int)$col->Index));
}

if($method === 'PATCH') {
    $id = isset($body['id']) ? (int)$body['id'] : 0;
    if($id < 1) {
        apiJsonExit(array('error' => 'invalid_id'), 400);
    }
    $col = new Collections;
    $col->load_by_id($id);
    if((int)$col->Index < 1) {
        apiJsonExit(array('error' => 'not_found'), 404);
    }
    if(array_key_exists('name', $body)) {
        $name = trim((string)$body['name']);
        if($name === '') {
            apiJsonExit(array('error' => 'invalid_name'), 400);
        }
        $col->Name = $name;
    }
    if(array_key_exists('archived', $body)) {
        $col->Archived = !empty($body['archived']) ? 1 : 0;
    }
    $col->save();
    apiJsonExit(array('ok' => true, 'id' => (int)$col->Index));
}

if($method === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($body['id']) ? (int)$body['id'] : 0);
    if($id < 1) {
        apiJsonExit(array('error' => 'invalid_id'), 400);
    }
    $col = new Collections;
    $col->load_by_id($id);
    if((int)$col->Index < 1) {
        apiJsonExit(array('error' => 'not_found'), 404);
    }
    if(!$col->delete()) {
        apiJsonExit(array('error' => 'delete_failed'), 500);
    }
    apiJsonExit(array('ok' => true));
}

apiJsonExit(array('error' => 'method_not_allowed'), 405);
?>
