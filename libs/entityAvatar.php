<?php
/**
 * Optional avatar images for Composer / Publisher (filesystem, like composition covers).
 * Paths: data/Composers/{id}/avatar.{ext}, data/Publishers/{id}/avatar.{ext}
 */

function archivEntityAvatarKinds() {
    return array('Composers', 'Publishers');
}

function archivEntityAvatarRelDir($kind, $id) {
    $kind = (string)$kind;
    $id = (int)$id;
    if(!in_array($kind, archivEntityAvatarKinds(), true) || $id < 1) {
        return '';
    }
    return 'data/'.$kind.'/'.$id.'/';
}

function archivEntityAvatarFsDir($kind, $id) {
    $rel = archivEntityAvatarRelDir($kind, $id);
    if($rel === '') {
        return '';
    }
    $root = isset($GLOBALS['optionsDB']['dataDirectory']) ? (string)$GLOBALS['optionsDB']['dataDirectory'] : '';
    return $root.$rel;
}

function archivEntityAvatarEnsureDir($kind, $id) {
    $dir = archivEntityAvatarFsDir($kind, $id);
    if($dir === '') {
        return false;
    }
    if(!is_dir($dir)) {
        return @mkdir($dir, 0775, true);
    }
    return true;
}

/**
 * @return string Relative web path to avatar file, or '' if none.
 */
function archivEntityAvatarFind($kind, $id) {
    $dir = archivEntityAvatarFsDir($kind, $id);
    $relDir = archivEntityAvatarRelDir($kind, $id);
    if($dir === '' || $relDir === '' || !is_dir($dir)) {
        return '';
    }
    foreach(array('png', 'jpg', 'jpeg', 'gif', 'webp') as $ext) {
        $file = $dir.'avatar.'.$ext;
        if(is_file($file)) {
            return $relDir.'avatar.'.$ext;
        }
    }
    return '';
}

/**
 * @param array $file $_FILES['avatar'] style entry
 * @return array{ok:bool,path?:string,error?:string}
 */
function archivEntityAvatarUpload($kind, $id, array $file, $logLabel = '') {
    $id = (int)$id;
    if($id < 1) {
        return array('ok' => false, 'error' => 'invalid id');
    }
    if(empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return array('ok' => false, 'error' => 'no file');
    }
    if(!empty($file['error']) && (int)$file['error'] !== UPLOAD_ERR_OK) {
        return array('ok' => false, 'error' => 'upload error');
    }
    if(!empty($file['size']) && (int)$file['size'] > 500000) {
        return array('ok' => false, 'error' => 'too large');
    }
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if(!in_array($ext, array('png', 'jpg', 'jpeg', 'gif', 'webp'), true)) {
        return array('ok' => false, 'error' => 'bad type');
    }
    $info = @getimagesize($file['tmp_name']);
    if($info === false) {
        return array('ok' => false, 'error' => 'not image');
    }
    if(!archivEntityAvatarEnsureDir($kind, $id)) {
        return array('ok' => false, 'error' => 'mkdir');
    }
    archivEntityAvatarDelete($kind, $id, false);
    $rel = archivEntityAvatarRelDir($kind, $id).'avatar.'.$ext;
    $fs = archivEntityAvatarFsDir($kind, $id).'avatar.'.$ext;
    if(!move_uploaded_file($file['tmp_name'], $fs)) {
        return array('ok' => false, 'error' => 'move failed');
    }
    if(class_exists('Log')) {
        $log = new Log;
        $log->DBupdate(sprintf(
            '%s Avatar: (leer) &rArr; <b>%s</b>',
            $logLabel !== '' ? $logLabel : $kind.'-ID: '.$id,
            htmlspecialchars(basename($rel), ENT_QUOTES, 'UTF-8')
        ));
    }
    return array('ok' => true, 'path' => $rel);
}

function archivEntityAvatarDelete($kind, $id, $writeLog = true, $logLabel = '') {
    $id = (int)$id;
    $dir = archivEntityAvatarFsDir($kind, $id);
    if($dir === '' || !is_dir($dir)) {
        return false;
    }
    $removed = '';
    foreach(array('png', 'jpg', 'jpeg', 'gif', 'webp') as $ext) {
        $file = $dir.'avatar.'.$ext;
        if(is_file($file)) {
            $removed = basename($file);
            @unlink($file);
        }
    }
    if($removed === '') {
        return false;
    }
    if($writeLog && class_exists('Log')) {
        $log = new Log;
        $log->DBupdate(sprintf(
            '%s Avatar: %s &rArr; <b>(gelöscht)</b>',
            $logLabel !== '' ? $logLabel : $kind.'-ID: '.$id,
            htmlspecialchars($removed, ENT_QUOTES, 'UTF-8')
        ));
    }
    return true;
}

/**
 * HTML for list/modal avatar (image or initials).
 */
function archivEntityAvatarHtml($kind, $id, $initials, $cssClass = 'entity-avatar') {
    $url = archivEntityAvatarFind($kind, $id);
    $cls = trim((string)$cssClass);
    if($cls === '') {
        $cls = 'entity-avatar';
    }
    $clsEsc = archivEscHtml($cls);
    $ini = archivEscHtml(mb_substr(trim((string)$initials), 0, 2, 'UTF-8'));
    if($url !== '') {
        $mtime = @filemtime(archivEntityAvatarFsDir($kind, $id).basename($url));
        return '<span class="'.$clsEsc.'" aria-hidden="true"><img src="'.archivEscHtml($url).'?'.rawurlencode((string)$mtime).'" alt=""></span>';
    }
    return '<span class="'.$clsEsc.' entity-avatar--placeholder" aria-hidden="true"><span class="entity-avatar-initials">'.($ini !== '' ? $ini : '—').'</span></span>';
}

/**
 * Profile-field markup for avatar upload/delete inside an edit form (multipart).
 */
function archivEntityAvatarFormFields($kind, $id, $inputBg = '', $btn = '', $btnDel = 'w3-red', $initials = '') {
    $id = (int)$id;
    $html = '<div class="profile-field entity-avatar-field">';
    $html .= '<label class="profile-label">Foto</label>';
    $html .= '<div class="entity-avatar-edit">';
    $html .= archivEntityAvatarHtml($kind, $id, $initials, 'entity-avatar entity-avatar--lg');
    $html .= '<div class="entity-avatar-controls">';
    $html .= '<input type="file" name="avatar" accept=".png,.jpeg,.gif,.jpg,.webp,image/*" class="w3-input w3-border profile-control '.archivEscHtml($inputBg).'">';
    $html .= '<div class="profile-actions">';
    $html .= '<button type="submit" name="uploadAvatar" value="1" class="w3-button '.archivEscHtml($btn).'">Hochladen</button> ';
    if(archivEntityAvatarFind($kind, $id) !== '') {
        $html .= '<button type="submit" name="deleteAvatar" value="1" class="w3-button w3-border '.archivEscHtml($btnDel).'">Löschen</button>';
    }
    $html .= '</div></div></div></div>';
    return $html;
}
?>
