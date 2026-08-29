<?php
/**
 * GET /api/compositions.php?id=&q=&limit=
 * POST JSON: title, composerId?/composerIds?, arrangerId?/arrangerIds?, publisherId?, year?, grade?, performanceTime?, registrationNumber?, website?, recording?
 * PATCH JSON: id + same optional fields
 * PATCH JSON: id + same optional fields
 * DELETE ?id= or JSON id
 */
require_once __DIR__.'/_bootstrap.php';

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');
apiRequireBearerAdmin();

$defaultCover = isset($GLOBALS['optionsDB']['defaultCompositionCover'])
    ? (string)$GLOBALS['optionsDB']['defaultCompositionCover']
    : '';

function apiCompositionPayload(Composition $p, $defaultCover) {
    $cover = $p->getCover();
    $website = trim((string)$p->Website);
    $recording = trim((string)$p->Recording);
    $composerIds = $p->getPersonIds('composer');
    $arrangerIds = $p->getPersonIds('arranger');
    return array(
        'id' => (int)$p->Index,
        'title' => archivPlainText($p->Title),
        'registrationNumber' => $p->RegistrationNumber !== null ? (int)$p->RegistrationNumber : null,
        'composerId' => !empty($composerIds[0]) ? (int)$composerIds[0] : ($p->Composer ? (int)$p->Composer : null),
        'arrangerId' => !empty($arrangerIds[0]) ? (int)$arrangerIds[0] : ($p->Arranger ? (int)$p->Arranger : null),
        'composerIds' => array_values(array_map('intval', $composerIds)),
        'arrangerIds' => array_values(array_map('intval', $arrangerIds)),
        'publisherId' => $p->Publisher ? (int)$p->Publisher : null,
        'year' => $p->Year !== null && $p->Year !== '' ? (int)$p->Year : null,
        'grade' => $p->Grade !== null && $p->Grade !== '' ? (float)$p->Grade : null,
        'performanceTime' => archivPlainText($p->PerformanceTime),
        'website' => $website !== '' ? $website : null,
        'recording' => $recording !== '' ? $recording : null,
        'publisherUrl' => (($u = $p->publisherExternalUrl()) !== '') ? $u : null,
        'cover' => ($cover !== '' && $cover !== $defaultCover) ? $cover : null,
        'hasCustomCover' => ($cover !== '' && $cover !== $defaultCover),
    );
}

function apiNormalizePersonIds($body, $arrayKey, $scalarKey) {
    if(array_key_exists($arrayKey, $body) && is_array($body[$arrayKey])) {
        $out = array();
        $pos = 1;
        foreach($body[$arrayKey] as $id) {
            $id = (int)$id;
            if($id < 1) {
                continue;
            }
            $out[] = array('id' => $id, 'number' => $pos);
            $pos++;
        }
        return $out;
    }
    if(array_key_exists($scalarKey, $body)) {
        $id = $body[$scalarKey] !== null && $body[$scalarKey] !== '' ? (int)$body[$scalarKey] : 0;
        return $id > 0 ? array(array('id' => $id, 'number' => 1)) : array();
    }
    return null;
}

