<?php
/**
 * Seed local Notenarchiv catalog with fictional wind-orchestra repertoire.
 *
 * Usage:
 *   php scripts/seedTestCatalog.php
 *   php scripts/seedTestCatalog.php --force   # wipe archiv catalog tables first
 */
if(php_sapi_name() !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$root = dirname(__DIR__);
chdir($root);

$configFile = $root.'/common/config.php';
if(!is_readable($configFile)) {
    fwrite(STDERR, "Missing common/config.php\n");
    exit(1);
}

require_once $configFile;
require_once $root.'/libs/helpers.php';
require_once $root.'/libs/log.php';
require_once $root.'/libs/user.php';
require_once $root.'/libs/Composer.php';
require_once $root.'/libs/Publisher.php';
require_once $root.'/libs/Collections.php';
require_once $root.'/libs/Collection.php';
require_once $root.'/libs/Composition.php';
require_once $root.'/libs/ScoreFile.php';

if(!isset($GLOBALS['conn']) || !$GLOBALS['conn']) {
    fwrite(STDERR, "No DB connection.\n");
    exit(1);
}

// Composition::makeFilePath() expects data/Compositions/{id}/
if(!is_dir($root.'/data/Compositions')) {
    if(!mkdir($root.'/data/Compositions', 0775, true) && !is_dir($root.'/data/Compositions')) {
        fwrite(STDERR, "Cannot create data/Compositions\n");
        exit(1);
    }
    echo "DIR\tdata/Compositions\n";
}

if(!isset($_SESSION) || !is_array($_SESSION)) {
    $_SESSION = array();
}
$_SESSION['userid'] = 0;

$force = in_array('--force', $argv, true);
$prefix = $GLOBALS['dbprefix'];
$conn = $GLOBALS['conn'];

$countSql = sprintf('SELECT COUNT(*) AS c FROM `%sComposition`;', $prefix);
$dbr = mysqli_query($conn, $countSql);
$row = $dbr ? mysqli_fetch_assoc($dbr) : null;
$existing = $row ? (int)$row['c'] : 0;
if($existing > 0 && !$force) {
    fwrite(STDERR, "Catalog already has {$existing} composition(s). Use --force to wipe and reseed.\n");
    exit(1);
}

if($force) {
    $tables = array('ScoreFile', 'CollectionItem', 'Composition', 'Collection', 'Composer', 'Publisher');
    foreach($tables as $t) {
        $sql = sprintf('DELETE FROM `%s%s`;', $prefix, $t);
        if(!mysqli_query($conn, $sql)) {
            fwrite(STDERR, "DELETE {$t}: ".mysqli_error($conn)."\n");
            exit(1);
        }
        echo "WIPED\t{$prefix}{$t}\n";
    }
}

function seedComposer($first, $last) {
    $c = new Composer;
    $c->FirstName = $first;
    $c->LastName = $last;
    if($c->save() === false && !$c->Index) {
        throw new RuntimeException('Composer save failed: '.$first.' '.$last);
    }
    echo "COMPOSER\t{$c->Index}\t{$first} {$last}\n";
    return (int)$c->Index;
}

function seedPublisher($name, $address = '') {
    $p = new Publisher;
    $p->Name = $name;
    if($address !== '') {
        $p->Address = $address;
    }
    if($p->save() === false && !$p->Index) {
        throw new RuntimeException('Publisher save failed: '.$name);
    }
    echo "PUBLISHER\t{$p->Index}\t{$name}\n";
    return (int)$p->Index;
}

function seedCollection($name) {
    $col = new Collections;
    $col->Name = $name;
    if($col->save() === false && !$col->Index) {
        throw new RuntimeException('Collection save failed: '.$name);
    }
    echo "COLLECTION\t{$col->Index}\t{$name}\n";
    return (int)$col->Index;
}

function seedComposition($data) {
    $piece = new Composition;
    $piece->RegistrationNumber = (int)$data['reg'];
    $piece->Title = $data['title'];
    $piece->Composer = (int)$data['composer'];
    if(!empty($data['arranger'])) {
        $piece->Arranger = (int)$data['arranger'];
    }
    if(!empty($data['publisher'])) {
        $piece->Publisher = (int)$data['publisher'];
    }
    if(isset($data['year'])) {
        $piece->Year = (int)$data['year'];
    }
    if(isset($data['grade'])) {
        $piece->Grade = (double)$data['grade'];
    }
    if(!empty($data['time'])) {
        $piece->PerformanceTime = $data['time'];
    }
    $piece->save();
    if(!$piece->Index) {
        throw new RuntimeException('Composition save failed: '.$data['title']);
    }
    echo "COMPOSITION\t{$piece->Index}\t#{$data['reg']}\t{$data['title']}\n";
    return (int)$piece->Index;
}

function seedCollectionItem($collectionId, $compositionId, $number) {
    $item = new Collection;
    $item->Collections = (int)$collectionId;
    $item->Composition = (int)$compositionId;
    $item->CollectionNumber = (int)$number;
    $item->save();
    if(!$item->Index) {
        throw new RuntimeException('CollectionItem save failed');
    }
    echo "MAP\tcollection {$collectionId} #{$number} → composition {$compositionId}\n";
}

/** Typical Blasorchester-Stimmen (Instrument-ID aus meldeliste_Instrument). */
function standardWindParts() {
    return array(
        array('instrument' => 2, 'voice' => '1'),   // Piccolo
        array('instrument' => 1, 'voice' => '1'),   // Flöte
        array('instrument' => 1, 'voice' => '2'),
        array('instrument' => 23, 'voice' => '1'),  // Oboe
        array('instrument' => 4, 'voice' => '1'),   // Es-Klarinette
        array('instrument' => 3, 'voice' => '1'),   // B-Klarinette
        array('instrument' => 3, 'voice' => '2'),
        array('instrument' => 3, 'voice' => '3'),
        array('instrument' => 15, 'voice' => '1'),  // Bass-Klarinette
        array('instrument' => 5, 'voice' => '1'),   // Alt-Sax
        array('instrument' => 6, 'voice' => '1'),   // Tenor-Sax
        array('instrument' => 7, 'voice' => '1'),   // Bariton-Sax
        array('instrument' => 11, 'voice' => '1'),  // Trompete
        array('instrument' => 11, 'voice' => '2'),
        array('instrument' => 11, 'voice' => '3'),
        array('instrument' => 20, 'voice' => '1'),  // Flügelhorn
        array('instrument' => 12, 'voice' => '1'),  // Waldhorn
        array('instrument' => 12, 'voice' => '2'),
        array('instrument' => 13, 'voice' => '1'),  // Tenorhorn
        array('instrument' => 14, 'voice' => '1'),  // Bariton
        array('instrument' => 16, 'voice' => '1'),  // Posaune
        array('instrument' => 16, 'voice' => '2'),
        array('instrument' => 17, 'voice' => '1'),  // Bassposaune
        array('instrument' => 18, 'voice' => '1'),  // Tuba
        array('instrument' => 9, 'voice' => '1'),   // Schlagwerk
        array('instrument' => 22, 'voice' => '1'),  // Dirigent
    );
}

function seedParts($compositionId, $parts = null) {
    if($parts === null) {
        $parts = standardWindParts();
    }
    $n = 0;
    foreach($parts as $part) {
        $sf = new ScoreFile;
        $sf->Composition = (int)$compositionId;
        $sf->Instrument = (int)$part['instrument'];
        $sf->VoiceLabel = (string)$part['voice'];
        $sf->PageCount = isset($part['pages']) ? (int)$part['pages'] : 2;
        $sf->save();
        if(!$sf->Index) {
            throw new RuntimeException('ScoreFile save failed for composition '.$compositionId);
        }
        $n++;
    }
    echo "PARTS\tcomposition {$compositionId}\t{$n} Stimmen\n";
}

try {
    // —— Personen (Komponisten / Arrangeure) ——
    $composers = array(
        'hartmann' => seedComposer('Elena', 'Hartmann'),
        'schmitt' => seedComposer('Klaus-Peter', 'Schmitt'),
        'bellini' => seedComposer('Marco', 'Bellini'),
        'voss' => seedComposer('Ingrid', 'Voss'),
        'engel' => seedComposer('Tobias', 'Engel'),
        'meier' => seedComposer('Sabine', 'Meier-Brandt'),
        'novak' => seedComposer('Petr', 'Novák'),
        'wagner' => seedComposer('Lukas', 'Wagner'),
        'sato' => seedComposer('Yuki', 'Sato'),
        'dumas' => seedComposer('Claire', 'Dumas'),
    );

    // —— Verlage ——
    $publishers = array(
        'rhein' => seedPublisher('BläserEdition Rhein', 'Koblenz'),
        'harmonie' => seedPublisher('Harmonie-Verlag Köln', 'Köln'),
        'marsch' => seedPublisher('Marsch & Fanfare GmbH', 'München'),
        'suite' => seedPublisher('Suite Nova Editions', 'Wien'),
        'jugend' => seedPublisher('JugendBlasorchester Verlag', 'Stuttgart'),
    );

    // —— Mappen ——
    $collections = array(
        'konzert' => seedCollection('Jahreskonzert 2026'),
        'marsch' => seedCollection('Marschmappe A'),
        'jugend' => seedCollection('Jugendblasorchester Pflichtstücke'),
        'probe' => seedCollection('Probenmappe Frühjahr'),
    );

    // —— Stücke ——
    $works = array(
        array(
            'reg' => 1001,
            'title' => 'Ouvertüre „Rheinischer Frühling“',
            'composer' => $composers['hartmann'],
            'arranger' => $composers['schmitt'],
            'publisher' => $publishers['rhein'],
            'year' => 2018,
            'grade' => 3.5,
            'time' => '6:30',
            'collections' => array(array('konzert', 1), array('probe', 1)),
        ),
        array(
            'reg' => 1002,
            'title' => 'Marsch „Sternschnuppe“',
            'composer' => $composers['schmitt'],
            'arranger' => 0,
            'publisher' => $publishers['marsch'],
            'year' => 2012,
            'grade' => 2.5,
            'time' => '3:15',
            'collections' => array(array('marsch', 1), array('konzert', 2)),
        ),
        array(
            'reg' => 1003,
            'title' => 'Suite „Sieben Berge“',
            'composer' => $composers['bellini'],
            'arranger' => $composers['voss'],
            'publisher' => $publishers['suite'],
            'year' => 2020,
            'grade' => 4.0,
            'time' => '14:00',
            'collections' => array(array('konzert', 3)),
        ),
        array(
            'reg' => 1004,
            'title' => 'Concertino für Flügelhorn und Blasorchester',
            'composer' => $composers['engel'],
            'arranger' => $composers['engel'],
            'publisher' => $publishers['harmonie'],
            'year' => 2016,
            'grade' => 4.5,
            'time' => '8:45',
            'collections' => array(array('konzert', 4)),
        ),
        array(
            'reg' => 1005,
            'title' => 'Festliche Fanfare „Rheinbogen“',
            'composer' => $composers['wagner'],
            'arranger' => $composers['schmitt'],
            'publisher' => $publishers['marsch'],
            'year' => 2021,
            'grade' => 3.0,
            'time' => '2:40',
            'collections' => array(array('marsch', 2), array('konzert', 5)),
        ),
        array(
            'reg' => 1006,
            'title' => 'Ballade „Mondlicht über dem Rhein“',
            'composer' => $composers['dumas'],
            'arranger' => $composers['meier'],
            'publisher' => $publishers['rhein'],
            'year' => 2019,
            'grade' => 3.0,
            'time' => '5:20',
            'collections' => array(array('probe', 2)),
        ),
        array(
            'reg' => 1007,
            'title' => 'Rhapsodie „Bonner Nacht“',
            'composer' => $composers['novak'],
            'arranger' => $composers['voss'],
            'publisher' => $publishers['harmonie'],
            'year' => 2015,
            'grade' => 4.0,
            'time' => '9:10',
            'collections' => array(array('konzert', 6)),
        ),
        array(
            'reg' => 1008,
            'title' => 'Ouvertüre „Drachenfels“',
            'composer' => $composers['sato'],
            'arranger' => $composers['wagner'],
            'publisher' => $publishers['suite'],
            'year' => 2022,
            'grade' => 3.5,
            'time' => '7:00',
            'collections' => array(array('jugend', 1), array('probe', 3)),
        ),
        array(
            'reg' => 1009,
            'title' => 'Polka „Heitere Stunde“',
            'composer' => $composers['meier'],
            'arranger' => 0,
            'publisher' => $publishers['jugend'],
            'year' => 2010,
            'grade' => 2.0,
            'time' => '2:55',
            'collections' => array(array('jugend', 2), array('marsch', 3)),
        ),
        array(
            'reg' => 1010,
            'title' => 'Sinfonische Skizze „Klang der Städte“',
            'composer' => $composers['hartmann'],
            'arranger' => $composers['novak'],
            'publisher' => $publishers['harmonie'],
            'year' => 2023,
            'grade' => 5.0,
            'time' => '11:30',
            'collections' => array(array('konzert', 7)),
        ),
        array(
            'reg' => 1011,
            'title' => 'Meditation „Stille Gassen“',
            'composer' => $composers['voss'],
            'arranger' => $composers['dumas'],
            'publisher' => $publishers['rhein'],
            'year' => 2017,
            'grade' => 2.5,
            'time' => '4:05',
            'collections' => array(array('jugend', 3), array('probe', 4)),
        ),
        array(
            'reg' => 1012,
            'title' => 'Galopp „Rheinsegel“',
            'composer' => $composers['schmitt'],
            'arranger' => $composers['meier'],
            'publisher' => $publishers['marsch'],
            'year' => 2014,
            'grade' => 3.0,
            'time' => '3:40',
            'collections' => array(array('marsch', 4)),
        ),
        array(
            'reg' => 1013,
            'title' => 'Variationen über ein rheinisches Volkslied',
            'composer' => $composers['engel'],
            'arranger' => $composers['hartmann'],
            'publisher' => $publishers['suite'],
            'year' => 2011,
            'grade' => 4.0,
            'time' => '10:15',
            'collections' => array(array('konzert', 8), array('jugend', 4)),
        ),
        array(
            'reg' => 1014,
            'title' => 'Intermezzo „Kaffeestunde“',
            'composer' => $composers['bellini'],
            'arranger' => 0,
            'publisher' => $publishers['jugend'],
            'year' => 2009,
            'grade' => 1.5,
            'time' => '3:00',
            'collections' => array(array('jugend', 5), array('probe', 5)),
        ),
        array(
            'reg' => 1015,
            'title' => 'Finale „Feuerwerk am Rhein“',
            'composer' => $composers['wagner'],
            'arranger' => $composers['sato'],
            'publisher' => $publishers['harmonie'],
            'year' => 2024,
            'grade' => 4.5,
            'time' => '6:50',
            'collections' => array(array('konzert', 9)),
        ),
    );

    foreach($works as $work) {
        $cid = seedComposition($work);
        seedParts($cid);
        if(!empty($work['collections'])) {
            foreach($work['collections'] as $link) {
                list($colKey, $num) = $link;
                seedCollectionItem($collections[$colKey], $cid, $num);
            }
        }
    }

    echo "Done. Seeded ".count($composers)." composers, ".count($publishers)." publishers, "
        .count($collections)." collections, ".count($works)." compositions with full wind parts.\n";
}
catch(Throwable $e) {
    fwrite(STDERR, "ERROR\t".$e->getMessage()."\n");
    exit(1);
}

exit(0);
?>
