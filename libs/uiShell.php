<?php
/**
 * Melde-compatible UI shell helpers (ARCHIV UI port).
 * No Melde PHP includes — copied/adapted patterns only.
 */

function isHexColor($value) {
    if(!is_string($value)) return false;
    return (bool)preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', trim($value));
}

function normalizeHexColor($value) {
    $value = strtoupper(trim((string)$value));
    if(!isHexColor($value)) return '';
    if(strlen($value) === 4) {
        return '#'.$value[1].$value[1].$value[2].$value[2].$value[3].$value[3];
    }
    return $value;
}

/** Map legacy w3 / highway / mvd color classes to hex for &lt;input type="color"&gt;. */
function w3ColorToHex($class) {
    $map = array(
        'w3-red' => '#F44336',
        'w3-pink' => '#E91E63',
        'w3-purple' => '#9C27B0',
        'w3-deep-purple' => '#673AB7',
        'w3-indigo' => '#3F51B5',
        'w3-blue' => '#2196F3',
        'w3-light-blue' => '#03A9F4',
        'w3-aqua' => '#00BCD4',
        'w3-cyan' => '#00BCD4',
        'w3-teal' => '#009688',
        'w3-green' => '#4CAF50',
        'w3-light-green' => '#8BC34A',
        'w3-lime' => '#CDDC39',
        'w3-sand' => '#FDF5E6',
        'w3-khaki' => '#F0E68C',
        'w3-yellow' => '#FFEB3B',
        'w3-amber' => '#FFC107',
        'w3-orange' => '#FF9800',
        'w3-deep-orange' => '#FF5722',
        'w3-blue-gray' => '#607D8B',
        'w3-brown' => '#795548',
        'w3-light-gray' => '#F1F1F1',
        'w3-gray' => '#9E9E9E',
        'w3-dark-gray' => '#616161',
        'w3-pale-red' => '#FFDDDD',
        'w3-pale-green' => '#DDFFDD',
        'w3-pale-yellow' => '#FFFFCC',
        'w3-pale-blue' => '#DDFFFF',
        'w3-highway-brown' => '#633517',
        'w3-highway-red' => '#A6001A',
        'w3-highway-orange' => '#E06000',
        'w3-highway-schoolbus' => '#EE9600',
        'w3-highway-yellow' => '#FFAB00',
        'w3-highway-green' => '#004D33',
        'w3-highway-blue' => '#00477E',
        'w3-mvd-white' => '#FDFFFC',
        'w3-mvd-black' => '#040006',
        'w3-mvd-blue' => '#345A95',
        'w3-mvd-gray' => '#969696',
        'w3-mvd-darkgray' => '#454545',
        'w3-mvd-egg' => '#FDF9E7',
        'w3-mvd-yellow' => '#FFC300',
        'w3-mvd-lightblue' => '#7F9DC1',
    );
    $class = trim((string)$class);
    return isset($map[$class]) ? $map[$class] : '#808080';
}

function colorPickerValue($raw) {
    $raw = trim((string)$raw);
    if($raw === '') return '#808080';
    if(isHexColor($raw)) return normalizeHexColor($raw);
    return w3ColorToHex($raw);
}

function hexContrastText($hex) {
    $hex = normalizeHexColor($hex);
    if($hex === '') return '#000000';
    $r = hexdec(substr($hex, 1, 2));
    $g = hexdec(substr($hex, 3, 2));
    $b = hexdec(substr($hex, 5, 2));
    $luma = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
    return ($luma > 0.55) ? '#000000' : '#FFFFFF';
}

function colorToCssClass($value) {
    $value = trim((string)$value);
    if($value === '') return '';
    if(isHexColor($value)) {
        $hex = normalizeHexColor($value);
        $class = 'cfg-hex-'.strtolower(substr($hex, 1));
        if(!isset($GLOBALS['cfgColorCssRules'])) {
            $GLOBALS['cfgColorCssRules'] = array();
        }
        $GLOBALS['cfgColorCssRules'][$class] = array(
            'bg' => $hex,
            'fg' => hexContrastText($hex),
        );
        return $class;
    }
    return $value;
}

function renderConfigColorCss($wrapStyleTag = true) {
    $css = '';
    $pageBg = '#FDFFFC';
    $bgClass = isset($GLOBALS['optionsDB']['colorBackground']) ? (string)$GLOBALS['optionsDB']['colorBackground'] : '';
    if($bgClass !== ''
        && !empty($GLOBALS['cfgColorCssRules'][$bgClass]['bg'])
        && isHexColor($GLOBALS['cfgColorCssRules'][$bgClass]['bg'])) {
        $pageBg = normalizeHexColor($GLOBALS['cfgColorCssRules'][$bgClass]['bg']);
    }
    $css .= ':root{--app-page-bg:'.$pageBg.';}';
    if(!empty($GLOBALS['cfgColorCssRules']) && is_array($GLOBALS['cfgColorCssRules'])) {
        foreach($GLOBALS['cfgColorCssRules'] as $class => $colors) {
            $css .= '.'.preg_replace('/[^a-z0-9\-]/i', '', $class)
                .'{color:'.$colors['fg'].' !important;background-color:'.$colors['bg'].' !important;}';
        }
    }
    if($css === '') return '';
    return $wrapStyleTag ? '<style type="text/css">'.$css.'</style>' : $css;
}

