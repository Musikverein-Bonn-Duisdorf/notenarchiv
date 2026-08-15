#!/usr/bin/env php
<?php
/**
 * Seed common wind-orchestra composers + works via Archiv JSON API.
 *
 * Env: ARCHIV_API_BASE, ARCHIV_API_TOKEN
 * Or:  php scripts/seedWindCatalogApi.php --local [--login=MVD]
 */
if(PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$root = dirname(__DIR__);
$local = in_array('--local', $argv, true);
$login = 'MVD';
foreach($argv as $arg) {
    if(strpos($arg, '--login=') === 0) {
        $login = substr($arg, 8);
    }
}

$ua = 'NotenarchivWindCatalogSeed/1.0 (local; Wikimedia Commons where noted)';
require_once $root.'/scripts/lib/publisherCoverFetch.php';
require_once $root.'/scripts/lib/composerPortraitFetch.php';

/** @var list<array{first:string,last:string,commons?:string}> */
$composers = array(
    array('first' => 'John Philip', 'last' => 'Sousa', 'commons' => 'John_Philip_Sousa.jpg'),
    array('first' => 'Gustav', 'last' => 'Holst', 'commons' => 'Gustav_Holst.jpg'),
    array('first' => 'Percy', 'last' => 'Grainger', 'commons' => 'Percy_Grainger_1922.jpg'),
    array('first' => 'Ralph', 'last' => 'Vaughan Williams', 'commons' => 'Ralph_Vaughan_Williams.jpg'),
    array('first' => 'Johan', 'last' => 'de Meij'),
    array('first' => 'Philip', 'last' => 'Sparke'),
    array('first' => 'Jacob', 'last' => 'de Haan'),
    array('first' => 'Jan', 'last' => 'Van der Roost'),
    array('first' => 'Bert', 'last' => 'Appermont'),
    array('first' => 'Alfred', 'last' => 'Reed'),
    array('first' => 'Frank', 'last' => 'Ticheli'),
    array('first' => 'James', 'last' => 'Barnes'),
    array('first' => 'David R.', 'last' => 'Holsinger'),
    array('first' => 'Steven', 'last' => 'Reineke'),
    array('first' => 'Eric', 'last' => 'Whitacre'),
    array('first' => 'Otto M.', 'last' => 'Schwarz'),
    array('first' => 'Thomas', 'last' => 'Doss'),
    array('first' => 'Satoshi', 'last' => 'Yagisawa'),
    array('first' => 'Franco', 'last' => 'Cesarini'),
    array('first' => 'Hardy', 'last' => 'Mertens'),
    array('first' => 'Kees', 'last' => 'Vlak'),
    array('first' => 'Henry', 'last' => 'Fillmore', 'commons' => 'Henry_Fillmore.jpg'),
    array('first' => 'Karl L.', 'last' => 'King', 'commons' => 'Karl_L_King.jpg'),
    array('first' => 'Clifton', 'last' => 'Williams'),
    array('first' => 'Robert W.', 'last' => 'Smith'),
    array('first' => 'Julie', 'last' => 'Giroux'),
    array('first' => 'Brian', 'last' => 'Balmages'),
    array('first' => 'John', 'last' => 'Mackey'),
    array('first' => 'Samuel R.', 'last' => 'Hazo'),
    array('first' => 'James', 'last' => 'Curnow'),
);

/**
 * Works: composerKey "First|Last", optional publisher name (exact API match), commons cover file optional.
 * @var array<string, list<array{title:string,year?:int,grade?:float,time?:string,publisher?:string,commons?:string}>>
 */
$works = array(
    'John Philip|Sousa' => array(
        array('title' => 'The Stars and Stripes Forever', 'year' => 1896, 'grade' => 4.0, 'time' => '3:30', 'publisher' => 'C.L. Barnhouse Company'),
        array('title' => 'The Washington Post', 'year' => 1889, 'grade' => 3.0, 'time' => '2:30'),
        array('title' => 'Semper Fidelis', 'year' => 1888, 'grade' => 3.5, 'time' => '2:45'),
    ),
    'Gustav|Holst' => array(
        array('title' => 'First Suite in E-flat for Military Band', 'year' => 1909, 'grade' => 4.0, 'time' => '11:00', 'publisher' => 'Schott Music'),
        array('title' => 'Second Suite in F for Military Band', 'year' => 1911, 'grade' => 4.0, 'time' => '12:00', 'publisher' => 'Schott Music'),
        array('title' => 'Mars, the Bringer of War (from The Planets)', 'year' => 1914, 'grade' => 5.0, 'time' => '7:00'),
    ),
    'Percy|Grainger' => array(
        array('title' => 'Lincolnshire Posy', 'year' => 1937, 'grade' => 5.0, 'time' => '16:00'),
        array('title' => 'Molly on the Shore', 'year' => 1920, 'grade' => 4.0, 'time' => '4:00'),
        array('title' => 'Irish Tune from County Derry', 'year' => 1918, 'grade' => 3.0, 'time' => '4:30'),
    ),
    'Ralph|Vaughan Williams' => array(
        array('title' => 'English Folk Song Suite', 'year' => 1923, 'grade' => 3.5, 'time' => '11:00', 'publisher' => 'Schott Music'),
        array('title' => 'Toccata Marziale', 'year' => 1924, 'grade' => 5.0, 'time' => '6:00'),
    ),
    'Johan|de Meij' => array(
        array('title' => 'Symphony No. 1 „The Lord of the Rings“', 'year' => 1988, 'grade' => 5.5, 'time' => '42:00', 'publisher' => 'Amstel Music'),
        array('title' => 'Extreme Make-over', 'year' => 2005, 'grade' => 5.0, 'time' => '16:00', 'publisher' => 'Amstel Music'),
        array('title' => 'Planet Earth', 'year' => 2006, 'grade' => 5.0, 'time' => '18:00', 'publisher' => 'Amstel Music'),
    ),
    'Philip|Sparke' => array(
        array('title' => 'Music of the Spheres', 'year' => 2004, 'grade' => 5.5, 'time' => '18:00', 'publisher' => 'Anglo Music Press'),
        array('title' => 'Dance Movements', 'year' => 1996, 'grade' => 5.0, 'time' => '16:00', 'publisher' => 'Anglo Music Press'),
        array('title' => 'Orient Express', 'year' => 1992, 'grade' => 4.0, 'time' => '6:00', 'publisher' => 'Anglo Music Press'),
    ),
    'Jacob|de Haan' => array(
        array('title' => 'Oregon', 'year' => 1989, 'grade' => 3.5, 'time' => '9:00', 'publisher' => 'De Haske Publications'),
        array('title' => 'Pacific Dreams', 'year' => 1991, 'grade' => 3.0, 'time' => '8:00', 'publisher' => 'De Haske Publications'),
        array('title' => 'Ammerland', 'year' => 2001, 'grade' => 3.0, 'time' => '6:00', 'publisher' => 'De Haske Publications'),
    ),
    'Jan|Van der Roost' => array(
        array('title' => 'Puszta', 'year' => 1987, 'grade' => 4.0, 'time' => '10:00', 'publisher' => 'De Haske Publications'),
        array('title' => 'Spartacus', 'year' => 1988, 'grade' => 5.0, 'time' => '10:00', 'publisher' => 'De Haske Publications'),
        array('title' => 'Poeme Montagnard', 'year' => 1994, 'grade' => 4.5, 'time' => '11:00', 'publisher' => 'De Haske Publications'),
    ),
    'Bert|Appermont' => array(
        array('title' => 'Imperium', 'year' => 2006, 'grade' => 4.0, 'time' => '8:00', 'publisher' => 'Bernaerts Music'),
        array('title' => 'The Spirit of U.N.C.L.E.', 'year' => 2005, 'grade' => 3.5, 'time' => '7:00'),
        array('title' => 'Noah’s Ark', 'year' => 2003, 'grade' => 3.0, 'time' => '9:00'),
    ),
    'Alfred|Reed' => array(
        array('title' => 'Armenian Dances (Part I)', 'year' => 1972, 'grade' => 5.0, 'time' => '11:00'),
        array('title' => 'Russian Christmas Music', 'year' => 1944, 'grade' => 4.5, 'time' => '10:00'),
        array('title' => 'El Camino Real', 'year' => 1985, 'grade' => 4.0, 'time' => '8:00'),
    ),
    'Frank|Ticheli' => array(
        array('title' => 'Blue Shades', 'year' => 1996, 'grade' => 5.0, 'time' => '10:00'),
        array('title' => 'An American Elegy', 'year' => 2000, 'grade' => 3.5, 'time' => '11:00'),
        array('title' => 'Vesuvius', 'year' => 1999, 'grade' => 4.5, 'time' => '9:00'),
    ),
    'James|Barnes' => array(
        array('title' => 'Fantasy Variations on a Theme by Niccolo Paganini', 'year' => 1988, 'grade' => 5.5, 'time' => '14:00', 'publisher' => 'C.L. Barnhouse Company'),
        array('title' => 'Symphony No. 3 „The Tragic“', 'year' => 1994, 'grade' => 6.0, 'time' => '35:00', 'publisher' => 'C.L. Barnhouse Company'),
    ),
    'David R.|Holsinger' => array(
        array('title' => 'On a Hymnsong of Philip Bliss', 'year' => 1989, 'grade' => 3.0, 'time' => '5:00'),
        array('title' => 'Liturgical Dances', 'year' => 1984, 'grade' => 5.0, 'time' => '10:00'),
    ),
    'Steven|Reineke' => array(
        array('title' => 'Fate of the Gods', 'year' => 2001, 'grade' => 4.0, 'time' => '7:00'),
        array('title' => 'The Witch and the Saint', 'year' => 2004, 'grade' => 4.5, 'time' => '9:00'),
    ),
    'Eric|Whitacre' => array(
        array('title' => 'October', 'year' => 2000, 'grade' => 4.0, 'time' => '7:00'),
        array('title' => 'Lux Aurumque', 'year' => 2000, 'grade' => 3.5, 'time' => '4:00'),
        array('title' => 'Godzilla Eats Las Vegas!', 'year' => 1996, 'grade' => 5.0, 'time' => '12:00'),
    ),
    'Otto M.|Schwarz' => array(
        array('title' => 'Around the World in 80 Days', 'year' => 2004, 'grade' => 4.0, 'time' => '9:00', 'publisher' => 'Musikverlag Rundel'),
        array('title' => 'Symphonic Poem No. 1', 'year' => 1999, 'grade' => 4.5, 'time' => '10:00', 'publisher' => 'Musikverlag Rundel'),
    ),
    'Thomas|Doss' => array(
        array('title' => 'Montana', 'year' => 2007, 'grade' => 3.5, 'time' => '8:00', 'publisher' => 'Musikverlag Rundel'),
        array('title' => 'The Legend of the Ancient Forest', 'year' => 2010, 'grade' => 4.0, 'time' => '9:00', 'publisher' => 'Musikverlag Rundel'),
    ),
    'Satoshi|Yagisawa' => array(
        array('title' => 'And Then the Ocean Glows', 'year' => 2005, 'grade' => 4.5, 'time' => '8:00'),
        array('title' => 'Marimba Concerto', 'year' => 2003, 'grade' => 5.0, 'time' => '14:00'),
    ),
    'Franco|Cesarini' => array(
        array('title' => 'Greek Folk Song Suite', 'year' => 1994, 'grade' => 3.5, 'time' => '9:00', 'publisher' => 'Mitropa Music'),
        array('title' => 'Alpine Suite', 'year' => 1990, 'grade' => 3.0, 'time' => '8:00'),
    ),
    'Hardy|Mertens' => array(
        array('title' => 'Nulli Secundus', 'year' => 1988, 'grade' => 5.0, 'time' => '10:00', 'publisher' => 'Molenaar Edition'),
        array('title' => 'Artemis', 'year' => 1992, 'grade' => 4.5, 'time' => '9:00', 'publisher' => 'Molenaar Edition'),
    ),
    'Kees|Vlak' => array(
        array('title' => 'Concerto for Trombone', 'year' => 1975, 'grade' => 4.5, 'time' => '12:00', 'publisher' => 'Molenaar Edition'),
        array('title' => 'Partita', 'year' => 1968, 'grade' => 4.0, 'time' => '10:00', 'publisher' => 'Molenaar Edition'),
    ),
    'Henry|Fillmore' => array(
        array('title' => 'Americans We', 'year' => 1929, 'grade' => 3.0, 'time' => '2:30'),
        array('title' => 'Military Escort', 'year' => 1921, 'grade' => 2.5, 'time' => '2:15'),
    ),
    'Karl L.|King' => array(
        array('title' => 'Barnum and Bailey’s Favorite', 'year' => 1913, 'grade' => 4.0, 'time' => '3:00'),
        array('title' => 'The Melody Shop', 'year' => 1910, 'grade' => 3.5, 'time' => '2:45'),
    ),
    'Clifton|Williams' => array(
        array('title' => 'Symphonic Suite', 'year' => 1957, 'grade' => 4.5, 'time' => '12:00'),
        array('title' => 'Fanfare and Allegro', 'year' => 1956, 'grade' => 4.0, 'time' => '5:00'),
    ),
    'Robert W.|Smith' => array(
        array('title' => 'The Divine Comedy', 'year' => 1995, 'grade' => 5.0, 'time' => '20:00', 'publisher' => 'C.L. Barnhouse Company'),
        array('title' => 'Encanto', 'year' => 1984, 'grade' => 3.0, 'time' => '6:00'),
    ),
    'Julie|Giroux' => array(
        array('title' => 'One Life Beautiful', 'year' => 2010, 'grade' => 4.0, 'time' => '6:00'),
        array('title' => 'No Man’s Land', 'year' => 2014, 'grade' => 4.5, 'time' => '8:00'),
    ),
    'Brian|Balmages' => array(
        array('title' => 'Arabian Dances', 'year' => 2007, 'grade' => 3.0, 'time' => '5:00'),
        array('title' => 'Elements', 'year' => 2012, 'grade' => 4.0, 'time' => '7:00'),
    ),
    'John|Mackey' => array(
        array('title' => 'Wine-Dark Sea', 'year' => 2014, 'grade' => 5.5, 'time' => '30:00'),
        array('title' => 'Aurora Awakes', 'year' => 2009, 'grade' => 5.0, 'time' => '11:00'),
    ),
    'Samuel R.|Hazo' => array(
        array('title' => 'Ride', 'year' => 2002, 'grade' => 4.0, 'time' => '5:00'),
        array('title' => 'Persian Dance', 'year' => 2007, 'grade' => 3.5, 'time' => '4:00'),
    ),
    'James|Curnow' => array(
        array('title' => 'Foshay Tower Fantasy', 'year' => 1989, 'grade' => 4.0, 'time' => '7:00'),
        array('title' => 'Where Never Lark or Eagle Flew', 'year' => 1993, 'grade' => 4.5, 'time' => '8:00'),
    ),
);

$base = rtrim((string)getenv('ARCHIV_API_BASE'), '/');
$token = (string)getenv('ARCHIV_API_TOKEN');
$serverProc = null;

if($local || $base === '' || $token === '') {
    if(!$local && ($base === '' || $token === '')) {
        fwrite(STDERR, "Set ARCHIV_API_BASE + ARCHIV_API_TOKEN, or pass --local\n");
        exit(1);
    }
    chdir($root);
    $tokCmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/issueApiToken.php').' '.escapeshellarg($login).' wind-catalog';
    $tokOut = array();
    $tokCode = 0;
    exec($tokCmd.' 2>/dev/null', $tokOut, $tokCode);
    $token = isset($tokOut[0]) ? trim($tokOut[0]) : '';
    if($token === '' || $tokCode !== 0) {
        fwrite(STDERR, "Could not issue API token for login {$login}\n");
        exit(1);
    }
    $port = 8767;
    $base = 'http://127.0.0.1:'.$port.'/api';
    $cmd = escapeshellarg(PHP_BINARY).' -S 127.0.0.1:'.$port.' -t '.escapeshellarg($root);
    $descriptors = array(
        0 => array('file', '/dev/null', 'r'),
        1 => array('file', sys_get_temp_dir().'/archiv-api-catalog-seed.log', 'a'),
        2 => array('file', sys_get_temp_dir().'/archiv-api-catalog-seed.log', 'a'),
    );
    $serverProc = proc_open($cmd, $descriptors, $pipes, $root);
    if(!is_resource($serverProc)) {
        fwrite(STDERR, "Failed to start php -S\n");
        exit(1);
    }
    usleep(450000);
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

function commonsThumbFile($fileTitle, $ua, $width = 400) {
    $api = 'https://commons.wikimedia.org/w/api.php?'.http_build_query(array(
        'action' => 'query',
        'titles' => 'File:'.$fileTitle,
        'prop' => 'imageinfo',
        'iiprop' => 'url|mime',
        'iiurlwidth' => (int)$width,
        'format' => 'json',
    ));
    $ch = curl_init($api);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, $ua);
    $raw = curl_exec($ch);
    curl_close($ch);
    if(!is_string($raw) || $raw === '') {
        return null;
    }
    $j = json_decode($raw, true);
    if(!is_array($j)) {
        return null;
    }
    foreach(($j['query']['pages'] ?? array()) as $page) {
        if(isset($page['missing'])) {
            return null;
        }
        $ii = $page['imageinfo'][0] ?? null;
        if(!$ii) {
            return null;
        }
        $url = isset($ii['thumburl']) ? (string)$ii['thumburl'] : (string)$ii['url'];
        $ch2 = curl_init($url);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch2, CURLOPT_USERAGENT, $ua);
        $bin = curl_exec($ch2);
        curl_close($ch2);
        if(!is_string($bin) || strlen($bin) < 80) {
            return null;
        }
        $img = @imagecreatefromstring($bin);
        if(!$img) {
            return null;
        }
        $png = tempnam(sys_get_temp_dir(), 'wavatar').'.png';
        imagepng($img, $png);
        imagedestroy($img);
        return is_file($png) ? $png : null;
    }
    return null;
}

