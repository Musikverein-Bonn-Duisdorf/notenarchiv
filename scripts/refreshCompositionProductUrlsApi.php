#!/usr/bin/env php
<?php
/**
 * Resolve and store composition product-page URLs (Composition.Website).
 *
 * Prefers a match on the publisher Website, then Rundel catalog product pages.
 *
 * Env: ARCHIV_API_BASE, ARCHIV_API_TOKEN
 * Or:  php scripts/refreshCompositionProductUrlsApi.php --local [--login=MVD] [--limit=200]
 */
if(PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root.'/scripts/lib/publisherCoverFetch.php';

$local = in_array('--local', $argv, true);
$login = 'MVD';
$limit = 200;
$onlyMissing = in_array('--only-missing', $argv, true);
foreach($argv as $arg) {
    if(strpos($arg, '--login=') === 0) {
        $login = substr($arg, 8);
    }
    if(strpos($arg, '--limit=') === 0) {
        $limit = max(1, min(500, (int)substr($arg, 8)));
    }
}

$base = rtrim((string)getenv('ARCHIV_API_BASE'), '/');
$token = (string)getenv('ARCHIV_API_TOKEN');
$serverProc = null;

if($local || $base === '' || $token === '') {
    if(!$local && ($base === '' || $token === '')) {
        fwrite(STDERR, "Set ARCHIV_API_BASE + ARCHIV_API_TOKEN, or pass --local\n");
        exit(1);
    }
    chdir($root);
    $tokCmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/issueApiToken.php').' '.escapeshellarg($login).' product-url-refresh';
    $tokOut = array();
    $tokCode = 0;
    exec($tokCmd.' 2>/dev/null', $tokOut, $tokCode);
    $token = isset($tokOut[0]) ? trim($tokOut[0]) : '';
    if($token === '' || $tokCode !== 0) {
        fwrite(STDERR, "Could not issue API token for login {$login}\n");
        exit(1);
    }
    $port = 8770;
    $base = 'http://127.0.0.1:'.$port.'/api';
    $cmd = escapeshellarg(PHP_BINARY).' -S 127.0.0.1:'.$port.' -t '.escapeshellarg($root);
    $descriptors = array(
        0 => array('file', '/dev/null', 'r'),
        1 => array('file', sys_get_temp_dir().'/archiv-product-url-refresh.log', 'a'),
        2 => array('file', sys_get_temp_dir().'/archiv-product-url-refresh.log', 'a'),
    );
    $serverProc = proc_open($cmd, $descriptors, $pipes, $root);
    if(!is_resource($serverProc)) {
        fwrite(STDERR, "Failed to start php -S\n");
        exit(1);
    }
    usleep(400000);
}

function apiRequest($method, $path, $token, $base, $json = null) {
    $url = $base.$path;
    $ch = curl_init($url);
    $headers = array('Authorization: Bearer '.$token);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if($json !== null) {
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

try {
    list($code, $me) = apiRequest('GET', '/me.php', $token, $base);
    if($code !== 200 || empty($me['ok'])) {
        fwrite(STDERR, "Auth failed HTTP {$code}: ".json_encode($me)."\n");
        exit(1);
    }

    list($code, $pubs) = apiRequest('GET', '/publishers.php?limit=200', $token, $base);
    $websiteById = array();
    foreach(($pubs['publishers'] ?? array()) as $row) {
        $websiteById[(int)$row['id']] = isset($row['website']) ? (string)$row['website'] : '';
    }

    list($code, $plist) = apiRequest('GET', '/compositions.php?limit='.(int)$limit, $token, $base);
    if($code !== 200 || empty($plist['ok'])) {
        fwrite(STDERR, "List compositions failed: ".json_encode($plist)."\n");
        exit(1);
    }

    $ok = 0;
    $fail = 0;
    $skip = 0;
    foreach(($plist['compositions'] ?? array()) as $row) {
        $id = (int)$row['id'];
        $title = (string)$row['title'];
        $existing = isset($row['website']) ? trim((string)$row['website']) : '';
        if($onlyMissing && $existing !== '') {
            $skip++;
            continue;
        }
        $pubId = isset($row['publisherId']) ? (int)$row['publisherId'] : 0;
        $pubWeb = ($pubId > 0 && isset($websiteById[$pubId])) ? $websiteById[$pubId] : '';

        fwrite(STDOUT, "{$id} {$title} … ");
        $resolved = archivResolvePublisherCover($title, $pubWeb);
        $page = ($resolved && !empty($resolved['page'])) ? (string)$resolved['page'] : '';
        if($page === '') {
            fwrite(STDOUT, "no match\n");
            $fail++;
            continue;
        }
        list($code, $res) = apiRequest('PATCH', '/compositions.php', $token, $base, array(
            'id' => $id,
            'website' => $page,
        ));
        if(empty($res['ok'])) {
            fwrite(STDOUT, "patch fail ".json_encode($res)."\n");
            $fail++;
            continue;
        }
        fwrite(STDOUT, "ok {$resolved['source']} {$page}\n");
        $ok++;
        usleep(200000);
    }
    fwrite(STDERR, "Done. ok={$ok} fail={$fail} skip={$skip}\n");
}
finally {
    if(is_resource($serverProc)) {
        $status = proc_get_status($serverProc);
        if(!empty($status['pid'])) {
            @exec('kill '.((int)$status['pid']).' 2>/dev/null');
        }
        proc_terminate($serverProc);
        proc_close($serverProc);
    }
}
