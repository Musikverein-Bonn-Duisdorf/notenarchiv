<?php
/**
 * Migrate legacy meldeliste_-prefixed archiv tables to archiv_ schema.
 *
 * Usage:
 *   php scripts/migrateLegacyArchiv.php [--legacy=meldeliste_] [--new=archiv_]
 *
 * Env: LEGACY_PREFIX, NEW_PREFIX
 */
if(php_sapi_name() !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$root = dirname(__DIR__);
$configFile = $root.'/common/config.php';
if(!is_readable($configFile)) {
    fwrite(STDERR, "Missing common/config.php\n");
    exit(1);
}
require_once $configFile;

$legacyPrefix = getenv('LEGACY_PREFIX') ?: 'meldeliste_';
$newPrefix = getenv('NEW_PREFIX') ?: 'archiv_';

foreach(array_slice($argv, 1) as $arg) {
    if(strpos($arg, '--legacy=') === 0) {
        $legacyPrefix = substr($arg, 9);
    }
    elseif(strpos($arg, '--new=') === 0) {
        $newPrefix = substr($arg, 6);
    }
}

if(!isset($GLOBALS['conn'])) {
    fwrite(STDERR, "No DB connection.\n");
    exit(1);
}

function tableExists($conn, $prefix, $table) {
    $name = mysqli_real_escape_string($conn, $prefix.$table);
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$name' LIMIT 1;";
    $dbr = mysqli_query($conn, $sql);
    return $dbr && mysqli_fetch_array($dbr);
}

function migrateTable($conn, $legacyPrefix, $newPrefix, $legacyTable, $newTable, $columns, $transform = null) {
    if(!tableExists($conn, $legacyPrefix, $legacyTable)) {
        echo "SKIP\t$legacyTable (legacy table missing)\n";
        return 0;
    }
    if(!tableExists($conn, $newPrefix, $newTable)) {
        echo "ERROR\t$newTable (target table missing — run SchemaManager create first)\n";
        return 0;
    }

    $legacy = '`'.mysqli_real_escape_string($conn, $legacyPrefix.$legacyTable).'`';
    $target = '`'.mysqli_real_escape_string($conn, $newPrefix.$newTable).'`';
    $colList = implode(', ', array_map(function($c) { return '`'.$c.'`'; }, $columns));

    $sql = "SELECT $colList FROM $legacy;";
    $dbr = mysqli_query($conn, $sql);
    if(!$dbr) {
        echo "ERROR\t$legacyTable: ".mysqli_error($conn)."\n";
        return 0;
    }

    $inserted = 0;
    $skipped = 0;
    while($row = mysqli_fetch_assoc($dbr)) {
        if($transform) {
            $row = $transform($row);
        }
        $index = (int)$row['Index'];
        $check = sprintf('SELECT `Index` FROM %s WHERE `Index` = %d LIMIT 1;', $target, $index);
        $exists = mysqli_query($conn, $check);
        if($exists && mysqli_fetch_array($exists)) {
            $skipped++;
            continue;
        }

        $values = array();
        foreach($columns as $col) {
            if(!array_key_exists($col, $row)) {
                $values[] = 'NULL';
                continue;
            }
            $val = $row[$col];
            if($val === null) {
                $values[] = 'NULL';
            }
            elseif(is_int($val) || (is_string($val) && is_numeric($val) && strpos($col, 'Path') === false && $col !== 'VoiceLabel')) {
                $values[] = (string)(int)$val;
            }
            else {
                $values[] = '"'.mysqli_real_escape_string($conn, (string)$val).'"';
            }
        }

        $insert = sprintf(
            'INSERT IGNORE INTO %s (%s) VALUES (%s);',
            $target,
            $colList,
            implode(', ', $values)
        );
        if(mysqli_query($conn, $insert) && mysqli_affected_rows($conn) > 0) {
            $inserted++;
        }
        else {
            $skipped++;
        }
    }
    echo "OK\t$legacyTable → $newTable\tinserted=$inserted\tskipped=$skipped\n";
    return $inserted;
}

echo "Legacy prefix: $legacyPrefix\n";
echo "New prefix:    $newPrefix\n\n";

migrateTable($GLOBALS['conn'], $legacyPrefix, $newPrefix, 'Composers', 'Composer',
    array('Index', 'FirstName', 'LastName'));

migrateTable($GLOBALS['conn'], $legacyPrefix, $newPrefix, 'Publishers', 'Publisher',
    array('Index', 'Name', 'Address'));

migrateTable($GLOBALS['conn'], $legacyPrefix, $newPrefix, 'Collections', 'Collection',
    array('Index', 'Name'));

migrateTable($GLOBALS['conn'], $legacyPrefix, $newPrefix, 'Collection', 'CollectionItem',
    array('Index', 'Collections', 'CollectionNumber', 'Composition'));

migrateTable($GLOBALS['conn'], $legacyPrefix, $newPrefix, 'Compositions', 'Composition',
    array('Index', 'RegistrationNumber', 'Title', 'Composer', 'Arranger', 'Publisher', 'Year', 'PerformanceTime', 'Grade', 'FilePath'));

if(tableExists($GLOBALS['conn'], $legacyPrefix, 'Parts') && tableExists($GLOBALS['conn'], $newPrefix, 'ScoreFile')) {
    $legacy = '`'.mysqli_real_escape_string($GLOBALS['conn'], $legacyPrefix.'Parts').'`';
    $target = '`'.mysqli_real_escape_string($GLOBALS['conn'], $newPrefix.'ScoreFile').'`';
    $dbr = mysqli_query($GLOBALS['conn'], "SELECT `Index`, `Composition`, `Instrument`, `Part`, `FilePath` FROM $legacy;");
    $inserted = 0;
    $skipped = 0;
    while($row = mysqli_fetch_assoc($dbr)) {
        $index = (int)$row['Index'];
        $check = sprintf('SELECT `Index` FROM %s WHERE `Index` = %d LIMIT 1;', $target, $index);
        $exists = mysqli_query($GLOBALS['conn'], $check);
        if($exists && mysqli_fetch_array($exists)) {
            $skipped++;
            continue;
        }
        $voice = mysqli_real_escape_string($GLOBALS['conn'], (string)$row['Part']);
        $fp = $row['FilePath'] !== null && $row['FilePath'] !== ''
            ? '"'.mysqli_real_escape_string($GLOBALS['conn'], (string)$row['FilePath']).'"'
            : 'NULL';
        $insert = sprintf(
            'INSERT IGNORE INTO %s (`Index`, `Composition`, `Instrument`, `VoiceLabel`, `NextcloudPath`, `FilePath`) VALUES (%d, %d, %d, "%s", "", %s);',
            $target,
            $index,
            (int)$row['Composition'],
            (int)$row['Instrument'],
            $voice,
            $fp
        );
        if(mysqli_query($GLOBALS['conn'], $insert) && mysqli_affected_rows($GLOBALS['conn']) > 0) {
            $inserted++;
        }
        else {
            $skipped++;
        }
    }
    echo "OK\tParts → ScoreFile\tinserted=$inserted\tskipped=$skipped\n";
}
else {
    echo "SKIP\tParts (legacy or target table missing)\n";
}

echo "\nMigration complete.\n";
