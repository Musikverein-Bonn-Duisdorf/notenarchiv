#!/usr/bin/env php
<?php
/**
 * Limited prod media fill (test): up to N publishers / composers / compositions.
 * Env: ARCHIV_API_BASE, ARCHIV_API_TOKEN
 * Usage: php scripts/fillProdMediaGapsLimited.php [--limit=10]
 */
if(PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root.'/scripts/lib/publisherLogoFetch.php';
require_once $root.'/scripts/lib/composerPortraitFetch.php';
require_once $root.'/scripts/lib/publisherCoverFetch.php';

$base = rtrim((string)getenv('ARCHIV_API_BASE'), '/');
$token = (string)getenv('ARCHIV_API_TOKEN');
if($base === '' || $token === '') {
    fwrite(STDERR, "Set ARCHIV_API_BASE + ARCHIV_API_TOKEN\n");
    exit(1);
}

$limit = 50;
$skipLogos = in_array('--skip-logos', $argv, true);
$skipAvatars = in_array('--skip-avatars', $argv, true);
$skipCovers = in_array('--skip-covers', $argv, true);
foreach($argv as $arg) {
    if(strpos($arg, '--limit=') === 0) {
        $limit = max(1, min(200, (int)substr($arg, 8)));
    }
}

function apiRequest($method, $path, $token, $base, $json = null, $multipart = null) {
    $url = $base.$path;
    $ch = curl_init($url);
    $headers = array(
        'Authorization: Bearer '.$token,
        'X-Archiv-Token: '.$token,
    );
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    if($multipart !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart);
    } elseif($json !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string)$body, true);
    return array($code, is_array($data) ? $data : array('raw' => $body));
}

list($code, $me) = apiRequest('GET', '/me.php', $token, $base);
if($code !== 200 || empty($me['ok'])) {
    fwrite(STDERR, "Auth failed: ".json_encode($me)."\n");
    exit(1);
}
fwrite(STDERR, "API OK as ".($me['user']['name'] ?? '?')." limit={$limit}"
    .($skipLogos ? ' skip-logos' : '')
    .($skipAvatars ? ' skip-avatars' : '')
    .($skipCovers ? ' skip-covers' : '')
    ."\n");

// --- Publishers: logo only when website already set; never invent websites ---
list($code, $plist) = apiRequest('GET', '/publishers.php?limit=200', $token, $base);
$websiteById = array();
foreach(($plist['publishers'] ?? array()) as $row) {
    $websiteById[(int)$row['id']] = isset($row['website']) ? trim((string)$row['website']) : '';
}
$pubOk = $pubFail = $pubSkip = 0;
$pubDone = 0;
foreach(($plist['publishers'] ?? array()) as $row) {
    if($skipLogos) {
        break;
    }
    if($pubDone >= $limit) {
        break;
    }
    $id = (int)$row['id'];
    $name = (string)$row['name'];
    $website = $websiteById[$id] ?? '';
    if($website === '') {
        fwrite(STDOUT, "[logo] {$id} {$name} … skip (no website)\n");
        $pubSkip++;
        continue;
    }
    if(!empty($row['avatar'])) {
        fwrite(STDOUT, "[logo] {$id} {$name} … skip (has avatar)\n");
        $pubSkip++;
        continue;
    }
    fwrite(STDOUT, "[logo] {$id} {$name} … ");
    $resolved = archivResolvePublisherLogo($website);
    if(!$resolved || empty($resolved['path'])) {
        fwrite(STDOUT, "no logo\n");
        $pubFail++;
        $pubDone++;
        continue;
    }
    $cfile = new CURLFile($resolved['path'], 'image/png', 'avatar.png');
    list($code, $res) = apiRequest('POST', '/publishers/avatar.php', $token, $base, null, array(
        'id' => (string)$id,
        'avatar' => $cfile,
    ));
    @unlink($resolved['path']);
    if(empty($res['ok'])) {
        fwrite(STDOUT, "upload fail ".json_encode($res)."\n");
        $pubFail++;
    } else {
        fwrite(STDOUT, "ok {$resolved['source']}\n");
        $pubOk++;
    }
    $pubDone++;
    usleep(150000);
}
fwrite(STDERR, "Publishers: ok={$pubOk} fail={$pubFail} skip={$pubSkip} attempted={$pubDone}\n");

list($code, $gapsResp) = apiRequest('GET', '/gaps/media.php?limit='.(int)$limit, $token, $base);
$gaps = $gapsResp['gaps'] ?? $gapsResp;

