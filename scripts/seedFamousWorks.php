<?php
/**
 * Local-only: seed best-known works for famous composers (after seedFamousComposers.php).
 * Usage: php scripts/seedFamousWorks.php
 */
$root = dirname(__DIR__);
require_once $root.'/libs/sessionBootstrap.php';
archivConfigureSession();
include $root.'/common/include.php';
mysqli_select_db($GLOBALS['conn'], $sql['database']) or die(mysqli_error($GLOBALS['conn']));

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

function findCompositionId($composerId, $title) {
    $escaped = mysqli_real_escape_string($GLOBALS['conn'], htmlentities(trim($title)));
    $sql = sprintf(
        'SELECT `Index` FROM `%sComposition` WHERE `Composer` = %d AND `Title` = "%s" LIMIT 1;',
        $GLOBALS['dbprefix'],
        (int)$composerId,
        $escaped
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
    return $row ? (int)$row['Index'] : 0;
}

function nextRegistrationNumber() {
    $sql = sprintf('SELECT MAX(`RegistrationNumber`) AS `m` FROM `%sComposition`;', $GLOBALS['dbprefix']);
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
    $max = $row && $row['m'] !== null ? (int)$row['m'] : 2000;
    return max(2001, $max + 1);
}

/**
 * Works keyed by "FirstName|LastName". Each work: title, year?, time?, grade?
 */
$catalog = array(
    'Ludwig|van Beethoven' => array(
        array('title' => 'Sinfonie Nr. 5 c-Moll („Schicksalssinfonie“)', 'year' => 1808, 'time' => '32:00', 'grade' => 5.0),
        array('title' => 'Sinfonie Nr. 9 d-Moll („Ode an die Freude“)', 'year' => 1824, 'time' => '65:00', 'grade' => 6.0),
        array('title' => 'Für Elise', 'year' => 1810, 'time' => '3:00', 'grade' => 2.0),
    ),
    'Wolfgang Amadeus|Mozart' => array(
        array('title' => 'Eine kleine Nachtmusik', 'year' => 1787, 'time' => '16:00', 'grade' => 3.0),
        array('title' => 'Die Zauberflöte (Ouvertüre)', 'year' => 1791, 'time' => '7:00', 'grade' => 4.0),
        array('title' => 'Requiem d-Moll', 'year' => 1791, 'time' => '55:00', 'grade' => 5.5),
    ),
    'Johann Sebastian|Bach' => array(
        array('title' => 'Toccata und Fuge d-Moll', 'year' => 1708, 'time' => '9:00', 'grade' => 4.0),
        array('title' => 'Brandenburgisches Konzert Nr. 3', 'year' => 1721, 'time' => '11:00', 'grade' => 4.0),
        array('title' => 'Air („Air on the G String“)', 'year' => 1730, 'time' => '5:00', 'grade' => 2.5),
    ),
    'Franz|Schubert' => array(
        array('title' => 'Ave Maria', 'year' => 1825, 'time' => '5:00', 'grade' => 2.5),
        array('title' => 'Sinfonie Nr. 8 h-Moll („Unvollendete“)', 'year' => 1822, 'time' => '25:00', 'grade' => 4.5),
        array('title' => 'Die Forelle', 'year' => 1817, 'time' => '3:30', 'grade' => 2.0),
    ),
    'Johannes|Brahms' => array(
        array('title' => 'Ungarischer Tanz Nr. 5', 'year' => 1869, 'time' => '3:00', 'grade' => 3.0),
        array('title' => 'Wiegenlied Op. 49 Nr. 4', 'year' => 1868, 'time' => '2:00', 'grade' => 1.5),
        array('title' => 'Sinfonie Nr. 1 c-Moll', 'year' => 1876, 'time' => '45:00', 'grade' => 5.5),
    ),
    'Richard|Wagner' => array(
        array('title' => 'Walkürenritt', 'year' => 1870, 'time' => '5:00', 'grade' => 4.5),
        array('title' => 'Hochzeitsmarsch (Lohengrin)', 'year' => 1850, 'time' => '6:00', 'grade' => 3.0),
        array('title' => 'Tannhäuser-Ouvertüre', 'year' => 1845, 'time' => '14:00', 'grade' => 4.5),
    ),
    'Giuseppe|Verdi' => array(
        array('title' => 'Va, pensiero (Nabucco)', 'year' => 1842, 'time' => '5:00', 'grade' => 3.0),
        array('title' => 'Triumphmarsch (Aida)', 'year' => 1871, 'time' => '7:00', 'grade' => 3.5),
        array('title' => 'La donna è mobile (Rigoletto)', 'year' => 1851, 'time' => '3:00', 'grade' => 2.5),
    ),
    'Antonín|Dvořák' => array(
        array('title' => 'Sinfonie Nr. 9 („Aus der Neuen Welt“)', 'year' => 1893, 'time' => '40:00', 'grade' => 5.0),
        array('title' => 'Slawischer Tanz Op. 46 Nr. 8', 'year' => 1878, 'time' => '4:00', 'grade' => 3.5),
        array('title' => 'Humoreske Op. 101 Nr. 7', 'year' => 1894, 'time' => '3:00', 'grade' => 2.5),
    ),
    'Carl Maria|von Weber' => array(
        array('title' => 'Der Freischütz (Ouvertüre)', 'year' => 1821, 'time' => '10:00', 'grade' => 4.0),
        array('title' => 'Aufforderung zum Tanz', 'year' => 1819, 'time' => '9:00', 'grade' => 3.5),
        array('title' => 'Oberon-Ouvertüre', 'year' => 1826, 'time' => '9:00', 'grade' => 4.0),
    ),
    'Felix|Mendelssohn Bartholdy' => array(
        array('title' => 'Hochzeitsmarsch (Sommernachtstraum)', 'year' => 1843, 'time' => '5:00', 'grade' => 3.0),
        array('title' => 'Sinfonie Nr. 4 („Italienische“)', 'year' => 1833, 'time' => '28:00', 'grade' => 4.5),
        array('title' => 'Das Lied ohne Worte Op. 30 Nr. 6 („Venezianisches Gondellied“)', 'year' => 1834, 'time' => '3:00', 'grade' => 2.5),
    ),
    'Robert|Schumann' => array(
        array('title' => 'Träumerei (Kinderszenen)', 'year' => 1838, 'time' => '3:00', 'grade' => 2.0),
        array('title' => 'Fröhlicher Landmann', 'year' => 1848, 'time' => '1:30', 'grade' => 1.5),
        array('title' => 'Klavierkonzert a-Moll', 'year' => 1845, 'time' => '30:00', 'grade' => 5.0),
    ),
    'Franz|Liszt' => array(
        array('title' => 'Ungarische Rhapsodie Nr. 2', 'year' => 1847, 'time' => '10:00', 'grade' => 5.0),
        array('title' => 'Liebestraum Nr. 3', 'year' => 1850, 'time' => '5:00', 'grade' => 3.5),
        array('title' => 'Les Préludes', 'year' => 1854, 'time' => '16:00', 'grade' => 4.5),
    ),
    'Peter|Tschaikowsky' => array(
        array('title' => 'Nussknacker – Blumenwalzer', 'year' => 1892, 'time' => '7:00', 'grade' => 3.5),
        array('title' => 'Ouvertüre 1812', 'year' => 1880, 'time' => '15:00', 'grade' => 4.5),
        array('title' => 'Schwanensee – Szenen', 'year' => 1877, 'time' => '12:00', 'grade' => 4.0),
    ),
    'John Philip|Sousa' => array(
        array('title' => 'The Stars and Stripes Forever', 'year' => 1896, 'time' => '3:30', 'grade' => 3.5),
        array('title' => 'The Washington Post', 'year' => 1889, 'time' => '2:45', 'grade' => 3.0),
        array('title' => 'Semper Fidelis', 'year' => 1888, 'time' => '3:00', 'grade' => 3.0),
    ),
    'Paul|Hindemith' => array(
        array('title' => 'Symphonic Metamorphosis', 'year' => 1943, 'time' => '20:00', 'grade' => 5.0),
        array('title' => 'Mathis der Maler (Sinfonie)', 'year' => 1934, 'time' => '25:00', 'grade' => 5.0),
        array('title' => 'Marsch für Blasorchester', 'year' => 1925, 'time' => '4:00', 'grade' => 3.5),
    ),
    'Carl|Orff' => array(
        array('title' => 'Carmina Burana – O Fortuna', 'year' => 1937, 'time' => '2:30', 'grade' => 4.0),
        array('title' => 'Carmina Burana (Auswahl)', 'year' => 1937, 'time' => '25:00', 'grade' => 5.0),
    ),
    'Richard|Strauss' => array(
        array('title' => 'Also sprach Zarathustra (Einleitung)', 'year' => 1896, 'time' => '2:00', 'grade' => 4.0),
        array('title' => 'Till Eulenspiegels lustige Streiche', 'year' => 1895, 'time' => '15:00', 'grade' => 5.0),
        array('title' => 'Rosenkavalier-Walzer', 'year' => 1911, 'time' => '8:00', 'grade' => 3.5),
    ),
    'Georg Friedrich|Händel' => array(
        array('title' => 'Halleluja (Messias)', 'year' => 1741, 'time' => '4:00', 'grade' => 3.5),
        array('title' => 'Feuerwerksmusik', 'year' => 1749, 'time' => '18:00', 'grade' => 3.5),
        array('title' => 'Wassermusik – Suite', 'year' => 1717, 'time' => '20:00', 'grade' => 3.5),
    ),
);

$created = 0;
$skipped = 0;
$missingComposer = 0;
$reg = nextRegistrationNumber();

foreach($catalog as $key => $works) {
    list($first, $last) = explode('|', $key, 2);
    $composerId = findComposerId($first, $last);
    if($composerId < 1) {
        fwrite(STDERR, "MISSING composer: {$first} {$last}\n");
        $missingComposer++;
        continue;
    }
    echo "Composer #{$composerId} {$first} {$last}\n";
    foreach($works as $work) {
        $existing = findCompositionId($composerId, $work['title']);
        if($existing > 0) {
            echo "  skip   #{$existing} {$work['title']}\n";
            $skipped++;
            continue;
        }
        $piece = new Composition;
        $piece->RegistrationNumber = $reg;
        $piece->Title = $work['title'];
        $piece->Composer = $composerId;
        $piece->FilePath = '';
        if(isset($work['year'])) {
            $piece->Year = (int)$work['year'];
        }
        if(isset($work['grade'])) {
            $piece->Grade = (double)$work['grade'];
        }
        if(!empty($work['time'])) {
            $piece->PerformanceTime = $work['time'];
        }
        $piece->save();
        if(!(int)$piece->Index) {
            fwrite(STDERR, "  FAIL   {$work['title']}\n");
            continue;
        }
        echo "  create #{$piece->Index} Inv.{$reg} {$work['title']}\n";
        $created++;
        $reg++;
    }
}

echo "\nDone: {$created} created, {$skipped} skipped, {$missingComposer} composers missing.\n";
