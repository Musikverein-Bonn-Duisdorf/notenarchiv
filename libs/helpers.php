<?php
function identityPrefix() {
    return isset($GLOBALS['identityPrefix']) ? $GLOBALS['identityPrefix'] : $GLOBALS['dbprefix'];
}

/**
 * Melde-colliding logical keys → Archiv* storage names in archiv_config.
 * UI keeps logical names (colorNav, WebSiteURL, …); persist only Archiv*.
 * @return array<string,string> logical => storage
 */
function archivConfigAliases() {
    return array(
        'WebSiteName' => 'ArchivWebSiteName',
        'WebSiteNameShort' => 'ArchivWebSiteNameShort',
        'WebSiteURL' => 'ArchivWebSiteURL',
        'MasterPage' => 'ArchivMasterPage',
        'favicon' => 'ArchivFavicon',
        'SchemaVersion' => 'ArchivSchemaVersion',
        'HoverEffect' => 'ArchivHoverEffect',
        'showBranchBannerAlways' => 'ArchivShowBranchBannerAlways',
        'colorSchemeActive' => 'ArchivColorSchemeActive',
        'colorSchemes' => 'ArchivColorSchemes',
        'colorLogFatal' => 'ArchivColorLogFatal',
        'colorLogError' => 'ArchivColorLogError',
        'colorLogWarning' => 'ArchivColorLogWarning',
        'colorBtnEdit' => 'ArchivColorBtnEdit',
        'colorSuccess' => 'ArchivColorSuccess',
        'colorNav' => 'ArchivColorNav',
        'colorNavAdmin' => 'ArchivColorNavAdmin',
        'colorBackground' => 'ArchivColorBackground',
        'colorTitle' => 'ArchivColorTitle',
        'colorTitleBar' => 'ArchivColorTitleBar',
        'colorWarning' => 'ArchivColorWarning',
        'colorBtnSubmit' => 'ArchivColorBtnSubmit',
        'colorBtnDelete' => 'ArchivColorBtnDelete',
        'colorInputBackground' => 'ArchivColorInputBackground',
    );
}

/** @deprecated Use archivConfigAliases() */
function archivSiteConfigAliases() {
    return archivConfigAliases();
}

/** Map logical Melde-style name to Archiv* storage param (no-op if already storage). */
function archivResolveConfigParam($parameter) {
    $parameter = (string)$parameter;
    $aliases = archivConfigAliases();
    if(isset($aliases[$parameter])) {
        return $aliases[$parameter];
    }
    return $parameter;
}

/** Map Archiv* storage name back to logical UI/scheme key (no-op if unknown). */
function archivLogicalConfigParam($parameter) {
    $parameter = (string)$parameter;
    foreach(archivConfigAliases() as $logical => $storage) {
        if($storage === $parameter) {
            return $logical;
        }
    }
    return $parameter;
}

/** True for scheme JSON / active-id params (hidden from config form). */
function archivIsHiddenConfigParam($parameter) {
    $parameter = (string)$parameter;
    $hidden = array(
        archivResolveConfigParam('colorSchemeActive'),
        archivResolveConfigParam('colorSchemes'),
        'colorSchemeActive',
        'colorSchemes',
    );
    return in_array($parameter, $hidden, true);
}

