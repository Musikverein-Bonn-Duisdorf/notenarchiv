<?php
/**
 * ARCHIV-31: helpers for JSON API handlers (uploads, gaps, JSON body).
 */

function archivApiReadJsonBody() {
    $raw = file_get_contents('php://input');
    if($raw === false || trim($raw) === '') {
        return array();
    }
    $decoded = json_decode($raw, true);
    if(!is_array($decoded)) {
        return array();
    }
    if(array_key_exists('runNote', $decoded) && trim((string)$decoded['runNote']) !== '') {
        if(function_exists('archivSetApiRunNote') && archivApiRunNote() === '') {
            archivSetApiRunNote((string)$decoded['runNote']);
        }
    }
    return $decoded;
}

function archivApiAcceptUploadTmp($tmp) {
    $tmp = (string)$tmp;
    if($tmp === '') {
        return false;
    }
    if(is_uploaded_file($tmp)) {
        return true;
    }
    // PHPUnit / CLI fixtures
    return PHP_SAPI === 'cli' && is_file($tmp) && is_readable($tmp);
}

function archivApiMoveUpload($tmp, $dest) {
    if(is_uploaded_file($tmp)) {
        return move_uploaded_file($tmp, $dest);
    }
    if(PHP_SAPI === 'cli' && is_file($tmp)) {
        return @rename($tmp, $dest) || @copy($tmp, $dest);
    }
    return false;
}

/**
 * @param array $file $_FILES-style entry
 * @return array{ok:bool,path?:string,error?:string}
 */
