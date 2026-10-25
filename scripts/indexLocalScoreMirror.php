<?php
/**
 * Index PDFs under data/Notenarchiv/ into ScoreFile (ARCHIV-11).
 *
 * Usage:
 *   php scripts/indexLocalScoreMirror.php
 *   php scripts/indexLocalScoreMirror.php --dry-run
 *   php scripts/indexLocalScoreMirror.php --path=/absolute/or/relative/mirror
 *
 * Does not read or write the Nextcloud/pCloud mount — only the local mirror.
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
require_once $root.'/libs/ScoreFile.php';
require_once $root.'/libs/Composition.php';
require_once $root.'/libs/ScoreFilenameParser.php';
require_once $root.'/libs/ScoreMirror.php';

if(!isset($GLOBALS['conn']) || !$GLOBALS['conn']) {
    fwrite(STDERR, "No DB connection.\n");
    exit(1);
}

if(!isset($_SESSION) || !is_array($_SESSION)) {
    $_SESSION = array();
}
if(!isset($GLOBALS['optionsDB']) || !is_array($GLOBALS['optionsDB'])) {
    $GLOBALS['optionsDB'] = array();
}
if(empty($GLOBALS['optionsDB']['dataDirectory'])) {
    $GLOBALS['optionsDB']['dataDirectory'] = $root.'/data/';
}

$dryRun = in_array('--dry-run', $argv, true);
$mirrorRoot = ScoreMirror::absoluteRoot();
foreach($argv as $arg) {
    if(strpos($arg, '--path=') === 0) {
        $mirrorRoot = rtrim(str_replace('\\', '/', substr($arg, 7)), '/').'/';
    }
}

if(!is_dir($mirrorRoot)) {
    fwrite(STDERR, "Mirror missing: ".$mirrorRoot."\n");
    exit(1);
}

$instruments = ScoreMirror::loadInstrumentCatalog();
if(count($instruments) < 1) {
    fwrite(STDERR, "No Melde instruments found (identityPrefix Instrument table).\n");
    exit(1);
}

$stats = array(
    'folders' => 0,
    'pdfs' => 0,
    'indexed' => 0,
    'updated' => 0,
    'unmatched' => 0,
    'no_composition' => 0,
    'zzz' => 0,
    'skipped_score_no_inst' => 0,
);

$unmatched = array();
$scoreInstrumentId = 0;
foreach($instruments as $inst) {
    if($inst['norm'] === 'partitur' || $inst['norm'] === 'direktion' || strpos($inst['norm'], 'score') !== false) {
        $scoreInstrumentId = $inst['id'];
        break;
    }
}

$dirs = @scandir($mirrorRoot);
if($dirs === false) {
    fwrite(STDERR, "Cannot scan ".$mirrorRoot."\n");
    exit(1);
}

foreach($dirs as $entry) {
    if($entry === '.' || $entry === '..') {
        continue;
    }
    $absDir = $mirrorRoot.$entry;
    if(!is_dir($absDir)) {
        continue;
    }
    $stats['folders']++;
    if(stripos($entry, 'ZZZ') === 0) {
        $stats['zzz']++;
        $unmatched[] = array('path' => $entry.'/', 'reason' => 'zzz_folder');
        continue;
    }
    $reg = ScoreMirror::registrationFromFolderName($entry);
    if($reg === null) {
        $unmatched[] = array('path' => $entry.'/', 'reason' => 'no_registration');
        $stats['no_composition']++;
        continue;
    }
    $compositionId = ScoreMirror::findCompositionByRegistration($reg);
    if($compositionId < 1) {
        $unmatched[] = array('path' => $entry.'/', 'reason' => 'composition_missing', 'registrationNumber' => $reg);
        $stats['no_composition']++;
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach($iterator as $fileInfo) {
        if(!$fileInfo->isFile()) {
            continue;
        }
        $ext = strtolower($fileInfo->getExtension());
        if($ext !== 'pdf') {
            continue;
        }
        $stats['pdfs']++;
        $basename = $fileInfo->getFilename();
        $full = str_replace('\\', '/', $fileInfo->getPathname());
        $rel = substr($full, strlen(rtrim($mirrorRoot, '/')) + 1);

        $parsed = ScoreFilenameParser::parse($basename);
        if(empty($parsed['ok'])) {
            $stats['unmatched']++;
            $unmatched[] = array(
                'path' => $rel,
                'reason' => isset($parsed['reason']) ? $parsed['reason'] : 'unmatched',
                'compositionId' => $compositionId,
            );
            continue;
        }

        $instrumentId = 0;
        $voiceLabel = (string)$parsed['voiceLabel'];
        if($parsed['kind'] === 'score') {
            if($scoreInstrumentId > 0) {
                $instrumentId = $scoreInstrumentId;
            }
            else {
                $stats['skipped_score_no_inst']++;
                $unmatched[] = array('path' => $rel, 'reason' => 'score_no_instrument', 'compositionId' => $compositionId);
                continue;
            }
        }
        else {
            $match = ScoreFilenameParser::matchInstrument($instruments, $parsed['instrumentToken']);
            if($match === null) {
                $stats['unmatched']++;
                $unmatched[] = array(
                    'path' => $rel,
                    'reason' => 'instrument_unmatched',
                    'token' => $parsed['instrumentToken'],
                    'compositionId' => $compositionId,
                );
                continue;
            }
            $instrumentId = $match['id'];
        }

        if($dryRun) {
            $stats['indexed']++;
            echo "DRY\t".$rel."\tinst=".$instrumentId."\tvoice=".$voiceLabel."\n";
            continue;
        }

        $result = ScoreMirror::upsertMirrorScore(
            $compositionId,
            array('instrumentId' => $instrumentId, 'voiceLabel' => $voiceLabel),
            $rel
        );
        if(empty($result['ok'])) {
            $stats['unmatched']++;
            $unmatched[] = array('path' => $rel, 'reason' => 'save_failed', 'compositionId' => $compositionId);
            continue;
        }
        if($result['action'] === 'update') {
            $stats['updated']++;
        }
        else {
            $stats['indexed']++;
        }
    }
}

echo "OK\tmirror=".$mirrorRoot."\n";
foreach($stats as $k => $v) {
    echo "STAT\t".$k."\t".$v."\n";
}
$reportPath = $root.'/data/Notenarchiv/_index_unmatched.json';
$payload = array(
    'generated' => date('c'),
    'dryRun' => $dryRun,
    'stats' => $stats,
    'unmatched' => $unmatched,
);
if(!$dryRun || count($unmatched) > 0) {
    @file_put_contents($reportPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "REPORT\t".$reportPath."\t".count($unmatched)."\n";
}

exit(0);
?>