function loadconfig() {
    $optionsDB = array();
    $sql = sprintf('SELECT * FROM `%sconfig`;',
		   $GLOBALS['dbprefix']
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    if($dbr) {
        while($row = mysqli_fetch_array($dbr)) {
            $optionsDB[$row['Parameter']] = $row['Value'];
        }
    }
    if(function_exists('getConfigDefaults')) {
        foreach(getConfigDefaults() as $item) {
            if(!array_key_exists($item['Parameter'], $optionsDB)) {
                $optionsDB[$item['Parameter']] = $item['Value'];
            }
        }
    }
    foreach(archivConfigAliases() as $legacy => $archivKey) {
        if(array_key_exists($archivKey, $optionsDB)) {
            $optionsDB[$legacy] = $optionsDB[$archivKey];
        }
    }
    if(function_exists('getColorConfigParameters') && function_exists('colorToCssClass')) {
        $colorParams = getColorConfigParameters();
        foreach($optionsDB as $param => $value) {
            if(isset($colorParams[$param]) || (function_exists('isHexColor') && isHexColor($value))) {
                $optionsDB[$param] = colorToCssClass($value);
            }
        }
    }
    return $optionsDB;
}
function requireAdmin() {
    refreshSessionAdmin();
    if(empty($_SESSION['admin'])) {
        denyAccess('Admin-Berechtigung erforderlich.');
    }
}

function requireLoggedInOrRedirect() {
    if(!loggedIn()) {
        header('Location: login.php');
        exit;
    }
    if(!empty($_SESSION['singleUsePW'])) {
        header('Location: changePW.php');
        exit;
    }
}

function setFlash($type, $message) {
    $_SESSION['flash'] = array(
        'type' => (string)$type,
        'message' => (string)$message,
    );
}

function getFlash() {
    if(!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function renderFlashHtml($flash = null) {
    if($flash === null) {
        $flash = getFlash();
    }
    if(!$flash || $flash['message'] === '') {
        return '';
    }
    $isError = ($flash['type'] === 'error');
    $mod = $isError ? 'app-toast--error' : 'app-toast--success';
    $role = $isError ? 'alert' : 'status';
    $attrs = $isError ? '' : ' data-autodismiss="3500"';
    $close = $isError
        ? '<button type="button" class="app-toast-close" aria-label="Hinweis schließen">&times;</button>'
        : '';
    $html = '<div class="app-toast '.$mod.'" role="'.$role.'"'.$attrs.'>'
        .'<div class="app-toast-body">'
        .htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8')
        .'</div>'
        .$close
        .'</div>';
    if(!isset($GLOBALS['mlDeferredToasts'])) {
        $GLOBALS['mlDeferredToasts'] = '';
    }
    $GLOBALS['mlDeferredToasts'] .= $html;
    return '';
}

function redirectAfterPost($url) {
    while(ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: '.$url);
    exit;
}

/**
 * Legacy Archiv text may store HTML entities (&uuml;, &bdquo;, …).
 * Decode to UTF-8 plain text for search/sort/display.
 */
function archivPlainText($value) {
    return html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Safe HTML text: decode legacy entities, then escape.
 */
function archivEscHtml($value) {
    return htmlspecialchars(archivPlainText($value), ENT_QUOTES, 'UTF-8');
}

function archivRequest($key, $default = null) {
    if(isset($_POST[$key])) {
        return $_POST[$key];
    }
    if(isset($_GET[$key])) {
        return $_GET[$key];
    }
    return $default;
}

function csrf_token() {
    if(empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify($token) {
    if(!isset($_SESSION['csrf_token']) || !is_string($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="'
        .htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8').'">';
}

function logMessageHasChanges($message) {
    $message = (string)$message;
    if($message === '') {
        return false;
    }
    if(strpos($message, '&rArr;') !== false) {
        return true;
    }
    if(strpos($message, '(vorher:') !== false) {
        return true;
    }
    if(preg_match('/\b(?:Passhash|activeLink)\s+geändert\b/u', $message)) {
        return true;
    }
    if(strpos($message, 'zurückgesetzt') !== false) {
        return true;
    }
    if(strpos($message, 'umbenannt:') !== false) {
        return true;
    }
    return false;
}

/** Build one log fragment: "Label: <b>value</b>". */
function logPart($label, $valueHtml) {
    return $label.': <b>'.$valueHtml.'</b>';
}

function logValueFilled($value, $allowZero = false) {
    if($value === null) {
        return false;
    }
    if(is_bool($value)) {
        return $value;
    }
    if(is_int($value) || is_float($value)) {
        if(!$allowZero && (float)$value == 0) {
            return false;
        }
        return true;
    }
    $s = trim((string)$value);
    return !($s === '' || $s === '-');
}

/** Append logPart to $parts if value is filled. */
function logAppendFilled(array &$parts, $label, $value, $valueHtml = null, $allowZero = false) {
    if(!logValueFilled($value, $allowZero)) {
        return;
    }
    $parts[] = logPart($label, $valueHtml !== null ? $valueHtml : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'));
}

/**
 * Append a Melde-style field diff ("old &rArr; <b>new</b>") when values differ.
 */
function logAppendChange(array &$parts, $label, $oldValue, $newValue, $allowZero = true) {
    $old = $oldValue === null ? '' : (string)$oldValue;
    $new = $newValue === null ? '' : (string)$newValue;
    if($old === $new) {
        return;
    }
    if(!$allowZero && $old === '' && $new === '0') {
        return;
    }
    $oldHtml = $old === '' ? '(leer)' : htmlspecialchars($old, ENT_QUOTES, 'UTF-8');
    $newHtml = $new === '' ? '(leer)' : htmlspecialchars($new, ENT_QUOTES, 'UTF-8');
    $parts[] = $label.': '.$oldHtml.' &rArr; <b>'.$newHtml.'</b>';
}

function formatConfigLogValue($value, $type = '') {
    if($value === null || $value === '') {
        return '(leer)';
    }
    if($type === 'bool') {
        return bool2string($value);
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function logConfigChange($parameter, $oldValue, $newValue, $type = '') {
    if((string)$oldValue === (string)$newValue) {
        return;
    }
    $label = (string)$parameter;
    if($type === '' && function_exists('getConfigDefaults')) {
        foreach(getConfigDefaults() as $item) {
            if($item['Parameter'] === $parameter) {
                $type = isset($item['Type']) ? (string)$item['Type'] : '';
                if(!empty($item['Description'])) {
                    $label = $parameter.' ('.$item['Description'].')';
                }
                break;
            }
        }
    }
    if(!class_exists('Log')) {
        return;
    }
    $logentry = new Log;
    $logentry->DBupdate(sprintf(
        'Config <b>%s</b>: %s &rArr; <b>%s</b>',
        htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
        formatConfigLogValue($oldValue, $type),
        formatConfigLogValue($newValue, $type)
    ));
}

/**
 * Non-fatal permission check (Melde requirePermission semantics).
 * User.Admin always passes. Does not deny/exit.
 * @param string $perm e.g. perm_editConfig
 * @return bool
 */
function hasPermission($perm) {
    $uid = isset($_SESSION['userid']) ? (int)$_SESSION['userid'] : 0;
    if($uid < 1) {
        return false;
    }
    $sql = sprintf(
        "SELECT `Admin` FROM `%sUser` WHERE `Index` = %d LIMIT 1;",
        identityPrefix(),
        $uid
    );
    $dbr = @mysqli_query($GLOBALS['conn'], $sql);
    $row = ($dbr) ? mysqli_fetch_assoc($dbr) : null;
    if($row && !empty($row['Admin'])) {
        return true;
    }
    return IdentityPermissions::loadForUser($uid)->getPermission($perm);
}

/**
 * Require a Melde permission (effective: personal row + group PermissionSpec).
 * User.Admin always passes.
 * @param string $perm e.g. perm_editConfig
 */
function requirePermission($perm) {
    refreshSessionAdmin();
    if(hasPermission($perm)) {
        return;
    }
    $uid = isset($_SESSION['userid']) ? (int)$_SESSION['userid'] : 0;
    if($uid < 1) {
        denyAccess('Bitte anmelden.');
    }
    denyAccess('Keine Berechtigung für diesen Bereich.');
}

/**
 * @param string $message
 */
function denyAccess($message = 'Keine Berechtigung für diesen Bereich.') {
    if(!headers_sent()) {
        http_response_code(403);
    }
    $color = isset($GLOBALS['optionsDB']['colorLogWarning'])
        ? $GLOBALS['optionsDB']['colorLogWarning']
        : 'w3-orange';
    echo '<div class="w3-panel '.$color.' w3-padding w3-margin">'
        .'<h3>Zugriff verweigert</h3>'
        .'<p>'.htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8').'</p>'
        .'</div>';
    if(file_exists(__DIR__.'/../common/footer.php')) {
        include __DIR__.'/../common/footer.php';
    }
    exit;
}

/**
 * Admin = Melde User.Admin OR any Melde Permissions flag (ARCHIV-6).
 * @param int $userId
 * @param bool $legacyAdmin User.Admin column
 * @return bool
 */
function computeAdminForUser($userId, $legacyAdmin = false) {
    if($legacyAdmin) {
        return true;
    }
    $userId = (int)$userId;
    if($userId < 1) {
        return false;
    }
    return IdentityPermissions::loadForUser($userId)->isAdmin();
}

/** Refresh $_SESSION['admin'] from Melde User.Admin OR Permissions. */
function refreshSessionAdmin() {
    if(!isset($_SESSION['userid'])) {
        return;
    }
    $uid = (int)$_SESSION['userid'];
    if($uid < 1) {
        return;
    }
    $sql = sprintf(
        "SELECT `Admin` FROM `%sUser` WHERE `Index` = %d LIMIT 1;",
        identityPrefix(),
        $uid
    );
    $dbr = @mysqli_query($GLOBALS['conn'], $sql);
    $row = ($dbr) ? mysqli_fetch_assoc($dbr) : null;
    $legacy = $row ? (bool)$row['Admin'] : false;
    $_SESSION['admin'] = computeAdminForUser($uid, $legacy);
}
function bool2string($val) {
    if($val) return "ja";
    return "nein";
}

function instrumentsOptionNull($val) {
    $str='';
    $str=$str."<option value=\"0\">keins</option>\n";
    $sql = sprintf('SELECT * FROM `%sInstrument` INNER JOIN (SELECT `Index` AS `rIndex`, `CustomOrder` AS `rSort` FROM `%sRegister`) `%sRegister` ON `rIndex` = `Register` ORDER BY `rSort`, `CustomOrder`;',
    identityPrefix(),
    identityPrefix(),
    identityPrefix()
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    while($row = mysqli_fetch_array($dbr)) {
        if($val == $row['Index']) {
            $str=$str."<option value=\"".$row['Index']."\" selected>".$row['Name']."</option>\n";
        }
        else {
            $str=$str."<option value=\"".$row['Index']."\">".$row['Name']."</option>\n";
        }
    }
    return $str;
}

function instrumentsOption() {
    $str='';
    $sql = sprintf('SELECT * FROM `%sInstrument` INNER JOIN (SELECT `Index` AS `rIndex`, `CustomOrder` AS `rSort` FROM `%sRegister`) `%sRegister` ON `rIndex` = `Register` ORDER BY `rSort`, `CustomOrder`;',
    identityPrefix(),
    identityPrefix(),
    identityPrefix()
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    while($row = mysqli_fetch_array($dbr)) {
        $str=$str."<option value=\"".$row['Index']."\">".$row['Name']."</option>\n";
    }
    return $str;
}

function collectionsOption() {
    $str='';
    $sql = sprintf('SELECT * FROM `%sCollection` ORDER BY `Name`;',
    $GLOBALS['dbprefix']
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    while($row = mysqli_fetch_array($dbr)) {
        $str=$str."<option value=\"".$row['Index']."\">".$row['Name']."</option>\n";
    }
    return $str;
}

function RegistersOption($val) {
    $sql = sprintf('SELECT * FROM `%sRegister` ORDER BY `CustomOrder`;',
		   identityPrefix()
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    while($row = mysqli_fetch_array($dbr)) {
        if($val == $row['Index']) {
            echo "<option value=\"".$row['Index']."\" selected>".$row['Name']."</option>\n";
        }
        else {
            echo "<option value=\"".$row['Index']."\">".$row['Name']."</option>\n";
        }
    }
}

function mkNULLonNull($val) {
    if($val == 0 || $val == NULL) return "null";
    return $val;
}

function nextArchiverNumber() {
    $sql = sprintf('SELECT `RegistrationNumber` FROM `%sComposition` ORDER BY `RegistrationNumber` ASC;',
		   $GLOBALS['dbprefix']
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    $Numbers = array();
    while($row = mysqli_fetch_array($dbr)) {
        array_push($Numbers, $row['RegistrationNumber']);
    }
    $i = 1;
    while(true) {
        if(array_search($i, $Numbers)) $i++;
        else break;
    }
    return $i;
}

function ComposersOption($val) {
    if($val == 0) {
        echo "<option value=\"null\" selected></option>\n";
    }
    else {
        echo "<option value=\"null\"></option>\n";
    }
    $sql = sprintf('SELECT * FROM `%sComposer` ORDER BY `LastName` ASC;',
		   $GLOBALS['dbprefix']
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    while($row = mysqli_fetch_array($dbr)) {
        if($val == $row['Index']) {
            echo "<option value=\"".$row['Index']."\" selected>".$row['LastName'].", ".$row['FirstName']."</option>\n";
        }
        else {
            echo "<option value=\"".$row['Index']."\">".$row['LastName'].", ".$row['FirstName']."</option>\n";
        }
    }
}

function PublishersOption($val) {
    if($val == 0) {
        echo "<option value=\"null\" selected></option>\n";
    }
    else {
        echo "<option value=\"null\"></option>\n";
    }
    $sql = sprintf('SELECT * FROM `%sPublisher` ORDER BY `Name` ASC;',
		   $GLOBALS['dbprefix']
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    while($row = mysqli_fetch_array($dbr)) {
        if($val == $row['Index']) {
            echo "<option value=\"".$row['Index']."\" selected>".$row['Name']."</option>\n";
        }
        else {
            echo "<option value=\"".$row['Index']."\">".$row['Name']."</option>\n";
        }
    }
}

function getPage($string, $groupId = '') {
    if($string == $_SESSION['page']) {
        echo $GLOBALS['optionsDB']['colorTitleBar'];
        return;
    }
    if($groupId !== '' && function_exists('navGroupClass')) {
        echo navGroupClass($groupId);
        return;
    }
    echo $GLOBALS['optionsDB']['colorNav'];
}

function getAdminPage($string) {
    if($string == $_SESSION['page'] && $_SESSION['adminpage']) {
        echo $GLOBALS['optionsDB']['colorTitleBar'];
    }
    else {
        echo $GLOBALS['optionsDB']['colorNavAdmin'];
    }
}

function string2Date($string) {
    $y = substr($string, 0, 3);
    $m = substr($string, 5, 6);
    $d = substr($string, 8, 9);
}

function string2gDate($string) {
    $y = substr($string, 0, 4);
    $m = substr($string, 5, 2);
    $d = substr($string, 8, 2);
    return "new Date(".intval($y).", ".(intval($m)-1).", ".intval($d).")";
}

function germanDateSpan($string1, $string2) {
    return germanDates($string1, true, true)." - ".germanDates($string2, true, true);
}

function germanDate($string, $monthLetters) {
    return germanDates($string, $monthLetters, false);
}

function germanDates($string, $monthLetters, $short) {
    if($string == '') {
	return;
    }
    $months = array(
        "01" => "Januar",
        "02" => "Februar",
        "03" => "März",
        "04" => "April",
        "05" => "Mai",
        "06" => "Juni",
        "07" => "Juli",
        "08" => "August",
        "09" => "September",
        "10" => "Oktober",
        "11" => "November",
        "12" => "Dezember"
    );
    $dows = array(
        1 => 'Montag',
        2 => 'Dienstag',
        3 => 'Mittwoch',
        4 => 'Donnerstag',
        5 => 'Freitag',
        6 => 'Samstag',
        7 => 'Sonntag'
    );

    if($short) {
        $months = array(
            "01" => "Jan",
            "02" => "Feb",
            "03" => "Mär",
            "04" => "Apr",
            "05" => "Mai",
            "06" => "Jun",
            "07" => "Jul",
            "08" => "Aug",
            "09" => "Sep",
            "10" => "Okt",
            "11" => "Nov",
            "12" => "Dez"
        );
        $dows = array(
            1 => 'Mo',
            2 => 'Di',
            3 => 'Mi',
            4 => 'Do',
            5 => 'Fr',
            6 => 'Sa',
            7 => 'So'
        );
    }
    $y = substr($string, 0, 4);
    $m = substr($string, 5, 2);
    $d = substr($string, 8, 2);

    $date = mktime(0,0,0, $m, $d, $y);
    $dow = date("N", $date);

    if($monthLetters) {
        $s = $dows[$dow].", ".$d.". ".$months[$m]." ".$y;
    } else {
        $s = $d.".".$m.".".$y;
    }
    return $s;
}

function mkAdmin() {
    $_SESSION['userid'] = 0;
    $_SESSION['admin'] = true;
    $_SESSION['username'] = 'SYSTEM';
}

function validateLink($hash) {
    $_SESSION['userid'] = 0;
    $hash = trim((string)$hash);
    if($hash === '' || !preg_match('/^[a-zA-Z0-9]+$/', $hash)) {
        $logentry = new Log;
        $logentry->error('Login not successful. Invalid hash for login via link.');
        return false;
    }
    $sql = sprintf(
        "SELECT * FROM `%sUser` WHERE `activeLink` = '%s' AND (`Deleted` IS NULL OR `Deleted` != 1) LIMIT 1;",
        identityPrefix(),
        mysqli_real_escape_string($GLOBALS['conn'], $hash)
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
    if($row) {
        $_SESSION['userid'] = $row['Index'];
        $_SESSION['Vorname'] = $row['Vorname'];
        $_SESSION['Nachname'] = $row['Nachname'];
        $_SESSION['username'] = $row['Vorname'].' '.$row['Nachname'];
        $_SESSION['admin'] = computeAdminForUser((int)$row['Index'], (bool)$row['Admin']);
        $_SESSION['singleUsePW'] = (bool)$row['singleUsePW'];
        $logentry = new Log;
        $logentry->info('Login via Link.');
        recordLogin();
        return true;
    }
    $logentry = new Log;
    $logentry->error(
        'Login not successful. Invalid hash for login via link <b>'.htmlspecialchars($hash, ENT_QUOTES, 'UTF-8').'</b>.'
    );
    return false;
}

function validateUser($login, $password) {
    $_SESSION['userid'] = 0;
    $login = trim((string)$login);
    if($login === '' || $password === null || $password === '') {
        $logentry = new Log;
        $logentry->error('Login not successful. Leerer Benutzername oder Passwort.');
        return false;
    }
    $sql = sprintf(
        "SELECT * FROM `%sUser` WHERE `login` = '%s' AND (`Deleted` IS NULL OR `Deleted` != 1);",
        identityPrefix(),
        mysqli_real_escape_string($GLOBALS['conn'], $login)
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    while($row = mysqli_fetch_assoc($dbr)) {
        $hash = (string)$row['Passhash'];
        if($hash !== '' && password_verify($password, $hash)) {
            $_SESSION['userid'] = $row['Index'];
            $_SESSION['Vorname'] = $row['Vorname'];
            $_SESSION['Nachname'] = $row['Nachname'];
            $_SESSION['username'] = $row['Vorname'].' '.$row['Nachname'];
            $_SESSION['admin'] = computeAdminForUser((int)$row['Index'], (bool)$row['Admin']);
            $_SESSION['singleUsePW'] = (bool)$row['singleUsePW'];
            $logentry = new Log;
            $logentry->info('Login via Password.');
            recordLogin();
            return true;
        }
    }
    $logentry = new Log;
    $logentry->error(
        'Login not successful. Invalid password for username <b>'.htmlspecialchars($login, ENT_QUOTES, 'UTF-8').'</b>.'
    );
    return false;
}

function recordLogin() {
    $sql = sprintf("UPDATE `%sUser` SET `LastLogin` = CURRENT_TIMESTAMP() WHERE `Index` = %d;",
		   identityPrefix(),
		   $_SESSION['userid']
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
}

function loggedIn() {
    if(!isset($_SESSION['userid'])) {
	session_destroy();
	return false;
    }
    if($_SESSION['userid'] > 0) return true;
    session_destroy();
    return false;
}

function loginUserBySsoId($userId) {
    $userId = (int)$userId;
    if($userId < 1) {
        return false;
    }
    $sql = sprintf(
        "SELECT * FROM `%sUser` WHERE `Index` = %d AND (`Deleted` IS NULL OR `Deleted` != 1) LIMIT 1;",
        identityPrefix(),
        $userId
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
    if(!$row) {
        return false;
    }
    $_SESSION['userid'] = (int)$row['Index'];
    $_SESSION['Vorname'] = $row['Vorname'];
    $_SESSION['Nachname'] = $row['Nachname'];
    $_SESSION['username'] = $row['Vorname'].' '.$row['Nachname'];
    $_SESSION['admin'] = computeAdminForUser((int)$row['Index'], (bool)$row['Admin']);
    $_SESSION['singleUsePW'] = (bool)$row['singleUsePW'];
    $logentry = new Log();
    $logentry->info('Login via Melde-SSO ticket.');
    recordLogin();
    return true;
}

function tryMeldeSsoLoginFromRequest() {
    if(!isset($_GET['sso']) || trim((string)$_GET['sso']) === '') {
        return false;
    }
    $userId = SsoTicket::redeem($_GET['sso']);
    if(!$userId) {
        return false;
    }
    return loginUserBySsoId($userId);
}

function sql2time($time) {
    if($time != '') {
        return sql2timeRaw($time)." Uhr";
    }
}

function sql2timeRaw($time) {
    return substr($time, 0, 5);
}

function genitiv($string) {
    $last = substr($string, -1);
    if($last == "s" || $last == "x") {
        return $string.'\'';
    }
    else {
        return $string."s";
    }
}

function sqlerror() {
    if(mysqli_errno($GLOBALS['conn'])) {
        echo "<div class=\"w3-container ".$GLOBALS['optionsDB']['colorLogFatal']." w3-mobile w3-border w3-padding w3-border-black\"><b>SQL ERROR </b>".mysqli_errno($GLOBALS['conn']).": ".mysqli_error($GLOBALS['conn'])."</div>";
        $logentry = new Log;
        $logentry->error(mysqli_errno($GLOBALS['conn']).": ".mysqli_error($GLOBALS['conn']));
    }
}

function bin2date($v) {
    $c=array(false, false, false, false, false, false, false);
    for($i=7; $i>=1; $i--) {
        if($v/2**($i-1)>=1) {
            $c[$i-1]=true;
            $v=$v-2**($i-1);
        }
    }
    return $c;
}

function checkCronDate($v) {
    $c = bin2date($v);
    $dow = intval(date("N"));
    if($c[$dow-1] == false) { 
        return false;
    }
    return true;
}

/**
 * Run a git command in the repo root; return stdout or null on failure.
 * @param string $args Arguments after `git` (already escaped where needed)
 * @return string|null
 */
function gitRepoOutput($args) {
    $root = dirname(__DIR__);
    if(!is_dir($root.'/.git') && !is_file($root.'/.git')) {
        return null;
    }
    $cmd = 'git -C '.escapeshellarg($root).' '.$args.' 2>/dev/null';
    $out = array();
    $code = 1;
    exec($cmd, $out, $code);
    if($code !== 0) {
        return null;
    }
    return implode("\n", $out);
}

/** Short git HEAD hash, or null if unavailable. */
function getGitHeadShort() {
    $head = gitRepoOutput('rev-parse --short=7 HEAD');
    if($head === null || $head === '') {
        return null;
    }
    return trim($head);
}

/** Full hash of the git commit that created the current VERSION string, or null. */
function getGitReleaseCommitHash() {
    $version = isset($GLOBALS['version']['String']) ? (string)$GLOBALS['version']['String'] : '';
    if($version === '') {
        return null;
    }
    $hash = gitRepoOutput('log -1 --fixed-strings --grep='.escapeshellarg('release '.$version).' --pretty=%H');
    if($hash === null || $hash === '') {
        return null;
    }
    return trim(explode("\n", $hash)[0]);
}

/** True when working tree HEAD is not exactly the release commit for VERSION. */
function isUnreleasedGitCheckout() {
    $head = gitRepoOutput('rev-parse HEAD');
    $release = getGitReleaseCommitHash();
    if($head === null || $release === null) {
        return false;
    }
    return trim($head) !== trim($release);
}

/**
 * Commit subjects since the VERSION release commit (for unreleased changelog row).
 * @return string[]
 */
function collectUnreleasedGitNotes() {
    $release = getGitReleaseCommitHash();
    if($release === null) {
        return array();
    }
    $log = gitRepoOutput('log --pretty=%s '.escapeshellarg($release.'..HEAD'));
    if($log === null || $log === '') {
        return array();
    }
    $notes = array();
    $seen = array();
    foreach(explode("\n", $log) as $subj) {
        $subj = trim($subj);
        if($subj === '' || isset($seen[$subj])) {
            continue;
        }
        if(preg_match('/^Merge /i', $subj)) {
            continue;
        }
        if(preg_match('/^release\s+/i', $subj)) {
            continue;
        }
        if(preg_match('/^Sync release/i', $subj)) {
            continue;
        }
        $seen[$subj] = true;
        $notes[] = $subj;
        if(count($notes) >= 20) {
            break;
        }
    }
    return $notes;
}

/**
 * Parse CHANGELOG.md into structured release entries.
 * @return array<int,array{version:string,date:string,notes:string[],unreleased?:bool}>
 */
function parseChangelogEntries() {
    $path = dirname(__DIR__).'/CHANGELOG.md';
    if(!is_file($path)) {
        return array();
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if($lines === false) {
        return array();
    }
    $entries = array();
    $current = null;
    foreach($lines as $line) {
        $line = rtrim($line);
        if(preg_match('/^##\s+(\S+)\s+\((\d{4}-\d{2}-\d{2})\)\s*$/', $line, $m)) {
            if($current !== null) {
                $entries[] = $current;
            }
            $current = array(
                'version' => $m[1],
                'date' => $m[2],
                'notes' => array(),
                'unreleased' => false
            );
            continue;
        }
        if($current === null) {
            continue;
        }
        if(preg_match('/^-\s+(.+)$/', $line, $m)) {
            $current['notes'][] = $m[1];
        }
    }
    if($current !== null) {
        $entries[] = $current;
    }
    return $entries;
}

/**
 * Render CHANGELOG.md as an HTML table for the Hilfe page.
 * Prepends an unreleased row when HEAD is ahead of the VERSION release commit.
 */
function renderChangelogHtml() {
    $entries = parseChangelogEntries();
    $currentVersion = isset($GLOBALS['version']['String']) ? (string)$GLOBALS['version']['String'] : '';
    $unreleased = isUnreleasedGitCheckout();
    $headShort = $unreleased ? getGitHeadShort() : null;

    if($unreleased) {
        $notes = collectUnreleasedGitNotes();
        if(!$notes) {
            $notes = array('Noch nicht released (Commit '.($headShort ? $headShort : 'dev').')');
        }
        array_unshift($entries, array(
            'version' => $headShort ? ('unreleased-'.$headShort) : 'unreleased',
            'date' => date('Y-m-d'),
            'notes' => $notes,
            'unreleased' => true
        ));
    }

    if(!$entries) {
        return '<p class="w3-text-gray">Kein Changelog vorhanden.</p>';
    }

    $html = '<div class="help-changelog-wrap">'."\n";
    $html .= '<table class="w3-table w3-striped w3-bordered help-changelog-table">'."\n";
    $html .= '<thead><tr>'
        .'<th>Version</th>'
        .'<th>Datum</th>'
        .'<th>&Auml;nderungen</th>'
        .'</tr></thead>'."\n<tbody>\n";
    foreach($entries as $entry) {
        $notes = $entry['notes'];
        if(!$notes) {
            $notes = array('(keine weiteren Notizen)');
        }
        $isUnreleasedRow = !empty($entry['unreleased']);
        $isCurrent = $isUnreleasedRow
            ? true
            : (!$unreleased && $currentVersion !== '' && $entry['version'] === $currentVersion);
        $rowClass = '';
        if($isCurrent && $isUnreleasedRow) {
            $rowClass = ' class="help-changelog-current help-changelog-unreleased"';
        }
        elseif($isCurrent) {
            $rowClass = ' class="help-changelog-current"';
        }
        elseif($isUnreleasedRow) {
            $rowClass = ' class="help-changelog-unreleased"';
        }
        $html .= '<tr'.$rowClass.'>';
        $html .= '<td class="help-changelog-version"><code>'
            .htmlspecialchars($entry['version'], ENT_QUOTES, 'UTF-8')
            .'</code>';
        if($isCurrent && $isUnreleasedRow) {
            $html .= ' <span class="help-changelog-badge help-changelog-badge-unreleased">nicht released</span>';
        }
        elseif($isCurrent) {
            $html .= ' <span class="help-changelog-badge">aktuell</span>';
        }
        $html .= '</td>';
        $html .= '<td class="help-changelog-date">'
            .htmlspecialchars($entry['date'], ENT_QUOTES, 'UTF-8')
            .'</td>';
        $html .= '<td class="help-changelog-notes"><ul class="help-changelog-list">';
        foreach($notes as $note) {
            $html .= '<li>'.htmlspecialchars($note, ENT_QUOTES, 'UTF-8').'</li>';
        }
        $html .= '</ul></td></tr>'."\n";
    }
    $html .= "</tbody></table>\n</div>\n";
    return $html;
}

/**
 * Chip short label for a composition: "{ID} {Titel} - {Komponist}, {Arrangeur} ({Verlag})".
 * Only set parts; no empty separators.
 *
 * @param string|int|null $registrationNumber
 * @param string $title
 * @param string $composer
 * @param string $arranger
 * @param string $publisher
 * @param int $fallbackId DB Index used only if everything else is empty
 * @return string
 */
function archivCompositionChipLabel($registrationNumber, $title, $composer, $arranger, $publisher, $fallbackId = 0) {
    $id = ($registrationNumber !== null && $registrationNumber !== '')
        ? trim((string)$registrationNumber)
        : '';
    $title = trim(archivPlainText($title));
    $composer = trim(archivPlainText($composer));
    $arranger = trim(archivPlainText($arranger));
    $publisher = trim(archivPlainText($publisher));

    $leftParts = array();
    if($id !== '') {
        $leftParts[] = $id;
    }
    if($title !== '') {
        $leftParts[] = $title;
    }
    $left = implode(' ', $leftParts);

    $peopleParts = array();
    if($composer !== '') {
        $peopleParts[] = $composer;
    }
    if($arranger !== '') {
        $peopleParts[] = $arranger;
    }
    $people = implode(', ', $peopleParts);

    $label = $left;
    if($people !== '') {
        $label = $label !== '' ? ($label.' - '.$people) : $people;
    }
    if($publisher !== '') {
        $label = $label !== '' ? ($label.' ('.$publisher.')') : ('('.$publisher.')');
    }
    if($label === '') {
        $label = 'Stück #'.(int)$fallbackId;
    }
    return $label;
}

/**
 * Build "FirstName LastName" from composer row columns (plain text).
 * @param string|null $firstName
 * @param string|null $lastName
 * @return string
 */
function archivComposerDisplayName($firstName, $lastName) {
    $parts = array();
    $fn = trim(archivPlainText((string)$firstName));
    $ln = trim(archivPlainText((string)$lastName));
    if($fn !== '') {
        $parts[] = $fn;
    }
    if($ln !== '') {
        $parts[] = $ln;
    }
    return implode(' ', $parts);
}

/**
 * Catalog entries for composition typeahead: [{id, label, meta}, ...]
 * @return array
 */
function archivCompositionsCatalog() {
    $out = array();
    $p = $GLOBALS['dbprefix'];
    $sql = sprintf(
        'SELECT c.`Index`, c.`RegistrationNumber`, c.`Title`,'
        .' comp.`FirstName` AS `ComposerFirst`, comp.`LastName` AS `ComposerLast`,'
        .' arr.`FirstName` AS `ArrangerFirst`, arr.`LastName` AS `ArrangerLast`,'
        .' pub.`Name` AS `PublisherName`'
        .' FROM `%sComposition` c'
        .' LEFT JOIN `%sComposer` comp ON comp.`Index` = c.`Composer`'
        .' LEFT JOIN `%sComposer` arr ON arr.`Index` = c.`Arranger`'
        .' LEFT JOIN `%sPublisher` pub ON pub.`Index` = c.`Publisher`'
        .' ORDER BY c.`RegistrationNumber`, c.`Title`;',
        $p, $p, $p, $p
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    while($row = mysqli_fetch_array($dbr)) {
        $out[] = array(
            'id' => (int)$row['Index'],
            'label' => archivCompositionChipLabel(
                $row['RegistrationNumber'],
                $row['Title'],
                archivComposerDisplayName($row['ComposerFirst'], $row['ComposerLast']),
                archivComposerDisplayName($row['ArrangerFirst'], $row['ArrangerLast']),
                isset($row['PublisherName']) ? $row['PublisherName'] : '',
                (int)$row['Index']
            ),
            'meta' => '',
        );
    }
    return $out;
}

/**
 * Catalog entries for collection typeahead: [{id, label}, ...]
 * @return array
 */
function archivCollectionsCatalog() {
    $out = array();
    $sql = sprintf(
        'SELECT `Index`, `Name` FROM `%sCollection` ORDER BY `Name`;',
        $GLOBALS['dbprefix']
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    while($row = mysqli_fetch_array($dbr)) {
        $name = archivPlainText($row['Name']);
        $out[] = array(
            'id' => (int)$row['Index'],
            'label' => $name !== '' ? $name : ('Sammlung #'.(int)$row['Index']),
            'meta' => '',
        );
    }
    return $out;
}

/**
 * Parse chip spec JSON into list of {id, number}.
 * Returns null if JSON is invalid (callers must not treat that as "clear all").
 * @param string $json
 * @return array|null
 */
function archivParseCollectionChipSpec($json) {
    $raw = (string)$json;
    $decoded = json_decode($raw, true);
    if(json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return null;
    }
    $out = array();
    $seen = array();
    foreach($decoded as $row) {
        if(!is_array($row)) {
            continue;
        }
        $id = isset($row['id']) ? (int)$row['id'] : 0;
        if($id < 1 || isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        $out[] = array(
            'id' => $id,
            'number' => isset($row['number']) ? (int)$row['number'] : 0,
        );
    }
    return $out;
}

/**
 * Sync CollectionItem rows for one collection (id = Composition).
 * @param int $collectionId
 * @param array $items list of {id: compositionId, number}
 */
function archivSyncCollectionItemsForCollection($collectionId, array $items) {
    $collectionId = (int)$collectionId;
    if($collectionId < 1) {
        return;
    }
    $wanted = array();
    foreach($items as $row) {
        $compId = (int)$row['id'];
        if($compId < 1) {
            continue;
        }
        $wanted[$compId] = (int)$row['number'];
    }

    $existing = array();
    $sql = sprintf(
        'SELECT `Index`, `Composition`, `CollectionNumber` FROM `%sCollectionItem` WHERE `Collections` = "%d";',
        $GLOBALS['dbprefix'],
        $collectionId
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    while($row = mysqli_fetch_array($dbr)) {
        $compId = (int)$row['Composition'];
        $idx = (int)$row['Index'];
        if(isset($existing[$compId])) {
            $dup = new Collection;
            $dup->load_by_id($idx);
            $dup->delete();
            continue;
        }
        $existing[$compId] = array(
            'Index' => $idx,
            'number' => (int)$row['CollectionNumber'],
        );
    }

    foreach($existing as $compId => $info) {
        if(!isset($wanted[$compId])) {
            $item = new Collection;
            $item->load_by_id($info['Index']);
            $item->delete();
        }
    }

    foreach($wanted as $compId => $number) {
        if(isset($existing[$compId])) {
            $item = new Collection;
            $item->load_by_id($existing[$compId]['Index']);
            $item->Collections = $collectionId;
            $item->Composition = $compId;
            $item->CollectionNumber = $number;
            $item->save();
        }
        else {
            $item = new Collection;
            $item->Collections = $collectionId;
            $item->Composition = $compId;
            $item->CollectionNumber = $number;
            $item->save();
        }
    }
}

/**
 * Sync CollectionItem rows for one composition (id = Collections).
 * @param int $compositionId
 * @param array $items list of {id: collectionId, number}
 */
function archivSyncCollectionItemsForComposition($compositionId, array $items) {
    $compositionId = (int)$compositionId;
    if($compositionId < 1) {
        return;
    }
    $wanted = array();
    foreach($items as $row) {
        $colId = (int)$row['id'];
        if($colId < 1) {
            continue;
        }
        $wanted[$colId] = (int)$row['number'];
    }

    $existing = array();
    $sql = sprintf(
        'SELECT `Index`, `Collections`, `CollectionNumber` FROM `%sCollectionItem` WHERE `Composition` = "%d";',
        $GLOBALS['dbprefix'],
        $compositionId
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    while($row = mysqli_fetch_array($dbr)) {
        $colId = (int)$row['Collections'];
        $idx = (int)$row['Index'];
        if(isset($existing[$colId])) {
            $dup = new Collection;
            $dup->load_by_id($idx);
            $dup->delete();
            continue;
        }
        $existing[$colId] = array(
            'Index' => $idx,
            'number' => (int)$row['CollectionNumber'],
        );
    }

    foreach($existing as $colId => $info) {
        if(!isset($wanted[$colId])) {
            $item = new Collection;
            $item->load_by_id($info['Index']);
            $item->delete();
        }
    }

    foreach($wanted as $colId => $number) {
        if(isset($existing[$colId])) {
            $item = new Collection;
            $item->load_by_id($existing[$colId]['Index']);
            $item->Collections = $colId;
            $item->Composition = $compositionId;
            $item->CollectionNumber = $number;
            $item->save();
        }
        else {
            $item = new Collection;
            $item->Collections = $colId;
            $item->Composition = $compositionId;
            $item->CollectionNumber = $number;
            $item->save();
        }
    }
}

/**
 * Markup + script bootstrap for a collection chip editor.
 * @param string $prefix unique DOM id prefix
 * @param string $chipClass mail-recipient-chip--composition|collection
 * @param string $hiddenName POST field name
 * @param array $catalog
 * @param array $initial [{id,number},...]
 * @param string $placeholder
 * @return string
 */
function archivCollectionChipsEditorHtml($prefix, $chipClass, $hiddenName, array $catalog, array $initial, $placeholder) {
    $inputBg = isset($GLOBALS['optionsDB']['colorInputBackground'])
        ? (string)$GLOBALS['optionsDB']['colorInputBackground']
        : '';
    $chipsId = $prefix.'-chips';
    $inputId = $prefix.'-input';
    $suggestId = $prefix.'-suggest';
    $hiddenId = $prefix.'-spec';
    $catalogId = $prefix.'-catalog';

    $html = '<div class="profile-field collection-chips-editor">';
    $html .= '<div id="'.archivEscHtml($chipsId).'" class="mail-recipient-chips" role="list" aria-live="polite"></div>';
    $html .= '<input id="'.archivEscHtml($inputId).'" type="text" class="w3-input w3-border profile-control '.archivEscHtml($inputBg).'"'
        .' placeholder="'.archivEscHtml($placeholder).'" autocomplete="off" aria-label="'.archivEscHtml($placeholder).'">';
    $html .= '<div id="'.archivEscHtml($suggestId).'" class="mail-recipient-suggest" hidden></div>';
    $html .= '<input type="hidden" name="'.archivEscHtml($hiddenName).'" id="'.archivEscHtml($hiddenId).'" value="'.archivEscHtml(json_encode(array_values($initial))).'">';
    $html .= '<script type="application/json" id="'.archivEscHtml($catalogId).'">'
        .json_encode(
            array_values($catalog),
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
        )
        .'</script>';
    $html .= '</div>';
    $html .= '<script src="'.archivEscHtml(assetUrl('js/collectionChips.js')).'"></script>';
    $html .= '<script>(function(){';
    $html .= 'var catEl=document.getElementById('.json_encode($catalogId).');';
    $html .= 'var catalog=[]; try{ catalog=JSON.parse(catEl.textContent||"[]"); }catch(e){}';
    $html .= 'var hid=document.getElementById('.json_encode($hiddenId).');';
    $html .= 'CollectionChips.init({';
    $html .= 'chipsEl:document.getElementById('.json_encode($chipsId).'),';
    $html .= 'inputEl:document.getElementById('.json_encode($inputId).'),';
    $html .= 'suggestEl:document.getElementById('.json_encode($suggestId).'),';
    $html .= 'hiddenEl:hid,';
    $html .= 'catalog:catalog,';
    $html .= 'initial:'.json_encode(array_values($initial), JSON_UNESCAPED_UNICODE).',';
    $html .= 'chipClass:'.json_encode($chipClass).',';
    $html .= 'inputBg:'.json_encode($inputBg);
    $html .= '});})();</script>';
    return $html;
}

/**
 * Render a PHP view from views/ and return the HTML.
 * @param string $view Path relative to views/ without .php (e.g. 'help/guide')
 * @param array $vars Variables extracted into the view scope
 * @return string
 */
function render($view, $vars = array()) {
    $path = dirname(__DIR__).'/views/'.$view.'.php';
    if(!is_file($path)) {
        trigger_error('View not found: '.$view, E_USER_WARNING);
        return '';
    }
    extract($vars, EXTR_SKIP);
    ob_start();
    include $path;
    return ob_get_clean();
}

?>