function archivCompositionCoverUpload($compositionId, array $file) {
    $compositionId = (int)$compositionId;
    $piece = new Composition;
    $piece->load_by_id($compositionId);
    if((int)$piece->Index < 1) {
        return array('ok' => false, 'error' => 'not_found');
    }
    if(empty($file['tmp_name']) || !archivApiAcceptUploadTmp($file['tmp_name'])) {
        return array('ok' => false, 'error' => 'no_file');
    }
    if(!empty($file['error']) && (int)$file['error'] !== UPLOAD_ERR_OK && (int)$file['error'] !== 0) {
        return array('ok' => false, 'error' => 'upload_error');
    }
    if(!empty($file['size']) && (int)$file['size'] > 500000) {
        return array('ok' => false, 'error' => 'too_large');
    }
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if(!in_array($ext, array('png', 'jpg', 'jpeg', 'gif'), true)) {
        return array('ok' => false, 'error' => 'bad_type');
    }
    if(@getimagesize($file['tmp_name']) === false) {
        return array('ok' => false, 'error' => 'not_image');
    }
    if(!$piece->FilePath) {
        $piece->FilePath = 'data/Compositions/'.$compositionId.'/';
        $piece->save();
    }
    $dir = $piece->getFilePathPHP();
    if(!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $piece->deleteCover(false);
    $dest = $dir.'cover.'.$ext;
    if(!archivApiMoveUpload($file['tmp_name'], $dest)) {
        return array('ok' => false, 'error' => 'move_failed');
    }
    $piece->logCoverUpload($dest);
    return array('ok' => true, 'path' => $piece->FilePath.'cover.'.$ext);
}

/**
 * Score upload returning structured result (no echo).
 * @return array{ok:bool,id?:int,error?:string,path?:string}
 */
function archivScoreUpload($compositionId, $instrumentId, $voiceLabel, array $file) {
    $compositionId = (int)$compositionId;
    $instrumentId = (int)$instrumentId;
    $voiceLabel = trim((string)$voiceLabel);
    $piece = new Composition;
    $piece->load_by_id($compositionId);
    if((int)$piece->Index < 1) {
        return array('ok' => false, 'error' => 'not_found');
    }
    if($instrumentId < 1 || $voiceLabel === '') {
        return array('ok' => false, 'error' => 'invalid_voice');
    }
    if(empty($file['tmp_name']) || !archivApiAcceptUploadTmp($file['tmp_name'])) {
        return array('ok' => false, 'error' => 'no_file');
    }
    if(!empty($file['size']) && (int)$file['size'] > 50000000) {
        return array('ok' => false, 'error' => 'too_large');
    }
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if(!in_array($ext, array('pdf', 'png', 'jpg', 'jpeg', 'gif'), true)) {
        return array('ok' => false, 'error' => 'bad_type');
    }
    if(!$piece->FilePath) {
        $piece->FilePath = 'data/Compositions/'.$compositionId.'/';
        $piece->save();
    }
    $dir = $piece->getFilePathPHP();
    if(!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $relName = 'part_'.$instrumentId.'_'.$voiceLabel.'.'.$ext;
    $dest = $dir.$relName;
    if(!archivApiMoveUpload($file['tmp_name'], $dest)) {
        return array('ok' => false, 'error' => 'move_failed');
    }

    $existing = null;
    $sql = sprintf(
        'SELECT `Index` FROM `%sScoreFile` WHERE `Composition` = %d AND `Instrument` = %d AND `VoiceLabel` = "%s" LIMIT 1;',
        $GLOBALS['dbprefix'],
        $compositionId,
        $instrumentId,
        mysqli_real_escape_string($GLOBALS['conn'], $voiceLabel)
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
    $sf = new ScoreFile;
    if($row && (int)$row['Index'] > 0) {
        $sf->load_by_id((int)$row['Index']);
    }
    $sf->Composition = $compositionId;
    $sf->Instrument = $instrumentId;
    $sf->VoiceLabel = $voiceLabel;
    $sf->FilePath = $relName;
    $sf->save();
    return array('ok' => true, 'id' => (int)$sf->Index, 'path' => $piece->FilePath.$relName);
}

/**
 * @return array{composers_without_avatar:array,compositions_without_cover:array,scores_missing?:array}
 */
function archivApiMediaGaps($limit = 100, $includeMissingScores = false) {
    $limit = max(1, min(500, (int)$limit));
    $out = array(
        'composers_without_avatar' => array(),
        'compositions_without_cover' => array(),
    );

    $sql = sprintf(
        'SELECT `Index`, `FirstName`, `LastName` FROM `%sComposer` ORDER BY `LastName` ASC, `FirstName` ASC;',
        $GLOBALS['dbprefix']
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    $n = 0;
    while($dbr && ($row = mysqli_fetch_assoc($dbr)) && $n < $limit) {
        $id = (int)$row['Index'];
        if(archivEntityAvatarFind('Composers', $id) === '') {
            $out['composers_without_avatar'][] = array(
                'id' => $id,
                'firstName' => (string)$row['FirstName'],
                'lastName' => (string)$row['LastName'],
            );
            $n++;
        }
    }

    $sql = sprintf(
        'SELECT `Index`, `Title`, `RegistrationNumber`, `FilePath` FROM `%sComposition` ORDER BY `RegistrationNumber` DESC;',
        $GLOBALS['dbprefix']
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    $n = 0;
    while($dbr && ($row = mysqli_fetch_assoc($dbr)) && $n < $limit) {
        $piece = new Composition;
        $piece->fill_from_array($row);
        $cover = $piece->getCover();
        $default = isset($GLOBALS['optionsDB']['defaultCompositionCover'])
            ? (string)$GLOBALS['optionsDB']['defaultCompositionCover']
            : '';
        if($cover === $default || $cover === '') {
            $out['compositions_without_cover'][] = array(
                'id' => (int)$piece->Index,
                'title' => archivPlainText($piece->Title),
                'registrationNumber' => $piece->RegistrationNumber !== null ? (int)$piece->RegistrationNumber : null,
            );
            $n++;
        }
    }

    if($includeMissingScores) {
        $out['compositions_without_scores'] = array();
        $sql = sprintf(
            'SELECT c.`Index`, c.`Title`, c.`RegistrationNumber`
             FROM `%sComposition` c
             LEFT JOIN `%sScoreFile` s ON s.`Composition` = c.`Index`
             WHERE s.`Index` IS NULL
             ORDER BY c.`RegistrationNumber` DESC
             LIMIT %d;',
            $GLOBALS['dbprefix'],
            $GLOBALS['dbprefix'],
            $limit
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        while($dbr && ($row = mysqli_fetch_assoc($dbr))) {
            $out['compositions_without_scores'][] = array(
                'id' => (int)$row['Index'],
                'title' => archivPlainText($row['Title']),
                'registrationNumber' => $row['RegistrationNumber'] !== null ? (int)$row['RegistrationNumber'] : null,
            );
        }
    }

    return $out;
}
?>
