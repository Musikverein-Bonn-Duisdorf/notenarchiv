#!/usr/bin/env php
<?php
/**
 * ARCHIV-29: Delete compositions via Archiv JSON API.
 *
 * Usage:
 *   ARCHIV_API_BASE=… ARCHIV_API_TOKEN=… php scripts/archiv29DeleteCompositionsApi.php --dry-run
 *   ARCHIV_API_BASE=… ARCHIV_API_TOKEN=… php scripts/archiv29DeleteCompositionsApi.php --apply --run-note='ARCHIV-29'
 */
if(PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$targets = array(
    array('registrationNumber' => 141, 'title' => 'Blumengeflüster'),
    array('registrationNumber' => 183, 'title' => 'Artisten-Show'),
    array('registrationNumber' => 184, 'title' => 'Register-Show'),
    array('registrationNumber' => 191, 'title' => 'Der kleine Tambour'),
    array('registrationNumber' => 202, 'title' => 'Rokoko Suite'),
    array('registrationNumber' => 206, 'title' => 'Prinz Eugen'),
    array('registrationNumber' => 229, 'title' => 'ABBA - Hits for Brass'),
);

$apply = in_array('--apply', $argv, true);
$dryRun = !$apply;
$runNote = 'ARCHIV-29';
foreach($argv as $arg) {
    if(strpos($arg, '--run-note=') === 0) {
        $runNote = substr($arg, 11);
    }
}

$base = rtrim((string)getenv('ARCHIV_API_BASE'), '/');
$token = (string)getenv('ARCHIV_API_TOKEN');
if($base === '' || $token === '') {
    fwrite(STDERR, "Set ARCHIV_API_BASE + ARCHIV_API_TOKEN\n");
    exit(1);
}

function apiRequest($method, $path, $token, $base, $json = null, $runNote = '') {
    $url = $base.$path;
    $ch = curl_init($url);
    $headers = array(
        'Authorization: Bearer '.$token,
        'X-Archiv-Token: '.$token,
    );
    if($runNote !== '') {
        $headers[] = 'X-Archiv-Run-Note: '.$runNote;
    }
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
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

$byReg = array();
list($code, $res) = apiRequest('GET', '/compositions.php?limit=200', $token, $base);
if($code === 200) {
    foreach(($res['compositions'] ?? array()) as $row) {
        $reg = isset($row['registrationNumber']) ? (int)$row['registrationNumber'] : 0;
        if($reg > 0) {
            $byReg[$reg] = $row;
        }
    }
}
foreach(range('A', 'Z') as $letter) {
    list($code, $res) = apiRequest('GET', '/compositions.php?q='.rawurlencode($letter).'&limit=200', $token, $base);
    if($code !== 200) {
        continue;
    }
    foreach(($res['compositions'] ?? array()) as $row) {
        $reg = isset($row['registrationNumber']) ? (int)$row['registrationNumber'] : 0;
        if($reg > 0) {
            $byReg[$reg] = $row;
        }
    }
}

$ok = $fail = $skip = 0;
foreach($targets as $target) {
    $reg = (int)$target['registrationNumber'];
    if(!isset($byReg[$reg])) {
        fwrite(STDERR, "  SKIP not found Inv.{$reg} {$target['title']}\n");
        $skip++;
        continue;
    }
    $row = $byReg[$reg];
    $id = (int)$row['id'];
    $title = (string)($row['title'] ?? '');
    if($dryRun) {
        fwrite(STDOUT, "  [dry-run] DELETE #{$id} Inv.{$reg} {$title}\n");
        $ok++;
        continue;
    }
    list($code, $res) = apiRequest('DELETE', '/compositions.php?id='.$id, $token, $base, null, $runNote);
    if($code === 200 && !empty($res['ok'])) {
        fwrite(STDOUT, "  OK deleted #{$id} Inv.{$reg} {$title}\n");
        $ok++;
    } else {
        fwrite(STDERR, "  FAIL #{$id} Inv.{$reg}: HTTP {$code} ".json_encode($res)."\n");
        $fail++;
    }
}

fwrite(STDOUT, "Done: {$ok} ok, {$skip} skip, {$fail} fail".($dryRun ? ' (dry-run)' : '')."\n");
exit($fail > 0 ? 1 : 0);