/**
 * Melde-parity hook for group chrome colors.
 * Archiv has no Groups UI yet — emit system accents only.
 */
function renderPermissionGroupColorCss($wrapStyleTag = true) {
    $accent = '#345A95';
    $strong = '#7F9DC1';
    $css = '';
    $css .= '.admin-list-shell:has(.admin-list-hero--system){--page-title-accent:'.$accent.';}';
    $css .= '.profile-shell .profile-hero.admin-list-hero--system,.w3-container.admin-list-hero--system{background:'.$strong.';border-left-color:'.$accent.';--page-title-accent:'.$accent.';}';
    $css .= '.app-nav .admin-nav-perm--system{background:#E8EEF5 !important;border-color:'.$accent.';color:#222 !important;}';
    return $wrapStyleTag ? '<style type="text/css" id="perm-group-colors">'.$css.'</style>' : $css;
}

function getColorConfigParameters() {
    static $params = null;
    if($params !== null) return $params;
    $params = array();
    if(function_exists('getConfigDefaults')) {
        foreach(getConfigDefaults() as $item) {
            if(isset($item['Type']) && $item['Type'] === 'color' && isset($item['Parameter'])) {
                $params[$item['Parameter']] = true;
            }
        }
    }
    return $params;
}

/** Cache-busting URL for static assets. */
function assetUrl($rel) {
    $rel = ltrim(str_replace('\\', '/', (string)$rel), '/');
    $hash = isset($GLOBALS['version']['Hash']) ? (string)$GLOBALS['version']['Hash'] : '0';
    $mtime = @filemtime(dirname(__DIR__).'/'.$rel);
    return htmlspecialchars($rel.'?'.$hash.'-'.$mtime, ENT_QUOTES, 'UTF-8');
}

/** Queue modal HTML outside .app-main (overflow/z-index). */
function deferPageModalHtml($html) {
    $html = (string)$html;
    if($html === '') {
        return;
    }
    if(!isset($GLOBALS['mlDeferredPageModals'])) {
        $GLOBALS['mlDeferredPageModals'] = '';
    }
    $GLOBALS['mlDeferredPageModals'] .= $html;
}

function navGroupClass($groupId) {
    $gid = preg_replace('/[^a-z0-9_-]/i', '', (string)$groupId);
    if($gid === '') {
        $gid = 'system';
    }
    return 'admin-nav-perm admin-nav-perm--'.$gid;
}

function adminListSectionGroupId($kicker) {
    $map = array(
        'Archiv' => 'system',
        'Stücke' => 'system',
        'Mappen' => 'system',
        'Komponisten' => 'system',
        'Verlage' => 'system',
        'System' => 'system',
        'Hilfe' => 'system',
        'Konfiguration' => 'system',
        'Log' => 'system',
    );
    $k = trim((string)$kicker);
    return isset($map[$k]) ? $map[$k] : 'system';
}

function adminHeroClass($options = array()) {
    if(isset($options['groupId']) && (string)$options['groupId'] !== '') {
        $gid = (string)$options['groupId'];
    }
    elseif(isset($options['kicker'])) {
        $gid = adminListSectionGroupId((string)$options['kicker']);
    }
    else {
        $gid = 'system';
    }
    $gid = preg_replace('/[^a-z0-9_-]/i', '', (string)$gid);
    if($gid === '') {
        $gid = 'system';
    }
    $withProfile = !array_key_exists('withProfileHero', $options) || !empty($options['withProfileHero']);
    $base = $withProfile ? 'profile-hero admin-list-hero' : 'admin-list-hero';
    return $base.' admin-list-hero--'.$gid;
}