function makeInitialsPng($label) {
    if(!function_exists('imagecreatetruecolor')) {
        return null;
    }
    $im = imagecreatetruecolor(320, 400);
    $h = crc32($label);
    $bg = imagecolorallocate($im, 40 + ($h & 0x3f), 60 + (($h >> 8) & 0x3f), 90 + (($h >> 16) & 0x3f));
    $fg = imagecolorallocate($im, 255, 255, 255);
    imagefilledrectangle($im, 0, 0, 319, 399, $bg);
    $text = mb_strtoupper(mb_substr(preg_replace('/\s+/', '', $label), 0, 2, 'UTF-8'), 'UTF-8');
    if($text === '') {
        $text = '??';
    }
    imagestring($im, 5, 140, 190, $text, $fg);
    $png = tempnam(sys_get_temp_dir(), 'wavatar').'.png';
    imagepng($im, $png);
    imagedestroy($im);
    return $png;
}

function makeCoverPng($title, $subtitle) {
    if(!function_exists('imagecreatetruecolor')) {
        return null;
    }
    $im = imagecreatetruecolor(400, 560);
    $h = crc32($title.'|'.$subtitle);
    $bg = imagecolorallocate($im, 30 + ($h & 0x2f), 45 + (($h >> 7) & 0x3f), 70 + (($h >> 14) & 0x4f));
    $fg = imagecolorallocate($im, 250, 250, 250);
    $muted = imagecolorallocate($im, 210, 220, 230);
    imagefilledrectangle($im, 0, 0, 399, 559, $bg);
    imagefilledrectangle($im, 20, 20, 379, 539, imagecolorallocate($im, 20, 25, 35));
    imagefilledrectangle($im, 28, 28, 371, 531, $bg);
    $lines = array();
    $words = preg_split('/\s+/u', $title) ?: array($title);
    $line = '';
    foreach($words as $w) {
        $try = $line === '' ? $w : $line.' '.$w;
        if(mb_strlen($try, 'UTF-8') > 22) {
            if($line !== '') {
                $lines[] = $line;
            }
            $line = $w;
        } else {
            $line = $try;
        }
    }
    if($line !== '') {
        $lines[] = $line;
    }
    $lines = array_slice($lines, 0, 5);
    $y = 200;
    foreach($lines as $ln) {
        $x = (int)max(40, (400 - strlen($ln) * 9) / 2);
        imagestring($im, 5, $x, $y, $ln, $fg);
        $y += 22;
    }
    if($subtitle !== '') {
        $sx = (int)max(40, (400 - strlen($subtitle) * 8) / 2);
        imagestring($im, 3, $sx, 480, $subtitle, $muted);
    }
    $jpg = tempnam(sys_get_temp_dir(), 'wcover').'.jpg';
    imagejpeg($im, $jpg, 88);
    imagedestroy($im);
    return is_file($jpg) ? $jpg : null;
}

