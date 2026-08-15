#!/usr/bin/env php
<?php
/**
 * Seed common wind-orchestra publishers via Archiv JSON API (ARCHIV-31).
 *
 * Env:
 *   ARCHIV_API_BASE   e.g. http://127.0.0.1:8765/api
 *   ARCHIV_API_TOKEN  Bearer token (admin)
 *
 * Or: php scripts/seedWindPublishersApi.php --local
 *   → issues token for login MVD (override --login=…) and uses php built-in server briefly.
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

$publishers = array(
    array('name' => 'Musikverlag Rundel', 'website' => 'https://www.rundel.de', 'domain' => 'rundel.de'),
    array('name' => 'De Haske Publications', 'website' => 'https://www.dehaske.com', 'domain' => 'dehaske.com'),
    array('name' => 'Molenaar Edition', 'website' => 'https://www.molenaar.com', 'domain' => 'molenaar.com'),
    array('name' => 'Tierolff Muziekcentrale', 'website' => 'https://www.tierolff.nl', 'domain' => 'tierolff.nl'),
    array('name' => 'Hafabra Music', 'website' => 'https://www.hafabramusic.com', 'domain' => 'hafabramusic.com'),
    array('name' => 'Obrasso-Verlag', 'website' => 'https://www.obrasso.com', 'domain' => 'obrasso.com'),
    array('name' => 'HeBu Musikverlag', 'website' => 'https://www.hebu-music.com', 'domain' => 'hebu-music.com'),
    array('name' => 'Musikverlag Bruno Uetz', 'website' => 'https://www.uetz.de', 'domain' => 'uetz.de'),
    array('name' => 'Baton Music', 'website' => 'https://www.batonmusic.nl', 'domain' => 'batonmusic.nl'),
    array('name' => 'Amstel Music', 'website' => 'https://www.amstelmusic.nl', 'domain' => 'amstelmusic.nl'),
    array('name' => 'C.L. Barnhouse Company', 'website' => 'https://www.barnhouse.com', 'domain' => 'barnhouse.com'),
    array('name' => 'Kliment Musikverlag', 'website' => 'https://www.kliment.at', 'domain' => 'kliment.at'),
    array('name' => 'Musikverlag Wilhelm Halter', 'website' => 'https://www.halter.de', 'domain' => 'halter.de'),
    array('name' => 'Editions Marc Reift', 'website' => 'https://www.reift.ch', 'domain' => 'reift.ch'),
    array('name' => 'Bernaerts Music', 'website' => 'https://www.bernaertsmusic.com', 'domain' => 'bernaertsmusic.com'),
    array('name' => 'Golden River Music', 'website' => 'https://www.goldenrivermusic.eu', 'domain' => 'goldenrivermusic.eu'),
    array('name' => 'Stormworks Europe', 'website' => 'https://www.stormworks.nl', 'domain' => 'stormworks.nl'),
    array('name' => 'Schott Music', 'website' => 'https://www.schott-music.com', 'domain' => 'schott-music.com'),
    array('name' => 'Bärenreiter', 'website' => 'https://www.baerenreiter.com', 'domain' => 'baerenreiter.com'),
    array('name' => 'Anglo Music Press', 'website' => 'https://www.anglo-music.com', 'domain' => 'anglo-music.com'),
    array('name' => 'Mitropa Music', 'website' => 'https://www.mitropamusic.com', 'domain' => 'mitropamusic.com'),
);

$base = rtrim((string)getenv('ARCHIV_API_BASE'), '/');
$token = (string)getenv('ARCHIV_API_TOKEN');
$serverProc = null;
$serverPid = null;

if($local || $base === '' || $token === '') {
    if(!$local && ($base === '' || $token === '')) {
        fwrite(STDERR, "Set ARCHIV_API_BASE + ARCHIV_API_TOKEN, or pass --local\n");
        exit(1);
    }
    chdir($root);
    // Issue token via CLI
    $tokCmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/issueApiToken.php').' '.escapeshellarg($login).' wind-seed';
    $tokOut = array();
    $tokCode = 0;
    exec($tokCmd.' 2>/dev/null', $tokOut, $tokCode);
    $token = isset($tokOut[0]) ? trim($tokOut[0]) : '';
    if($token === '' || $tokCode !== 0) {
        fwrite(STDERR, "Could not issue API token for login {$login}\n");
        exit(1);
    }
    $port = 8765;
    $base = 'http://127.0.0.1:'.$port.'/api';
    $router = $root.'/scripts/_devApiRouter.php';
    if(!is_file($router)) {
        file_put_contents($router, "<?php\n\$path = parse_url(\$_SERVER['REQUEST_URI'], PHP_URL_PATH);\n\$file = __DIR__.'/..'.\$path;\nif(\$path !== '/' && is_file(\$file)) { return false; }\nhttp_response_code(404);\necho 'Not found';\n");
    }
    $cmd = escapeshellarg(PHP_BINARY).' -S 127.0.0.1:'.$port.' -t '.escapeshellarg($root);
    $descriptors = array(
        0 => array('file', '/dev/null', 'r'),
        1 => array('file', sys_get_temp_dir().'/archiv-api-seed.log', 'a'),
        2 => array('file', sys_get_temp_dir().'/archiv-api-seed.log', 'a'),
    );
    $serverProc = proc_open($cmd, $descriptors, $pipes, $root);
    if(!is_resource($serverProc)) {
        fwrite(STDERR, "Failed to start php -S\n");
        exit(1);
    }
    $status = proc_get_status($serverProc);
    $serverPid = isset($status['pid']) ? (int)$status['pid'] : null;
    usleep(400000);
    putenv('ARCHIV_API_BASE='.$base);
    putenv('ARCHIV_API_TOKEN='.$token);
}

function apiRequest($method, $path, $token, $base, $json = null, $multipart = null) {
    $url = $base.$path;
    $ch = curl_init($url);
    $headers = array('Authorization: Bearer '.$token);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    if($multipart !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart);
    } elseif($json !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if($body === false) {
        throw new RuntimeException('curl error: '.$err);
    }
    $data = json_decode($body, true);
    return array($code, is_array($data) ? $data : array('raw' => $body));
}

function fetchLogoPng($domain) {
    $hit = archivResolvePublisherLogo('https://'.$domain);
    if($hit && !empty($hit['path'])) {
        return $hit['path'];
    }
    // Fallback: generate simple branded PNG
    if(!function_exists('imagecreatetruecolor')) {
        return null;
    }
    $im = imagecreatetruecolor(128, 160);
    $bg = imagecolorallocate($im, 52, 90, 149);
    $fg = imagecolorallocate($im, 255, 255, 255);
    imagefilledrectangle($im, 0, 0, 127, 159, $bg);
    $label = strtoupper(substr($domain, 0, 2));
    imagestring($im, 5, 48, 72, $label, $fg);
    $png = tempnam(sys_get_temp_dir(), 'publogo').'.png';
    imagepng($im, $png);
    imagedestroy($im);
    return $png;
}

try {
    list($code, $me) = apiRequest('GET', '/me.php', $token, $base);
    if($code !== 200 || empty($me['ok'])) {
        fwrite(STDERR, "Auth failed (HTTP {$code}): ".json_encode($me)."\n");
        exit(1);
    }
    fwrite(STDERR, "API OK as ".($me['user']['name'] ?? '?')."\n");

    list($code, $existing) = apiRequest('GET', '/publishers.php?limit=200', $token, $base);
    if($code !== 200) {
        fwrite(STDERR, "List publishers failed: ".json_encode($existing)."\n");
        exit(1);
    }
    $byName = array();
    foreach(isset($existing['publishers']) ? $existing['publishers'] : array() as $row) {
        $byName[mb_strtolower(trim((string)$row['name']), 'UTF-8')] = (int)$row['id'];
    }

    $created = 0;
    $updated = 0;
    foreach($publishers as $pub) {
        $key = mb_strtolower($pub['name'], 'UTF-8');
        $id = isset($byName[$key]) ? $byName[$key] : 0;
        if($id < 1) {
            list($code, $res) = apiRequest('POST', '/publishers.php', $token, $base, array(
                'name' => $pub['name'],
                'website' => $pub['website'],
            ));
            if($code !== 200 || empty($res['id'])) {
                fwrite(STDERR, "Create failed {$pub['name']}: ".json_encode($res)."\n");
                continue;
            }
            $id = (int)$res['id'];
            $created++;
            fwrite(STDOUT, "created {$id} {$pub['name']}\n");
        } else {
            list($code, $res) = apiRequest('PATCH', '/publishers.php', $token, $base, array(
                'id' => $id,
                'website' => $pub['website'],
            ));
            if($code !== 200) {
                fwrite(STDERR, "Patch failed {$pub['name']}: ".json_encode($res)."\n");
                continue;
            }
            $updated++;
            fwrite(STDOUT, "updated {$id} {$pub['name']}\n");
        }

        $logo = fetchLogoPng($pub['domain']);
        if($logo && is_file($logo)) {
            $cfile = class_exists('CURLFile') ? new CURLFile($logo, 'image/png', 'avatar.png') : '@'.$logo;
            list($code, $res) = apiRequest('POST', '/publishers/avatar.php', $token, $base, null, array(
                'id' => (string)$id,
                'avatar' => $cfile,
            ));
            @unlink($logo);
            if($code !== 200 || empty($res['ok'])) {
                fwrite(STDERR, "  avatar fail: ".json_encode($res)."\n");
            } else {
                fwrite(STDOUT, "  avatar ok\n");
            }
        } else {
            fwrite(STDERR, "  no logo for {$pub['domain']}\n");
        }
    }
    fwrite(STDERR, "Done. created={$created} updated={$updated}\n");
}
finally {
    if(is_resource($serverProc)) {
        $status = proc_get_status($serverProc);
        if(!empty($status['pid'])) {
            // Kill process group of php -S
            @exec('kill '.((int)$status['pid']).' 2>/dev/null');
        }
        proc_terminate($serverProc);
        proc_close($serverProc);
    }
}
?>
