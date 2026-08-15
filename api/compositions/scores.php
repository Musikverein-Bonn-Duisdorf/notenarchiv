<?php
/**
 * GET /api/compositions/scores.php?composition=
 * POST multipart: composition, instrument, voice, file
 * DELETE ?id=
 */
require_once dirname(__DIR__).'/_bootstrap.php';

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');
apiRequireBearerAdmin();

if($method === 'GET') {
    $compositionId = isset($_GET['composition']) ? (int)$_GET['composition'] : 0;
    if($compositionId < 1) {
        apiJsonExit(array('error' => 'invalid_composition'), 400);
    }
    $sql = sprintf(
        'SELECT `Index`, `Composition`, `Instrument`, `VoiceLabel`, `FilePath` FROM `%sScoreFile` WHERE `Composition` = %d ORDER BY `Instrument` ASC, `VoiceLabel` ASC;',
        $GLOBALS['dbprefix'],
        $compositionId
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    $list = array();
    while($dbr && ($row = mysqli_fetch_assoc($dbr))) {
        $list[] = array(
            'id' => (int)$row['Index'],
            'compositionId' => (int)$row['Composition'],
            'instrumentId' => (int)$row['Instrument'],
            'voice' => (string)$row['VoiceLabel'],
            'filePath' => $row['FilePath'] !== null && $row['FilePath'] !== '' ? (string)$row['FilePath'] : null,
        );
    }
    apiJsonExit(array('ok' => true, 'scores' => $list));
}

if($method === 'POST') {
    $compositionId = isset($_POST['composition']) ? (int)$_POST['composition'] : 0;
    $instrumentId = isset($_POST['instrument']) ? (int)$_POST['instrument'] : 0;
    $voice = isset($_POST['voice']) ? trim((string)$_POST['voice']) : '';
    if(empty($_FILES['file'])) {
        apiJsonExit(array('error' => 'no_file'), 400);
    }
    $result = archivScoreUpload($compositionId, $instrumentId, $voice, $_FILES['file']);
    if(empty($result['ok'])) {
        $code = (isset($result['error']) && $result['error'] === 'not_found') ? 404 : 400;
        apiJsonExit(array('error' => isset($result['error']) ? $result['error'] : 'upload_failed'), $code);
    }
    apiJsonExit(array('ok' => true, 'id' => $result['id'], 'path' => $result['path']));
}

if($method === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if($id < 1) {
        $body = archivApiReadJsonBody();
        $id = isset($body['id']) ? (int)$body['id'] : 0;
    }
    if($id < 1) {
        apiJsonExit(array('error' => 'invalid_id'), 400);
    }
    $sf = new ScoreFile;
    $sf->load_by_id($id);
    if((int)$sf->Index < 1) {
        apiJsonExit(array('error' => 'not_found'), 404);
    }
    $piece = new Composition;
    $piece->load_by_id((int)$sf->Composition);
    if($sf->FilePath && (int)$piece->Index > 0) {
        $path = $piece->getFilePathPHP().$sf->FilePath;
        if(is_file($path)) {
            @unlink($path);
        }
    }
    $sql = sprintf('DELETE FROM `%sScoreFile` WHERE `Index` = %d;', $GLOBALS['dbprefix'], $id);
    mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    $log = new Log;
    $log->DBdelete(sprintf('ScoreFile-ID: %d', $id));
    apiJsonExit(array('ok' => true));
}

apiJsonExit(array('error' => 'method_not_allowed'), 405);
?>