try {
    list($code, $me) = apiRequest('GET', '/me.php', $token, $base);
    if($code !== 200 || empty($me['ok'])) {
        fwrite(STDERR, "Auth failed HTTP {$code}: ".json_encode($me)."\n");
        exit(1);
    }
    fwrite(STDERR, "API OK as ".($me['user']['name'] ?? '?')."\n");

    // Publishers map
    list($code, $pubs) = apiRequest('GET', '/publishers.php?limit=200', $token, $base);
    $pubByName = array();
    foreach(($pubs['publishers'] ?? array()) as $row) {
        $pubByName[mb_strtolower(trim((string)$row['name']), 'UTF-8')] = (int)$row['id'];
    }
    // Mitropa may be missing — ignore silently

    // Existing composers
    list($code, $clist) = apiRequest('GET', '/composers.php?limit=200', $token, $base);
    $composerIds = array();
    foreach(($clist['composers'] ?? array()) as $row) {
        $key = mb_strtolower(trim($row['firstName'].'|'.$row['lastName']), 'UTF-8');
        $composerIds[$key] = (int)$row['id'];
    }

    $createdC = 0;
    $updatedC = 0;
    foreach($composers as $c) {
        $key = mb_strtolower($c['first'].'|'.$c['last'], 'UTF-8');
        $id = isset($composerIds[$key]) ? $composerIds[$key] : 0;
        if($id < 1) {
            list($code, $res) = apiRequest('POST', '/composers.php', $token, $base, array(
                'firstName' => $c['first'],
                'lastName' => $c['last'],
            ));
            if($code !== 200 || empty($res['id'])) {
                fwrite(STDERR, "Composer create fail {$c['first']} {$c['last']}: ".json_encode($res)."\n");
                continue;
            }
            $id = (int)$res['id'];
            $composerIds[$key] = $id;
            $createdC++;
            fwrite(STDOUT, "composer+ {$id} {$c['first']} {$c['last']}\n");
        } else {
            $updatedC++;
            fwrite(STDOUT, "composer= {$id} {$c['first']} {$c['last']}\n");
        }

        $avatar = null;
        $avatarSrc = '';
        $resolved = archivResolveComposerPortrait($c['first'], $c['last']);
        if($resolved && !empty($resolved['url'])) {
            $avatar = archivDownloadPortraitFile($resolved['url']);
            $avatarSrc = $resolved['source'];
        }
        if(!$avatar && !empty($c['commons'])) {
            $avatar = commonsThumbFile($c['commons'], $ua, 400);
            $avatarSrc = 'commons';
        }
        if(!$avatar) {
            $avatar = makeInitialsPng($c['first'].$c['last']);
            $avatarSrc = 'generated';
        }
        if($avatar) {
            $mime = (substr($avatar, -4) === '.png') ? 'image/png' : 'image/jpeg';
            $name = (substr($avatar, -4) === '.png') ? 'avatar.png' : 'avatar.jpg';
            $cfile = new CURLFile($avatar, $mime, $name);
            list($code, $res) = apiRequest('POST', '/composers/avatar.php', $token, $base, null, array(
                'id' => (string)$id,
                'avatar' => $cfile,
            ));
            @unlink($avatar);
            fwrite(STDOUT, empty($res['ok']) ? "  avatar fail\n" : "  avatar ok ({$avatarSrc})\n");
        }
    }

    // Existing compositions by title (lowercase)
    list($code, $plist) = apiRequest('GET', '/compositions.php?limit=200', $token, $base);
    $pieceByTitle = array();
    foreach(($plist['compositions'] ?? array()) as $row) {
        $pieceByTitle[mb_strtolower(trim((string)$row['title']), 'UTF-8')] = (int)$row['id'];
    }

    $createdP = 0;
    $skippedP = 0;
    foreach($works as $ckey => $list) {
        $composerId = isset($composerIds[mb_strtolower($ckey, 'UTF-8')])
            ? $composerIds[mb_strtolower($ckey, 'UTF-8')]
            : 0;
        if($composerId < 1) {
            fwrite(STDERR, "Missing composer for works: {$ckey}\n");
            continue;
        }
        $parts = explode('|', $ckey, 2);
        $composerLabel = trim(($parts[1] ?? '').'');
        foreach($list as $work) {
            $tkey = mb_strtolower(trim($work['title']), 'UTF-8');
            if(isset($pieceByTitle[$tkey])) {
                $skippedP++;
                fwrite(STDOUT, "piece= {$pieceByTitle[$tkey]} {$work['title']}\n");
                $pieceId = $pieceByTitle[$tkey];
            } else {
                $payload = array(
                    'title' => $work['title'],
                    'composerId' => $composerId,
                    'year' => isset($work['year']) ? $work['year'] : null,
                    'grade' => isset($work['grade']) ? $work['grade'] : null,
                    'performanceTime' => isset($work['time']) ? $work['time'] : '',
                );
                if(!empty($work['publisher'])) {
                    $pk = mb_strtolower($work['publisher'], 'UTF-8');
                    if(isset($pubByName[$pk])) {
                        $payload['publisherId'] = $pubByName[$pk];
                    }
                }
                list($code, $res) = apiRequest('POST', '/compositions.php', $token, $base, $payload);
                if($code !== 200 || empty($res['id'])) {
                    fwrite(STDERR, "Piece create fail {$work['title']}: ".json_encode($res)."\n");
                    continue;
                }
                $pieceId = (int)$res['id'];
                $pieceByTitle[$tkey] = $pieceId;
                $createdP++;
                fwrite(STDOUT, "piece+ {$pieceId} {$work['title']}\n");
            }

            $cover = null;
            $coverSrc = '';
            $pubWebsite = '';
            if(!empty($work['publisher'])) {
                $pk = mb_strtolower($work['publisher'], 'UTF-8');
                // Website comes from publisher seed; resolve via API row if present
                foreach(($pubs['publishers'] ?? array()) as $prow) {
                    if(mb_strtolower(trim((string)$prow['name']), 'UTF-8') === $pk) {
                        $pubWebsite = isset($prow['website']) ? (string)$prow['website'] : '';
                        break;
                    }
                }
            }
            $resolved = archivResolvePublisherCover($work['title'], $pubWebsite);
            if($resolved && !empty($resolved['url'])) {
                $cover = archivDownloadCoverFile($resolved['url']);
                $coverSrc = $resolved['source'].' score='.(int)$resolved['score'];
            }
            if($resolved && !empty($resolved['page'])) {
                list($code, $res) = apiRequest('PATCH', '/compositions.php', $token, $base, array(
                    'id' => $pieceId,
                    'website' => $resolved['page'],
                ));
                fwrite(STDOUT, empty($res['ok']) ? "  website fail\n" : "  website ok\n");
            }
            if(!$cover && !empty($work['commons'])) {
                $cover = commonsThumbFile($work['commons'], $ua, 500);
                $coverSrc = 'commons';
            }
            if(!$cover) {
                $cover = makeCoverPng($work['title'], $composerLabel);
                $coverSrc = 'generated';
            }
            if($cover) {
                $mime = (substr($cover, -4) === '.png') ? 'image/png' : 'image/jpeg';
                $name = (substr($cover, -4) === '.png') ? 'cover.png' : 'cover.jpg';
                $cfile = new CURLFile($cover, $mime, $name);
                list($code, $res) = apiRequest('POST', '/compositions/cover.php', $token, $base, null, array(
                    'id' => (string)$pieceId,
                    'cover' => $cfile,
                ));
                @unlink($cover);
                fwrite(STDOUT, empty($res['ok'])
                    ? "  cover fail ".json_encode($res)."\n"
                    : "  cover ok ({$coverSrc})\n");
            }
        }
    }

    fwrite(STDERR, "Done. composers created={$createdC} known={$updatedC}; pieces created={$createdP} existing={$skippedP}\n");
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
?>
