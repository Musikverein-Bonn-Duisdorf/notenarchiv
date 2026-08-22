<?php
/**
 * ARCHIV-31: User-scoped API tokens (SHA-256 stored). Tokens live in $dbprefix AppTokens;
 * users come from Melde identityPrefix User table.
 */

function archivHashAppToken($rawToken) {
    return hash('sha256', (string)$rawToken);
}

function archivCreateAppToken($userId, $deviceLabel = '') {
    $userId = (int)$userId;
    if($userId <= 0) {
        return null;
    }
    $raw = bin2hex(random_bytes(32));
    $hash = archivHashAppToken($raw);
    $device = mysqli_real_escape_string($GLOBALS['conn'], substr((string)$deviceLabel, 0, 200));
    $sql = sprintf(
        "INSERT INTO `%sAppTokens` (`User`, `TokenHash`, `DeviceLabel`, `Revoked`) VALUES (%d, '%s', '%s', 0);",
        $GLOBALS['dbprefix'],
        $userId,
        mysqli_real_escape_string($GLOBALS['conn'], $hash),
        $device
    );
    mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    if(!mysqli_insert_id($GLOBALS['conn'])) {
        return null;
    }
    $label = archivAppTokenUserLabel($userId);
    $prevUser = isset($_SESSION['userid']) ? (int)$_SESSION['userid'] : 0;
    $_SESSION['userid'] = $userId;
    $log = new Log;
    $msg = sprintf('AppToken issued for User-ID: %d <b>%s</b>', $userId, htmlspecialchars($label, ENT_QUOTES, 'UTF-8'));
    if(trim((string)$deviceLabel) !== '') {
        $msg .= ', device: '.htmlspecialchars(substr((string)$deviceLabel, 0, 80), ENT_QUOTES, 'UTF-8');
    }
    $msg .= '.';
    $log->info($msg);
    $_SESSION['userid'] = $prevUser;
    return $raw;
}

/**
 * Display name for log lines (Vorname Nachname, else login / #id).
 */
