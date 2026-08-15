#!/usr/bin/env php
<?php
/**
 * Refresh publisher avatars from website logos / icons.
 *
 * Env: ARCHIV_API_BASE, ARCHIV_API_TOKEN
 * Or:  php scripts/refreshPublisherLogosApi.php --local [--login=MVD]
 */
if(PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root.'/scripts/lib/publisherLogoFetch.php';

$local = in_array('--local', $argv, true);
$login = 'MVD';
foreach($argv as $arg) {
    if(strpos($arg, '--login=') === 0) {
        $login = substr($arg, 8);
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
    $tokCmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/issueApiToken.php').' '.escapeshellarg($login).' logo-refresh';
    $tokOut = array();
    $tokCode = 0;
    exec($tokCmd.' 2>/dev/null', $tokOut, $tokCode);
    $token = isset($tokOut[0]) ? trim($tokOut[0]) : '';
    if($token === '' || $tokCode !== 0) {
        fwrite(STDERR, "Could not issue API token for login {$login}\n");
        exit(1);
    }
    $port = 8769;
    $base = 'http://127.0.0.1:'.$port.'/api';
    $cmd = escapeshellarg(PHP_BINARY).' -S 127.0.0.1:'.$port.' -t '.escapeshellarg($root);
    $descriptors = array(
        0 => array('file', '/dev/null', 'r'),
        1 => array('file', sys_get_temp_dir().'/archiv-logo-refresh.log', 'a'),
        2 => array('file', sys_get_temp_dir().'/archiv-logo-refresh.log', 'a'),
    );
    $serverProc = proc_open($cmd, $descriptors, $pipes, $root);
    if(!is_resource($serverProc)) {
        fwrite(STDERR, "Failed to start php -S\n");
        exit(1);
    }
    usleep(400000);
}

function apiRequest($method, $path, $token, $base, $json = null, $multipart = null) {
    $url = $base.$path;
    $ch = curl_init($url);
    $headers = array('Authorization: Bearer '.$token);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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

try {
    list($code, $me) = apiRequest('GET', '/me.php', $token, $base);
    if($code !== 200 || empty($me['ok'])) {
        fwrite(STDERR, "Auth failed HTTP {$code}: ".json_encode($me)."\n");
        exit(1);
    }

    list($code, $plist) = apiRequest('GET', '/publishers.php?limit=200', $token, $base);
    if($code !== 200 || empty($plist['ok'])) {
        fwrite(STDERR, "List publishers failed: ".json_encode($plist)."\n");
        exit(1);
    }

    $ok = 0;
    $fail = 0;
    $skip = 0;
    foreach(($plist['publishers'] ?? array()) as $row) {
        $id = (int)$row['id'];
        $name = (string)$row['name'];
        $website = isset($row['website']) ? trim((string)$row['website']) : '';
        if($website === '') {
            fwrite(STDOUT, "{$id} {$name} … skip (no website)\n");
            $skip++;
            continue;
        }
        fwrite(STDOUT, "{$id} {$name} … ");
        $resolved = archivResolvePublisherLogo($website);
        if(!$resolved || empty($resolved['path'])) {
            fwrite(STDOUT, "no logo\n");
            $fail++;
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
            $fail++;
            continue;
        }
        fwrite(STDOUT, "ok {$resolved['source']}\n");
        $ok++;
        usleep(150000);
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
