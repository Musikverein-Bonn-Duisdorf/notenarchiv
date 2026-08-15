<?php
/**
 * Local-only: seed well-known composers + Wikimedia Commons portraits as avatars.
 * Usage (from repo root): php scripts/seedFamousComposers.php
 */
$root = dirname(__DIR__);
require_once $root.'/libs/sessionBootstrap.php';
archivConfigureSession();
include $root.'/common/include.php';
mysqli_select_db($GLOBALS['conn'], $sql['database']) or die(mysqli_error($GLOBALS['conn']));

$ua = 'NotenarchivLocalSeed/1.0 (https://github.com/Musikverein-Bonn-Duisdorf/notenarchiv; local-dev avatar seed)';

/** @var array<int, array{first:string,last:string,file:string}> */
$composers = array(
    array('first' => 'Ludwig', 'last' => 'van Beethoven', 'file' => 'Beethoven.jpg'),
    array('first' => 'Wolfgang Amadeus', 'last' => 'Mozart', 'file' => 'Wolfgang-amadeus-mozart_1.jpg'),
    array('first' => 'Johann Sebastian', 'last' => 'Bach', 'file' => 'Johann_Sebastian_Bach.jpg'),
    array('first' => 'Franz', 'last' => 'Schubert', 'file' => 'Franz_Schubert_by_Wilhelm_August_Rieder_1875.jpg'),
    array('first' => 'Johannes', 'last' => 'Brahms', 'file' => 'JohannesBrahms.jpg'),
    array('first' => 'Richard', 'last' => 'Wagner', 'file' => 'RichardWagner.jpg'),
    array('first' => 'Giuseppe', 'last' => 'Verdi', 'file' => 'Verdi.jpg'),
    array('first' => 'Antonín', 'last' => 'Dvořák', 'file' => 'Antonín Dvořák, portrait.jpg'),
    array('first' => 'Carl Maria', 'last' => 'von Weber', 'file' => 'Carl_Maria_von_Weber.png'),
    array('first' => 'Felix', 'last' => 'Mendelssohn Bartholdy', 'file' => 'Felix Mendelssohn Bartholdy by Eduard Magnus (1833).jpg'),
    array('first' => 'Robert', 'last' => 'Schumann', 'file' => 'Robert_Schumann.jpg'),
    array('first' => 'Franz', 'last' => 'Liszt', 'file' => 'Franz_Liszt_1858.jpg'),
    array('first' => 'Peter', 'last' => 'Tschaikowsky', 'file' => 'Pyotr Tchaikovsky с. 1870.jpg'),
    array('first' => 'John Philip', 'last' => 'Sousa', 'file' => 'John_Philip_Sousa.jpg'),
    array('first' => 'Paul', 'last' => 'Hindemith', 'file' => 'Paul_Hindemith_1923.jpg'),
    array('first' => 'Carl', 'last' => 'Orff', 'file' => 'Carl_Orff.jpg'),
    array('first' => 'Richard', 'last' => 'Strauss', 'file' => 'Richard_Strauss.jpg'),
    array('first' => 'Georg Friedrich', 'last' => 'Händel', 'file' => 'Georg_Friedrich_Händel.jpg'),
);

function commonsThumbUrl($fileTitle, $width, $ua) {
    $api = 'https://commons.wikimedia.org/w/api.php?'.http_build_query(array(
        'action' => 'query',
        'titles' => 'File:'.$fileTitle,
        'prop' => 'imageinfo',
        'iiprop' => 'url|mime',
        'iiurlwidth' => (int)$width,
        'format' => 'json',
    ));
    $ctx = stream_context_create(array(
        'http' => array(
            'header' => "User-Agent: {$ua}\r\n",
            'timeout' => 30,
        ),
    ));
    $raw = @file_get_contents($api, false, $ctx);
    if($raw === false) {
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
        $mime = isset($ii['mime']) ? (string)$ii['mime'] : 'image/jpeg';
        return array('url' => $url, 'mime' => $mime);
    }
    return null;
}

function downloadTo($url, $dest, $ua) {
    $ctx = stream_context_create(array(
        'http' => array(
            'header' => "User-Agent: {$ua}\r\n",
            'timeout' => 60,
            'follow_location' => 1,
        ),
    ));
    $data = @file_get_contents($url, false, $ctx);
    if($data === false || $data === '') {
        return false;
    }
    return file_put_contents($dest, $data) !== false;
}

function findComposerId($first, $last) {
    $sql = sprintf(
        'SELECT `Index` FROM `%sComposer` WHERE `FirstName` = "%s" AND `LastName` = "%s" LIMIT 1;',
        $GLOBALS['dbprefix'],
        mysqli_real_escape_string($GLOBALS['conn'], $first),
        mysqli_real_escape_string($GLOBALS['conn'], $last)
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
    return $row ? (int)$row['Index'] : 0;
}

function extForMime($mime) {
    if(strpos($mime, 'png') !== false) {
        return 'png';
    }
    if(strpos($mime, 'webp') !== false) {
        return 'webp';
    }
    if(strpos($mime, 'gif') !== false) {
        return 'gif';
    }
    return 'jpg';
}

$ok = 0;
$fail = 0;
foreach($composers as $row) {
    $first = $row['first'];
    $last = $row['last'];
    $label = trim($first.' '.$last);
    $id = findComposerId($first, $last);
    if($id < 1) {
        $c = new Composer;
        $c->FirstName = $first;
        $c->LastName = $last;
        $c->save();
        $id = (int)$c->Index;
        if($id < 1) {
            fwrite(STDERR, "FAIL save: {$label}\n");
            $fail++;
            continue;
        }
        echo "created #{$id} {$label}\n";
    }
    else {
        echo "exists  #{$id} {$label}\n";
    }

    $info = commonsThumbUrl($row['file'], 400, $ua);
    if(!$info) {
        fwrite(STDERR, "  FAIL resolve commons: {$row['file']}\n");
        $fail++;
        continue;
    }
    $ext = extForMime($info['mime']);
    if(!archivEntityAvatarEnsureDir('Composers', $id)) {
        fwrite(STDERR, "  FAIL mkdir for #{$id}\n");
        $fail++;
        continue;
    }
    archivEntityAvatarDelete('Composers', $id, false);
    $dest = archivEntityAvatarFsDir('Composers', $id).'avatar.'.$ext;
    if(!downloadTo($info['url'], $dest, $ua)) {
        fwrite(STDERR, "  FAIL download: {$info['url']}\n");
        $fail++;
        continue;
    }
    $bytes = filesize($dest);
    echo "  avatar {$ext} {$bytes} B ← {$row['file']}\n";
    $ok++;
}

echo "\nDone: {$ok} avatars, {$fail} failures.\n";