function adminListPageBegin($kicker, $title, $options = array()) {
    $actionsHtml = isset($options['actionsHtml']) ? (string)$options['actionsHtml'] : '';
    $shellClass = isset($options['shellClass']) ? trim((string)$options['shellClass']) : '';
    $shellCls = 'profile-shell admin-list-shell'.($shellClass !== '' ? ' '.$shellClass : '');
    $heroOpts = array('kicker' => $kicker);
    if(isset($options['groupId'])) {
        $heroOpts['groupId'] = $options['groupId'];
    }
    $heroCls = adminHeroClass($heroOpts);
    echo '<div class="profile-page">'."\n";
    echo '  <div class="'.htmlspecialchars($shellCls, ENT_QUOTES, 'UTF-8').'">'."\n";
    echo '    <div class="app-page-chrome">'."\n";
    echo '    <header class="'.htmlspecialchars($heroCls, ENT_QUOTES, 'UTF-8').'">'."\n";
    echo '      <div class="profile-hero-text">'."\n";
    echo '        <p class="profile-kicker">'.htmlspecialchars((string)$kicker, ENT_QUOTES, 'UTF-8').'</p>'."\n";
    echo '        <h2 class="profile-title">'.htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8').'</h2>'."\n";
    echo '      </div>'."\n";
    if($actionsHtml !== '') {
        echo '      <div class="profile-hero-actions">'.$actionsHtml.'</div>'."\n";
    }
    echo '    </header>'."\n";
    $GLOBALS['mlAdminListChrome'] = 'open';
    $GLOBALS['mlAdminListBody'] = null;
    ob_start();
    $GLOBALS['mlAdminListCapturing'] = true;
}

function adminListFlushChromeCapture() {
    if(empty($GLOBALS['mlAdminListCapturing'])) {
        return '';
    }
    $chunk = ob_get_clean();
    $GLOBALS['mlAdminListCapturing'] = false;
    return ($chunk === false) ? '' : $chunk;
}

function adminListChromeClose($captureToBody = false) {
    if(empty($GLOBALS['mlAdminListChrome']) || $GLOBALS['mlAdminListChrome'] !== 'open') {
        return;
    }
    $chunk = adminListFlushChromeCapture();
    if(!$captureToBody && $chunk !== '') {
        echo $chunk;
        $chunk = '';
    }
    echo '    </div><!-- .app-page-chrome -->'."\n";
    echo '    <div class="admin-list-body">'."\n";
    $GLOBALS['mlAdminListChrome'] = 'closed';
    $GLOBALS['mlAdminListBody'] = 'open';
    if($captureToBody && $chunk !== '') {
        echo $chunk;
    }
}

function adminListPageEnd() {
    if(!empty($GLOBALS['mlAdminListChrome']) && $GLOBALS['mlAdminListChrome'] === 'open') {
        adminListChromeClose(true);
    }
    if(!empty($GLOBALS['mlAdminListBody']) && $GLOBALS['mlAdminListBody'] === 'open') {
        echo '    </div><!-- .admin-list-body -->'."\n";
        $GLOBALS['mlAdminListBody'] = 'closed';
    }
    echo '  </div>'."\n";
    echo '</div>'."\n";
}

function adminListSearchField($placeholder, $options = array()) {
    $id = isset($options['id']) ? (string)$options['id'] : 'filterString';
    $onkeyup = isset($options['onkeyup']) ? (string)$options['onkeyup'] : '';
    $extra = isset($options['extraHtml']) ? (string)$options['extraHtml'] : '';
    $inputBg = isset($GLOBALS['optionsDB']['colorInputBackground'])
        ? (string)$GLOBALS['optionsDB']['colorInputBackground'] : '';
    $aria = isset($options['ariaLabel']) && (string)$options['ariaLabel'] !== ''
        ? (string)$options['ariaLabel']
        : (string)$placeholder;
    $pre = adminListFlushChromeCapture();
    if($pre !== '') {
        echo $pre;
    }
    echo '    <div class="admin-list-toolbar">'."\n";
    echo '      <div class="profile-field admin-list-search">'."\n";
    echo '        <input type="search" id="'.htmlspecialchars($id, ENT_QUOTES, 'UTF-8').'"'
        .' class="w3-input w3-border profile-control '.htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8').'"'
        .' placeholder="'.htmlspecialchars((string)$placeholder, ENT_QUOTES, 'UTF-8').'"'
        .' aria-label="'.htmlspecialchars($aria, ENT_QUOTES, 'UTF-8').'"'
        .' autocomplete="off"';
    if($onkeyup !== '') {
        echo ' onkeyup="'.htmlspecialchars($onkeyup, ENT_QUOTES, 'UTF-8').'"';
    }
    echo '>'."\n";
    echo '      </div>'."\n";
    if($extra !== '') {
        echo '      <div class="admin-list-toolbar-extra">'.$extra.'</div>'."\n";
    }
    echo '    </div>'."\n";
    adminListChromeClose(false);
}

function adminNavGroupActiveClass($pages) {
    if(empty($_SESSION['adminpage'])) {
        return '';
    }
    $current = isset($_SESSION['page']) ? (string)$_SESSION['page'] : '';
    if($current === '') {
        return '';
    }
    foreach((array)$pages as $p) {
        if((string)$p === $current) {
            return ' admin-nav-open admin-nav-current-group';
        }
    }
    return '';
}
?>
