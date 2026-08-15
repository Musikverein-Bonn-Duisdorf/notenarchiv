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
 * Rough skin-tone focus for portrait framing (upper half of the image).
 *
 * @param resource|\GdImage $img
 * @return array{x:float,y:float} Focus point in image coordinates
 */
function archivPortraitFocusPoint($img) {
    $w = imagesx($img);
    $h = imagesy($img);
    if($w < 2 || $h < 2) {
        return array('x' => $w / 2, 'y' => $h * 0.35);
    }
    $step = max(1, (int)floor(min($w, $h) / 64));
    $sumX = 0.0;
    $sumY = 0.0;
    $n = 0;
    $yMax = (int)max(1, floor($h * 0.62));
    for($y = 0; $y < $yMax; $y += $step) {
        for($x = 0; $x < $w; $x += $step) {
            $rgb = imagecolorat($img, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            // Loose skin heuristic (works for many portrait photos)
            if($r > 95 && $g > 40 && $b > 20 && $r > $g && $r > $b && ($r - $g) > 12
                && abs($r - $g) > 8 && $r < 250 && $g < 230) {
                $sumX += $x;
                $sumY += $y;
                $n++;
            }
        }
    }
    if($n < 12) {
        return array('x' => $w / 2.0, 'y' => $h * 0.32);
    }
    return array('x' => $sumX / $n, 'y' => $sumY / $n);
}

/**
 * Crop/scale a portrait GD image to ~3:4 with the subject near the focus point.
 *
 * @param resource|\GdImage $img
 * @return resource|\GdImage
 */
function archivPortraitNormalizeGd($img) {
    $w = imagesx($img);
    $h = imagesy($img);
    if($w < 40 || $h < 40) {
        return $img;
    }
    $targetRatio = 3 / 4; // width / height
    $focus = archivPortraitFocusPoint($img);
    $fx = $focus['x'];
    $fy = $focus['y'];

    $cropW = $w;
    $cropH = $h;
    $ratio = $w / max(1, $h);
    if($ratio > $targetRatio) {
        $cropW = (int)max(1, round($h * $targetRatio));
        $cropH = $h;
    } elseif($ratio < $targetRatio) {
        $cropW = $w;
        $cropH = (int)max(1, round($w / $targetRatio));
    }

    $srcX = (int)round($fx - $cropW / 2);
    $srcY = (int)round($fy - $cropH * 0.38); // keep face in upper portion of frame
    $srcX = max(0, min($srcX, $w - $cropW));
    $srcY = max(0, min($srcY, $h - $cropH));

    $max = 1200;
    $scale = min(1.0, $max / max($cropW, $cropH));
    $nw = (int)max(1, round($cropW * $scale));
    $nh = (int)max(1, round($cropH * $scale));

    if($srcX === 0 && $srcY === 0 && $cropW === $w && $cropH === $h && $nw === $w && $nh === $h) {
        return $img;
    }

    $dst = imagecreatetruecolor($nw, $nh);
    if($dst === false) {
        return $img;
    }
    imagecopyresampled($dst, $img, 0, 0, $srcX, $srcY, $nw, $nh, $cropW, $cropH);
    imagedestroy($img);
    return $dst;
}

/**
 * Normalize a portrait image file in place to JPEG (composer faces stay in frame).
 *
 * @return string|null Path to JPEG temp/original file, or null on failure
 */
function archivPortraitNormalizeFile($path) {
    $path = (string)$path;
    if($path === '' || !is_file($path)) {
        return null;
    }
    $bin = @file_get_contents($path);
    if($bin === false || strlen($bin) < 200) {
        return null;
    }
    $img = @imagecreatefromstring($bin);
    if(!$img) {
        return null;
    }
    $img = archivPortraitNormalizeGd($img);
    $out = preg_match('/\.jpe?g$/i', $path) ? $path : ($path.'.jpg');
    if(!imagejpeg($img, $out, 90)) {
        imagedestroy($img);
        return null;
    }
    imagedestroy($img);
    if($out !== $path && is_file($path)) {
        @unlink($path);
    }
    return is_file($out) ? $out : null;
}

/**
 * Fit a logo into a 3:4 transparent canvas (same aspect as covers/portraits),
 * centered, so list/detail thumbs share one visual schema.
 *
 * @param resource|\GdImage $img
 * @param int $canvasH Canvas height (width derived as 3:4)
 * @return resource|\GdImage
 */
function archivLogoNormalizeGd($img, $canvasH = 512, $padRatio = 0.02) {
    $w = imagesx($img);
    $h = imagesy($img);
    if($w < 1 || $h < 1) {
        return $img;
    }
    $canvasH = max(64, (int)$canvasH);
    $canvasW = (int)max(1, round($canvasH * 3 / 4));
    $padRatio = max(0.0, min(0.25, (float)$padRatio));
    $innerW = (int)max(1, round($canvasW * (1.0 - 2.0 * $padRatio)));
    $innerH = (int)max(1, round($canvasH * (1.0 - 2.0 * $padRatio)));
    $scale = min($innerW / $w, $innerH / $h);
    $nw = (int)max(1, round($w * $scale));
    $nh = (int)max(1, round($h * $scale));
    $dst = imagecreatetruecolor($canvasW, $canvasH);
    if($dst === false) {
        return $img;
    }
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $canvasW - 1, $canvasH - 1, $transparent);
    imagealphablending($dst, true);
    $dx = (int)floor(($canvasW - $nw) / 2);
    $dy = (int)floor(($canvasH - $nh) / 2);
    imagecopyresampled($dst, $img, $dx, $dy, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($img);
    return $dst;
}

/**
 * Normalize a logo file to a centered 3:4 PNG (shared thumb schema).
 *
 * @return string|null
 */
function archivLogoNormalizeFile($path, $canvasH = 512) {
    $path = (string)$path;
    if($path === '' || !is_file($path)) {
        return null;
    }
    $bin = @file_get_contents($path);
    if($bin === false || strlen($bin) < 40) {
        return null;
    }
    $img = @imagecreatefromstring($bin);
    if(!$img) {
        return null;
    }
    $img = archivLogoNormalizeGd($img, $canvasH);
    $out = preg_match('/\.png$/i', $path) ? $path : ($path.'.png');
    imagesavealpha($img, true);
    if(!imagepng($img, $out)) {
        imagedestroy($img);
        return null;
    }
    imagedestroy($img);
    if($out !== $path && is_file($path)) {
        @unlink($path);
    }
    return is_file($out) ? $out : null;
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
    if(empty($file['tmp_name'])) {
        return array('ok' => false, 'error' => 'no file');
    }
    $tmpOk = function_exists('archivApiAcceptUploadTmp')
        ? archivApiAcceptUploadTmp($file['tmp_name'])
        : is_uploaded_file($file['tmp_name']);
    if(!$tmpOk) {
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

    $srcTmp = (string)$file['tmp_name'];
    $saveExt = $ext;
    if($kind === 'Composers') {
        $normalized = archivPortraitNormalizeFile($srcTmp);
        if($normalized) {
            $srcTmp = $normalized;
            $saveExt = 'jpg';
        }
    } elseif($kind === 'Publishers') {
        $normalized = archivLogoNormalizeFile($srcTmp, 512);
        if($normalized) {
            $srcTmp = $normalized;
            $saveExt = 'png';
        }
    }

    $rel = archivEntityAvatarRelDir($kind, $id).'avatar.'.$saveExt;
    $fs = archivEntityAvatarFsDir($kind, $id).'avatar.'.$saveExt;
    $moved = false;
    if($srcTmp === (string)$file['tmp_name']) {
        $moved = move_uploaded_file($file['tmp_name'], $fs);
        if(!$moved && function_exists('archivApiMoveUpload')) {
            $moved = archivApiMoveUpload($file['tmp_name'], $fs);
        }
    } else {
        $moved = @rename($srcTmp, $fs) || @copy($srcTmp, $fs);
        if(is_file($srcTmp) && realpath($srcTmp) !== realpath($fs)) {
            @unlink($srcTmp);
        }
    }
    if(!$moved) {
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
    // Same thumb schema for composers and publishers (3:4 frame like covers).
    $clsEsc = archivEscHtml($cls);
    $iniRaw = mb_substr(trim((string)$initials), 0, 2, 'UTF-8');
    $ini = archivEscHtml($iniRaw);
    if($url !== '') {
        $mtime = @filemtime(archivEntityAvatarFsDir($kind, $id).basename($url));
        return '<span class="'.$clsEsc.'" aria-hidden="true"><img src="'.archivEscHtml($url).'?'.rawurlencode((string)$mtime).'" alt=""></span>';
    }
    $hue = (int)(sprintf('%u', crc32($kind.'|'.(int)$id.'|'.$iniRaw)) % 360);
    return '<span class="'.$clsEsc.' entity-avatar--placeholder" style="background-color:hsl('.$hue.',42%,38%)" aria-hidden="true">'
        .'<span class="entity-avatar-initials">'.($ini !== '' ? $ini : '—').'</span></span>';
}

/**
 * Profile-field markup for avatar upload/delete inside an edit form (multipart).
 */
function archivEntityAvatarFormFields($kind, $id, $inputBg = '', $btn = '', $btnDel = 'w3-red', $initials = '') {
    $id = (int)$id;
    $html = '<div class="profile-field entity-avatar-field">';
    $html .= '<label class="profile-label">Foto</label>';
    $html .= '<div class="entity-avatar-edit">';
    $html .= archivEntityAvatarHtml($kind, $id, $initials, 'archiv-thumb archiv-thumb--lg entity-avatar entity-avatar--lg');
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