function archivAppTokenUserLabel($userId) {
    $userId = (int)$userId;
    if($userId < 1) {
        return '#0';
    }
    $sql = sprintf(
        "SELECT `Vorname`, `Nachname`, `login` FROM `%sUser` WHERE `Index` = %d LIMIT 1;",
        identityPrefix(),
        $userId
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
    if(!$row) {
        return '#'.$userId;
    }
    $name = trim((string)$row['Vorname'].' '.(string)$row['Nachname']);
    if($name !== '') {
        return $name;
    }
    $login = trim((string)$row['login']);
    return $login !== '' ? $login : '#'.$userId;
}

/**
 * @return array|null User row + TokenIndex
 */
function archivFindUserByAppToken($rawToken) {
    $rawToken = trim((string)$rawToken);
    if($rawToken === '' || strlen($rawToken) < 32) {
        return null;
    }
    $hash = archivHashAppToken($rawToken);
    $sql = sprintf(
        "SELECT u.*, t.`Index` AS `TokenIndex`
         FROM `%sAppTokens` t
         INNER JOIN `%sUser` u ON u.`Index` = t.`User`
         WHERE t.`TokenHash` = '%s'
           AND t.`Revoked` = 0
           AND (t.`Expires` IS NULL OR t.`Expires` > CURRENT_TIMESTAMP())
           AND (u.`Deleted` IS NULL OR u.`Deleted` != 1)
         LIMIT 1;",
        $GLOBALS['dbprefix'],
        identityPrefix(),
        mysqli_real_escape_string($GLOBALS['conn'], $hash)
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
    if(!$row) {
        return null;
    }
    $upd = sprintf(
        "UPDATE `%sAppTokens` SET `LastUsed` = CURRENT_TIMESTAMP() WHERE `Index` = %d;",
        $GLOBALS['dbprefix'],
        (int)$row['TokenIndex']
    );
    mysqli_query($GLOBALS['conn'], $upd);
    return $row;
}

/**
 * Bind PHP session to the AppToken owner. Session start is logged via
 * archivLogApiSessionEstablished() from api/me.php (not on every API call).
 */
function archivEstablishApiSessionFromUserRow(array $row, $via = 'AppToken') {
    if(isset($row['Deleted']) && (int)$row['Deleted'] === 1) {
        return false;
    }
    $uid = (int)$row['Index'];
    if($uid < 1) {
        return false;
    }
    if(!userHasMeldeArchivAccess($uid)) {
        return false;
    }
    // Same user already bound via this API path — keep session, no re-log.
    if(!empty($_SESSION['userid'])
        && (int)$_SESSION['userid'] === $uid
        && !empty($_SESSION['apiAuthVia'])
        && (string)$_SESSION['apiAuthVia'] === (string)$via) {
        $_SESSION['admin'] = computeAdminForUser($uid);
        return true;
    }
    $_SESSION['userid'] = $uid;
    $_SESSION['Vorname'] = $row['Vorname'];
    $_SESSION['Nachname'] = $row['Nachname'];
    $_SESSION['username'] = trim((string)$row['Vorname'].' '.(string)$row['Nachname']);
    $_SESSION['singleUsePW'] = !empty($row['singleUsePW']);
    $_SESSION['admin'] = computeAdminForUser($uid);
    $_SESSION['apiAuthVia'] = (string)$via;
    return true;
}

function archivValidateAppToken($rawToken) {
    $row = archivFindUserByAppToken($rawToken);
    if(!$row) {
        $_SESSION['userid'] = 0;
        unset($_SESSION['apiAuthVia']);
        $log = new Log;
        $log->error('API auth failed: invalid or revoked token.');
        return false;
    }
    return archivEstablishApiSessionFromUserRow($row, 'AppToken');
}

/**
 * Info log when a client establishes an API session (GET api/me.php).
 * Idempotent for the current PHP session (one line per me.php call).
 */
function archivLogApiSessionEstablished($rawToken) {
    if(!empty($_SESSION['apiSessionLogged'])) {
        return;
    }
    $rawToken = trim((string)$rawToken);
    if($rawToken === '' || empty($_SESSION['userid'])) {
        return;
    }
    $hash = archivHashAppToken($rawToken);
    $sql = sprintf(
        "SELECT t.`DeviceLabel`, t.`User` FROM `%sAppTokens` t
         WHERE t.`TokenHash` = '%s' AND t.`Revoked` = 0 LIMIT 1;",
        $GLOBALS['dbprefix'],
        mysqli_real_escape_string($GLOBALS['conn'], $hash)
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
    $userId = (int)$_SESSION['userid'];
    $label = archivAppTokenUserLabel($userId);
    $msg = sprintf(
        'API session via AppToken for User-ID: %d <b>%s</b>',
        $userId,
        htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
    );
    if(is_array($row)) {
        $device = trim((string)($row['DeviceLabel'] ?? ''));
        if($device !== '') {
            $msg .= ', device: '.htmlspecialchars($device, ENT_QUOTES, 'UTF-8');
        }
    }
    $path = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '/api/me.php';
    if(strlen($path) > 120) {
        $path = substr($path, 0, 117).'…';
    }
    $msg .= ', path: '.htmlspecialchars($path, ENT_QUOTES, 'UTF-8').'.';
    if(function_exists('archivApiRunNote') && archivApiRunNote() !== '') {
        $note = htmlspecialchars(archivApiRunNote(), ENT_QUOTES, 'UTF-8');
        $msg = '['.$note.'] '.$msg;
    }
    $log = new Log;
    $log->info($msg);
    $_SESSION['apiSessionLogged'] = true;
}

function archivRevokeAppToken($rawToken) {
    $rawToken = trim((string)$rawToken);
    if($rawToken === '') {
        return false;
    }
    $hash = archivHashAppToken($rawToken);
    $sql = sprintf(
        "UPDATE `%sAppTokens` SET `Revoked` = 1 WHERE `TokenHash` = '%s' AND `Revoked` = 0;",
        $GLOBALS['dbprefix'],
        mysqli_real_escape_string($GLOBALS['conn'], $hash)
    );
    mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    return mysqli_affected_rows($GLOBALS['conn']) > 0;
}

function archivExtractBearerToken() {
    $header = '';
    if(isset($_SERVER['HTTP_AUTHORIZATION']) && (string)$_SERVER['HTTP_AUTHORIZATION'] !== '') {
        $header = (string)$_SERVER['HTTP_AUTHORIZATION'];
    } elseif(isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) && (string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION'] !== '') {
        $header = (string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif(function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if(is_array($headers)) {
            foreach($headers as $k => $v) {
                if(strtolower((string)$k) === 'authorization') {
                    $header = (string)$v;
                    break;
                }
            }
        }
    }
    if(preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $header, $m)) {
        return $m[1];
    }
    // Fallback when hosts strip Authorization (ARCHIV-35); raw token, not Bearer-prefixed.
    if(isset($_SERVER['HTTP_X_ARCHIV_TOKEN'])) {
        $alt = trim((string)$_SERVER['HTTP_X_ARCHIV_TOKEN']);
        if($alt !== '' && strlen($alt) >= 32) {
            return $alt;
        }
    }
    return '';
}
?>
