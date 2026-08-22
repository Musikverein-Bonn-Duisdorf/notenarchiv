#!/usr/bin/env php
<?php
/**
 * Import works from ArchivMvD Dachschränke.xlsx via Archiv JSON API.
 *
 * Usage:
 *   php scripts/importDachschraenkeApi.php --local [--file=…] [--dry-run]
 *   php scripts/importDachschraenkeApi.php --local --apply [--on-conflict=ask|report|…] [--run-note=…]
 *
 * Prod (safe — default --on-conflict=report):
 *   ARCHIV_API_BASE=https://…/api ARCHIV_API_TOKEN=… \
 *     php scripts/importDachschraenkeApi.php --apply --file=… [--conflicts-out=conflicts.tsv]
 *
 * Duplicate policy:
 *   - Same title already in DB (any reg) → skip
 *   - Same reg + same title (or leichte Abweichung) → skip (Prod behalten)
 *   - Same reg + different title → anlegen (Heftchen: mehrere Stücke pro Inv.-Nr.)
 *   - Rows without title in XLSX are ignored (reserved numbers only)
 *
 * --on-conflict=report: only remaining hard conflicts → list + optional TSV
 */
if(PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root.'/scripts/lib/dachschraenkeXlsxParser.php';

$local = in_array('--local', $argv, true);
$apply = in_array('--apply', $argv, true);
$dryRun = !$apply;
$file = '/home/schedler/Downloads/ArchivMvD Dachschränke.xlsx';
$login = 'mschedler';
$onConflict = $local ? 'ask' : 'report';
$runNote = '';
$conflictsOut = '';

foreach($argv as $arg) {
    if(strpos($arg, '--file=') === 0) {
        $file = substr($arg, 7);
    } elseif(strpos($arg, '--login=') === 0) {
        $login = substr($arg, 8);
    } elseif(strpos($arg, '--on-conflict=') === 0) {
        $onConflict = substr($arg, 14);
    } elseif(strpos($arg, '--run-note=') === 0) {
        $runNote = substr($arg, 11);
    } elseif(strpos($arg, '--conflicts-out=') === 0) {
        $conflictsOut = substr($arg, 16);
    }
}

if($apply) {
    chdir($root);
    require_once $root.'/common/config.php';
    require_once $root.'/libs/helpers.php';
}

if(!in_array($onConflict, array('ask', 'skip', 'next', 'abort', 'report'), true)) {
    fwrite(STDERR, "Invalid --on-conflict (ask|skip|next|abort|report)\n");
    exit(1);
}

$isTty = function_exists('posix_isatty') && @posix_isatty(STDIN);
if($apply && $onConflict === 'ask' && !$isTty) {
    fwrite(STDERR, "Non-interactive: set --on-conflict=report|skip|next|abort\n");
    exit(1);
}

try {
    $works = archivParseDachschraenkeXlsx($file);
} catch(Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
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
    $tokCmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/issueApiToken.php').' '.escapeshellarg($login).' dachschraenke-import';
    $tokOut = array();
    $tokCode = 0;
    exec($tokCmd.' 2>/dev/null', $tokOut, $tokCode);
    $token = isset($tokOut[0]) ? trim($tokOut[0]) : '';
    if($token === '' || $tokCode !== 0) {
        fwrite(STDERR, "Could not issue API token for login {$login}\n");
        exit(1);
    }
    $port = 8768;
    $base = 'http://127.0.0.1:'.$port.'/api';
    $cmd = escapeshellarg(PHP_BINARY).' -S 127.0.0.1:'.$port.' -t '.escapeshellarg($root);
    $descriptors = array(
        0 => array('file', '/dev/null', 'r'),
        1 => array('file', sys_get_temp_dir().'/archiv-dachschraenke-import.log', 'a'),
        2 => array('file', sys_get_temp_dir().'/archiv-dachschraenke-import.log', 'a'),
    );
    $serverProc = proc_open($cmd, $descriptors, $pipes, $root);
    if(!is_resource($serverProc)) {
        fwrite(STDERR, "Failed to start php -S\n");
        exit(1);
    }
    usleep(450000);
}

function apiRequest($method, $path, $token, $base, $json = null, $runNote = '') {
    $url = $base.$path;
    $ch = curl_init($url);
    $headers = array('Authorization: Bearer '.$token);
    $runNote = trim((string)$runNote);
    if($runNote !== '') {
        $headers[] = 'X-Archiv-Run-Note: '.$runNote;
    }
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

function loadComposersByKey($token, $base) {
    list($code, $res) = apiRequest('GET', '/composers.php?limit=200', $token, $base);
    if($code !== 200 || empty($res['composers'])) {
        return array();
    }
    $map = array();
    foreach($res['composers'] as $row) {
        $key = mb_strtolower(trim((string)$row['lastName']).'|'.trim((string)$row['firstName']), 'UTF-8');
        $map[$key] = (int)$row['id'];
    }
    return $map;
}

function ensureComposer($token, $base, array &$composerIds, $first, $last, $dryRun, $runNote = '') {
    $first = trim((string)$first);
    $last = trim((string)$last);
    if($last === '') {
        return 0;
    }
    $key = mb_strtolower($last.'|'.$first, 'UTF-8');
    if(isset($composerIds[$key])) {
        return $composerIds[$key];
    }
    list($code, $res) = apiRequest('GET', '/composers.php?q='.rawurlencode($last).'&limit=200', $token, $base);
    if($code === 200) {
        foreach(($res['composers'] ?? array()) as $row) {
            $rKey = mb_strtolower(trim((string)$row['lastName']).'|'.trim((string)$row['firstName']), 'UTF-8');
            $composerIds[$rKey] = (int)$row['id'];
            if($rKey === $key) {
                return (int)$row['id'];
            }
        }
    }
    if($dryRun) {
        fwrite(STDOUT, "  [dry-run] would create composer {$last}, {$first}\n");
        return -1;
    }
    list($code, $res) = apiRequest('POST', '/composers.php', $token, $base, array(
        'firstName' => $first,
        'lastName' => $last,
    ), $runNote);
    if($code !== 200 || empty($res['id'])) {
        fwrite(STDERR, "  composer create fail {$last}, {$first}: ".json_encode($res)."\n");
        return 0;
    }
    $id = (int)$res['id'];
    $composerIds[$key] = $id;
    fwrite(STDOUT, "  composer+ {$id} {$last}, {$first}\n");
    return $id;
}

/** @return array{byReg: array, byTitle: array<string, array{id:int,title:string,nTitle:string,reg:int}>} */
function loadCompositionsIndex($token, $base, array $works) {
    $byReg = loadCompositionsByReg($token, $base, $works);
    $byTitle = array();
    list($code, $res) = apiRequest('GET', '/compositions.php?limit=200', $token, $base);
    if($code === 200) {
        foreach(($res['compositions'] ?? array()) as $row) {
            $nTitle = archivNormalizeWorkTitle($row['title']);
            if($nTitle === '' || isset($byTitle[$nTitle])) {
                continue;
            }
            $byTitle[$nTitle] = array(
                'id' => (int)$row['id'],
                'title' => (string)$row['title'],
                'reg' => isset($row['registrationNumber']) ? (int)$row['registrationNumber'] : 0,
                'nTitle' => $nTitle,
            );
        }
    }
    return array('byReg' => $byReg, 'byTitle' => $byTitle);
}

/** @return array<int, list<array{id:int,title:string,nTitle:string}>> */
function loadCompositionsByReg($token, $base, array $works) {
    $byReg = array();
    $want = array();
    foreach($works as $work) {
        $want[(int)$work['registrationNumber']] = true;
    }
    foreach(array_keys($want) as $reg) {
        list($code, $res) = apiRequest('GET', '/compositions.php?q='.rawurlencode((string)$reg).'&limit=200', $token, $base);
        if($code !== 200) {
            continue;
        }
        foreach(($res['compositions'] ?? array()) as $row) {
            $rowReg = isset($row['registrationNumber']) ? (int)$row['registrationNumber'] : 0;
            if($rowReg !== (int)$reg) {
                continue;
            }
            if(!isset($byReg[$rowReg])) {
                $byReg[$rowReg] = array();
            }
            $byReg[$rowReg][] = array(
                'id' => (int)$row['id'],
                'title' => (string)$row['title'],
                'nTitle' => archivNormalizeWorkTitle($row['title']),
            );
        }
    }
    return $byReg;
}

function nextFreeRegistrationNumberLocal($root, array $byReg, array $pendingRegs) {
    $n = nextArchiverNumber();
    $used = array_fill_keys(array_keys($byReg), true);
    foreach($pendingRegs as $r => $_) {
        $used[(int)$r] = true;
    }
    while(isset($used[$n])) {
        $n++;
    }
    return $n;
}

function askConflict($reg, $existingTitle, $newTitle, $row) {
    fwrite(STDOUT, "\nKONFLIKT Zeile {$row}: Inventarnummer {$reg} ist belegt.\n");
    fwrite(STDOUT, "  vorhanden: {$existingTitle}\n");
    fwrite(STDOUT, "  import:    {$newTitle}\n");
    fwrite(STDOUT, "  [s] überspringen  [n] nächste freie Nummer  [a] abbrechen  [?] Hilfe: ");
    $line = fgets(STDIN);
    $ans = $line !== false ? strtolower(trim($line)) : 'a';
    if($ans === 'n') {
        return 'next';
    }
    if($ans === 'a') {
        return 'abort';
    }
    return 'skip';
}

function importRecordConflict(array &$conflicts, array $work, $reg, array $decision) {
    $conflicts[] = array(
        'row' => (int)$work['row'],
        'registrationNumber' => (int)$reg,
        'importTitle' => (string)$work['title'],
        'existingId' => isset($decision['existingId']) ? (int)$decision['existingId'] : null,
        'existingTitle' => isset($decision['existingTitle']) ? (string)$decision['existingTitle'] : '',
        'reason' => isset($decision['reason']) ? (string)$decision['reason'] : 'reg_taken',
    );
}

function importPrintConflictSummary(array $conflicts) {
    if(count($conflicts) === 0) {
        return;
    }
    fwrite(STDERR, "\n=== Inventarnummer-Konflikte (".count($conflicts).") — nicht importiert ===\n");
    foreach($conflicts as $c) {
        $idPart = !empty($c['existingId']) ? ' #'.(int)$c['existingId'] : '';
        fwrite(STDERR, sprintf(
            "Zeile %4d  Inv.%d  Import: «%s»  |  DB%s: «%s»  (%s)\n",
            (int)$c['row'],
            (int)$c['registrationNumber'],
            (string)$c['importTitle'],
            $idPart,
            (string)$c['existingTitle'],
            (string)$c['reason']
        ));
    }
}

$exitCode = 0;

try {
    list($code, $me) = apiRequest('GET', '/me.php', $token, $base, null, $runNote);
    if($code !== 200 || empty($me['ok'])) {
        fwrite(STDERR, "Auth failed HTTP {$code}: ".json_encode($me)."\n");
        exit(1);
    }
    fwrite(STDERR, ($dryRun ? 'DRY-RUN ' : 'APPLY ').'as '.($me['user']['name'] ?? '?')." — {$file}\n");
    fwrite(STDERR, 'Mode: on-conflict='.$onConflict."\n");
    fwrite(STDERR, 'Parsed works with title: '.count($works)."\n");

    $composerIds = loadComposersByKey($token, $base);
    $index = loadCompositionsIndex($token, $base, $works);
    $byReg = $index['byReg'];
    $byTitle = $index['byTitle'];
    $pendingRegs = array();
    $conflicts = array();

    $stats = array('create' => 0, 'skip' => 0, 'skip_same' => 0, 'conflict' => 0, 'conflict_next' => 0, 'fail' => 0);

    foreach($works as $work) {
        $reg = (int)$work['registrationNumber'];
        $title = $work['title'];
        $nTitle = archivNormalizeWorkTitle($title);
        $row = (int)$work['row'];

        fwrite(STDOUT, sprintf("Zeile %4d Inv.%d  %s\n", $row, $reg, $title));

        if(isset($byTitle[$nTitle])) {
            $existing = $byTitle[$nTitle];
            $msg = '  =skip Titel bereits vorhanden (#'.$existing['id'];
            if((int)$existing['reg'] > 0) {
                $msg .= ', Inv.'.$existing['reg'];
            }
            fwrite(STDOUT, $msg.")\n");
            $stats['skip']++;
            continue;
        }

        $decision = archivImportRegDecision($nTitle, $reg, $byReg, $pendingRegs);
        if($decision['action'] === 'skip') {
            $skipReason = isset($decision['reason']) ? (string)$decision['reason'] : 'same_piece';
            if($skipReason === 'same_piece_title_variant') {
                fwrite(STDOUT, '  =skip Prod-Titel behalten Inv.'.$reg.' (#'.(int)$decision['existingId'].": «".(string)($decision['existingTitle'] ?? '')."»)\n");
            } else {
                fwrite(STDOUT, '  =skip gleiches Stück Inv.'.$reg.' (#'.(int)$decision['existingId'].")\n");
            }
            $stats['skip_same']++;
            continue;
        }
        if($decision['action'] === 'conflict') {
            importRecordConflict($conflicts, $work, $reg, $decision);
            $existingTitle = (string)($decision['existingTitle'] ?? '');
            if($onConflict === 'report' || ($dryRun && $onConflict !== 'ask')) {
                fwrite(STDOUT, "  !Konflikt Inv.{$reg}: DB «{$existingTitle}»\n");
                $stats['conflict']++;
                continue;
            }
            if($dryRun && $onConflict === 'ask') {
                fwrite(STDOUT, "  !Konflikt Inv.{$reg}: DB «{$existingTitle}»\n");
                $stats['conflict']++;
                continue;
            }
            $action = $onConflict;
            if($onConflict === 'ask') {
                $action = askConflict($reg, $existingTitle, $title, $row);
            }
            if($action === 'abort') {
                fwrite(STDERR, "Abgebrochen.\n");
                break;
            }
            if($action === 'skip' || $action === 'report') {
                fwrite(STDOUT, "  !skip Konflikt Inv.{$reg}\n");
                $stats['conflict']++;
                continue;
            }
            if($action === 'next') {
                $reg = nextFreeRegistrationNumberLocal($root, $byReg, $pendingRegs);
                fwrite(STDOUT, "  → nächste freie Nummer Inv.{$reg}\n");
                $stats['conflict_next']++;
            }
        }

        $composer = archivParsePersonLabel($work['composerLabel']);
        $arranger = archivParsePersonLabel($work['arrangerLabel']);
        $composerId = ensureComposer($token, $base, $composerIds, $composer['firstName'], $composer['lastName'], $dryRun, $runNote);
        $arrangerId = 0;
        if($arranger['lastName'] !== '') {
            $arrangerId = ensureComposer($token, $base, $composerIds, $arranger['firstName'], $arranger['lastName'], $dryRun, $runNote);
        }

        if($dryRun) {
            $cLabel = $work['composerLabel'] !== '' ? $work['composerLabel'] : '—';
            $aLabel = $work['arrangerLabel'] !== '' ? $work['arrangerLabel'] : '—';
            fwrite(STDOUT, "  [dry-run] would create Inv.{$reg} | {$cLabel} | arr: {$aLabel}\n");
            $stats['create']++;
            if(!isset($byReg[$reg])) {
                $byReg[$reg] = array();
            }
            $byReg[$reg][] = array('id' => 0, 'title' => $title, 'nTitle' => $nTitle);
            $pendingRegs[$reg] = true;
            continue;
        }

        if($composerId < 1 && $arrangerId < 1) {
            fwrite(STDERR, "  FAIL missing composer/arranger for row {$row}\n");
            $stats['fail']++;
            continue;
        }

        $payload = array(
            'title' => $title,
            'registrationNumber' => $reg,
        );
        if($composerId > 0) {
            $payload['composerId'] = $composerId;
        }
        if($arrangerId > 0) {
            $payload['arrangerId'] = $arrangerId;
        }
        list($code, $res) = apiRequest('POST', '/compositions.php', $token, $base, $payload, $runNote);
        if($code !== 200 || empty($res['id'])) {
            fwrite(STDERR, "  FAIL create: ".json_encode($res)."\n");
            $stats['fail']++;
            continue;
        }
        $id = (int)$res['id'];
        $actualReg = isset($res['registrationNumber']) ? (int)$res['registrationNumber'] : $reg;
        if(!isset($byReg[$actualReg])) {
            $byReg[$actualReg] = array();
        }
        $byReg[$actualReg][] = array('id' => $id, 'title' => $title, 'nTitle' => $nTitle);
        $pendingRegs[$actualReg] = true;
        $byTitle[$nTitle] = array('id' => $id, 'title' => $title, 'nTitle' => $nTitle, 'reg' => $actualReg);
        fwrite(STDOUT, "  +create #{$id} Inv.{$actualReg}\n");
        $stats['create']++;
    }

    importPrintConflictSummary($conflicts);
    if($conflictsOut !== '' && count($conflicts) > 0) {
        if(archivImportWriteConflictReport($conflictsOut, $conflicts)) {
            fwrite(STDERR, "Konfliktliste: {$conflictsOut}\n");
        } else {
            fwrite(STDERR, "WARN: could not write {$conflictsOut}\n");
        }
    }

    fwrite(STDERR, sprintf(
        "\nDone (%s): create=%d skip_title=%d skip_same=%d conflict=%d conflict_next=%d fail=%d\n",
        $dryRun ? 'dry-run' : 'applied',
        $stats['create'],
        $stats['skip'],
        $stats['skip_same'],
        $stats['conflict'],
        $stats['conflict_next'],
        $stats['fail']
    ));
    if($dryRun) {
        fwrite(STDERR, "Prod: --apply --on-conflict=report (default remote) — nothing overwritten.\n");
    }
    if(count($conflicts) > 0) {
        $exitCode = 2;
    }
}
finally {
    if(is_resource($serverProc)) {
        proc_terminate($serverProc);
        proc_close($serverProc);
    }
}

exit($exitCode);