if($method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if($id > 0) {
        $p = new Composition;
        $p->load_by_id($id);
        if((int)$p->Index < 1) {
            apiJsonExit(array('error' => 'not_found'), 404);
        }
        apiJsonExit(array('ok' => true, 'composition' => apiCompositionPayload($p, $defaultCover)));
    }

    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 50;
    $where = '';
    if($q !== '') {
        $esc = mysqli_real_escape_string($GLOBALS['conn'], $q);
        $where = ' WHERE `Title` LIKE "%'.$esc.'%" OR CAST(`RegistrationNumber` AS CHAR) LIKE "%'.$esc.'%"';
    }
    $sql = sprintf(
        'SELECT `Index`, `Title`, `RegistrationNumber`, `Composer`, `Arranger`, `Publisher`, `Year`, `Grade`, `PerformanceTime`, `FilePath`, `Website`, `Recording`
         FROM `%sComposition`%s ORDER BY `RegistrationNumber` DESC LIMIT %d;',
        $GLOBALS['dbprefix'],
        $where,
        $limit
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    $list = array();
    while($dbr && ($row = mysqli_fetch_assoc($dbr))) {
        $p = new Composition;
        $p->fill_from_array($row);
        $list[] = apiCompositionPayload($p, $defaultCover);
    }
    apiJsonExit(array('ok' => true, 'compositions' => $list));
}

$body = archivApiReadJsonBody();

if($method === 'POST') {
    $title = isset($body['title']) ? trim((string)$body['title']) : '';
    if($title === '') {
        apiJsonExit(array('error' => 'invalid_title'), 400);
    }
    $composers = apiNormalizePersonIds($body, 'composerIds', 'composerId');
    $arrangers = apiNormalizePersonIds($body, 'arrangerIds', 'arrangerId');
    if($composers === null) {
        $composers = array();
    }
    if($arrangers === null) {
        $arrangers = array();
    }
    $p = new Composition;
    $p->Title = $title;
    $p->Composer = !empty($composers[0]['id']) ? (int)$composers[0]['id'] : null;
    $p->Arranger = !empty($arrangers[0]['id']) ? (int)$arrangers[0]['id'] : null;
    $p->Publisher = isset($body['publisherId']) ? (int)$body['publisherId'] : null;
    $p->Year = isset($body['year']) && $body['year'] !== '' && $body['year'] !== null ? (int)$body['year'] : null;
    $p->Grade = isset($body['grade']) && $body['grade'] !== '' && $body['grade'] !== null ? (float)$body['grade'] : 0;
    $p->PerformanceTime = isset($body['performanceTime']) ? trim((string)$body['performanceTime']) : '';
    $p->Website = isset($body['website']) ? trim((string)$body['website']) : '';
    $p->Recording = isset($body['recording']) ? trim((string)$body['recording']) : '';
    $p->RegistrationNumber = isset($body['registrationNumber']) && (int)$body['registrationNumber'] > 0
        ? (int)$body['registrationNumber']
        : nextArchiverNumber();
    $p->FilePath = '';
    $p->save();
    if((int)$p->Index < 1) {
        apiJsonExit(array('error' => 'save_failed'), 500);
    }
    archivSyncCompositionPersons((int)$p->Index, 'composer', $composers);
    archivSyncCompositionPersons((int)$p->Index, 'arranger', $arrangers);
    apiJsonExit(array('ok' => true, 'id' => (int)$p->Index, 'registrationNumber' => (int)$p->RegistrationNumber));
}

if($method === 'PATCH') {
    $id = isset($body['id']) ? (int)$body['id'] : 0;
    if($id < 1) {
        apiJsonExit(array('error' => 'invalid_id'), 400);
    }
    $p = new Composition;
    $p->load_by_id($id);
    if((int)$p->Index < 1) {
        apiJsonExit(array('error' => 'not_found'), 404);
    }
    if(array_key_exists('title', $body)) {
        $title = trim((string)$body['title']);
        if($title === '') {
            apiJsonExit(array('error' => 'invalid_title'), 400);
        }
        $p->Title = $title;
    }
    $composers = apiNormalizePersonIds($body, 'composerIds', 'composerId');
    $arrangers = apiNormalizePersonIds($body, 'arrangerIds', 'arrangerId');
    if($composers !== null) {
        $p->Composer = !empty($composers[0]['id']) ? (int)$composers[0]['id'] : null;
    }
    if($arrangers !== null) {
        $p->Arranger = !empty($arrangers[0]['id']) ? (int)$arrangers[0]['id'] : null;
    }
    if(array_key_exists('publisherId', $body)) {
        $p->Publisher = $body['publisherId'] !== null && $body['publisherId'] !== '' ? (int)$body['publisherId'] : null;
    }
    if(array_key_exists('year', $body)) {
        $p->Year = $body['year'] !== null && $body['year'] !== '' ? (int)$body['year'] : null;
    }
    if(array_key_exists('grade', $body)) {
        $p->Grade = $body['grade'] !== null && $body['grade'] !== '' ? (float)$body['grade'] : 0;
    }
    if(array_key_exists('performanceTime', $body)) {
        $p->PerformanceTime = trim((string)$body['performanceTime']);
    }
    if(array_key_exists('website', $body)) {
        $p->Website = $body['website'] !== null ? trim((string)$body['website']) : '';
    }
    if(array_key_exists('recording', $body)) {
        $p->Recording = $body['recording'] !== null ? trim((string)$body['recording']) : '';
    }
    if(array_key_exists('registrationNumber', $body) && (int)$body['registrationNumber'] > 0) {
        $p->RegistrationNumber = (int)$body['registrationNumber'];
    }
    $p->save();
    if($composers !== null) {
        archivSyncCompositionPersons((int)$p->Index, 'composer', $composers);
    }
    if($arrangers !== null) {
        archivSyncCompositionPersons((int)$p->Index, 'arranger', $arrangers);
    }
    apiJsonExit(array('ok' => true, 'id' => (int)$p->Index));
}

if($method === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($body['id']) ? (int)$body['id'] : 0);
    if($id < 1) {
        apiJsonExit(array('error' => 'invalid_id'), 400);
    }
    $p = new Composition;
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