// --- Composers ---
$avOk = $avFail = 0;
foreach(($skipAvatars ? array() : array_slice($gaps['composers_without_avatar'] ?? array(), 0, $limit)) as $row) {
    $id = (int)$row['id'];
    $first = (string)($row['firstName'] ?? '');
    $last = (string)($row['lastName'] ?? '');
    fwrite(STDOUT, "[avatar] {$id} ".trim($first.' '.$last)." … ");
    $resolved = archivResolveComposerPortrait($first, $last);
    if(!$resolved || empty($resolved['url'])) {
        fwrite(STDOUT, "no match\n");
        $avFail++;
        continue;
    }
    $file = archivDownloadPortraitFile($resolved['url']);
    if(!$file) {
        fwrite(STDOUT, "download fail ({$resolved['source']})\n");
        $avFail++;
        continue;
    }
    $cfile = new CURLFile($file, 'image/jpeg', 'avatar.jpg');
    list($code, $res) = apiRequest('POST', '/composers/avatar.php', $token, $base, null, array(
        'id' => (string)$id,
        'avatar' => $cfile,
    ));
    @unlink($file);
    if(empty($res['ok'])) {
        fwrite(STDOUT, "upload fail ".json_encode($res)."\n");
        $avFail++;
    } else {
        fwrite(STDOUT, "ok {$resolved['source']}\n");
        $avOk++;
    }
    usleep(200000);
}
fwrite(STDERR, "Composers: ok={$avOk} fail={$avFail}\n");

// --- Compositions: product URL (optional) + cover ---
$covOk = $covFail = $urlOk = $urlFail = 0;
$uploadedCovers = array();
foreach(($skipCovers ? array() : array_slice($gaps['compositions_without_cover'] ?? array(), 0, $limit)) as $row) {
    $id = (int)$row['id'];
    $title = (string)$row['title'];
    list($code, $detail) = apiRequest('GET', '/compositions.php?id='.$id, $token, $base);
    $comp = $detail['composition'] ?? array();
    $pubId = isset($comp['publisherId']) ? (int)$comp['publisherId'] : 0;
    $pubWeb = ($pubId > 0 && isset($websiteById[$pubId])) ? $websiteById[$pubId] : '';

    fwrite(STDOUT, "[url] {$id} {$title} … ");
    $resolved = archivResolvePublisherCover($title, $pubWeb);
    $page = ($resolved && !empty($resolved['page'])) ? (string)$resolved['page'] : '';
    if($page === '' || !empty($comp['website'])) {
        fwrite(STDOUT, !empty($comp['website']) ? "skip\n" : "no match\n");
        if($page === '' && empty($comp['website'])) {
            $urlFail++;
        }
    } else {
        list($code, $res) = apiRequest('PATCH', '/compositions.php', $token, $base, array(
            'id' => $id,
            'website' => $page,
        ));
        if(empty($res['ok'])) {
            fwrite(STDOUT, "patch fail\n");
            $urlFail++;
        } else {
            fwrite(STDOUT, "ok {$resolved['source']}\n");
            $urlOk++;
        }
    }

    fwrite(STDOUT, "[cover] {$id} {$title} … ");
    if(!$resolved || empty($resolved['url'])) {
        fwrite(STDOUT, "no match\n");
        $covFail++;
        continue;
    }
    $file = archivDownloadCoverFile($resolved['url']);
    if(!$file) {
        fwrite(STDOUT, "download fail ({$resolved['source']})\n");
        $covFail++;
        continue;
    }
    $cfile = new CURLFile($file, 'image/jpeg', 'cover.jpg');
    list($code, $res) = apiRequest('POST', '/compositions/cover.php', $token, $base, null, array(
        'id' => (string)$id,
        'cover' => $cfile,
    ));
    @unlink($file);
    if(empty($res['ok'])) {
        fwrite(STDOUT, "upload fail ".json_encode($res)."\n");
        $covFail++;
    } else {
        fwrite(STDOUT, "ok {$resolved['source']}\n");
        $covOk++;
        if(!empty($res['path'])) {
            $uploadedCovers[] = (string)$res['path'];
        } elseif(!empty($res['cover'])) {
            $uploadedCovers[] = (string)$res['cover'];
        }
    }
    usleep(250000);
}
fwrite(STDERR, "URLs: ok={$urlOk} fail={$urlFail}\n");
fwrite(STDERR, "Covers: ok={$covOk} fail={$covFail}\n");
fwrite(STDERR, "Done.\n");
?>
