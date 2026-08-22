<?php
/**
 * Parse "ArchivMvD Dachschränke.xlsx" (sheet 1: Lfd.-Nr., Titel, Komponist, Arrangeur).
 *
 * Rows without title are skipped (reserved numbers only). Returns list of:
 *   row, registrationNumber, title, composerLabel, arrangerLabel
 */
function archivParseDachschraenkeXlsx($path) {
    $path = (string)$path;
    if(!is_readable($path)) {
        throw new RuntimeException('XLSX not readable: '.$path);
    }
    $zip = new ZipArchive();
    if($zip->open($path) !== true) {
        throw new RuntimeException('Could not open XLSX: '.$path);
    }

    $sharedStrings = array();
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if(is_string($ssXml) && $ssXml !== '') {
        $ss = @simplexml_load_string($ssXml);
        if($ss !== false) {
            $ss->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach($ss->xpath('//m:si') ?: array() as $si) {
                $si->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $parts = array();
                foreach($si->xpath('.//m:t') ?: array() as $t) {
                    $parts[] = (string)$t;
                }
                $sharedStrings[] = implode('', $parts);
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if(!is_string($sheetXml) || $sheetXml === '') {
        throw new RuntimeException('Missing sheet1 in XLSX');
    }
    $sheet = @simplexml_load_string($sheetXml);
    if($sheet === false) {
        throw new RuntimeException('Invalid sheet1 XML');
    }
    $sheet->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    $rows = array();
    foreach($sheet->xpath('//m:sheetData/m:row') ?: array() as $row) {
        $row->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $cells = array();
        foreach($row->xpath('m:c') ?: array() as $cell) {
            $ref = (string)$cell['r'];
            if(!preg_match('/^([A-Z]+)/', $ref, $m)) {
                continue;
            }
            $col = $m[1];
            $type = (string)$cell['t'];
            $valNode = $cell->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main')->v;
            $val = $valNode !== null ? (string)$valNode : '';
            if($type === 's' && $val !== '' && isset($sharedStrings[(int)$val])) {
                $val = $sharedStrings[(int)$val];
            }
            $cells[$col] = $val;
        }
        $rows[] = $cells;
    }

    $works = array();
    foreach(array_slice($rows, 1) as $idx => $cells) {
        $title = isset($cells['B']) ? trim((string)$cells['B']) : '';
        if($title === '') {
            continue;
        }
        $regRaw = isset($cells['A']) ? trim((string)$cells['A']) : '';
        if($regRaw === '' || !ctype_digit($regRaw)) {
            continue;
        }
        $works[] = array(
            'row' => $idx + 2,
            'registrationNumber' => (int)$regRaw,
            'title' => archivCleanImportWorkTitle($title),
            'composerLabel' => isset($cells['C']) ? trim((string)$cells['C']) : '',
            'arrangerLabel' => isset($cells['D']) ? trim((string)$cells['D']) : '',
        );
    }

    return $works;
}

/** "Giant, B." → firstName/lastName; empty label → empty names. */
function archivParsePersonLabel($label) {
    $label = trim(str_replace(':', '.', (string)$label));
    if($label === '') {
        return array('firstName' => '', 'lastName' => '');
    }
    $parts = explode(',', $label, 2);
    if(count($parts) === 1) {
        return array('firstName' => '', 'lastName' => trim($parts[0]));
    }
    return array(
        'firstName' => trim($parts[1]),
        'lastName' => trim($parts[0]),
    );
}

function archivNormalizeWorkTitle($title) {
    $title = mb_strtolower(trim((string)$title), 'UTF-8');
    $title = preg_replace('/\s+/u', ' ', $title);
    return $title === null ? '' : $title;
}

/** Genre/Potp suffix labels in Excel (not part of the work title). */
function archivImportTitleGenreSuffixesRegex() {
    return 'potp\\.?|potpourri|potpouri|marsch|walzer|polka|sturmmarsch|konzertwalzer|konzertmarsch';
}

/**
 * Strip trailing Excel genre tags (" - Marsch -", " - Walzer -", " - Potp. -", …)
 * before import; keeps the actual work title only.
 */
function archivCleanImportWorkTitle($title) {
    $title = trim((string)$title);
    if($title === '') {
        return '';
    }
    $pat = '/\s*-\s*(?:'.archivImportTitleGenreSuffixesRegex().')\s*(?:-\s*)?$/ui';
    for($i = 0; $i < 4; $i++) {
        $next = preg_replace($pat, '', $title);
        if($next === null || $next === $title) {
            break;
        }
        $title = trim($next);
    }
    return trim($title);
}

/** Remove trailing genre/potp suffix when title has a distinct name part (fuzzy match). */
function archivStripWorkTitleSuffix($title) {
    if(!preg_match(
        '/\s*-\s*(?:'.archivImportTitleGenreSuffixesRegex().')\s*(?:-\s*)?$/ui',
        $title,
        $m,
        PREG_OFFSET_CAPTURE
    )) {
        return $title;
    }
    $before = trim(substr($title, 0, (int)$m[0][1]));
    $words = preg_split('/\s+/u', $before, -1, PREG_SPLIT_NO_EMPTY);
    if(!is_array($words) || count($words) < 2) {
        return $title;
    }
    return $before;
}

/** Aggressive normalization for fuzzy title matching (suffixes, punctuation). */
function archivNormalizeWorkTitleForMatch($title) {
    $title = archivNormalizeWorkTitle($title);
    $title = preg_replace('/\s*\[[^\]]*\]\s*/u', ' ', $title);
    $title = preg_replace('/\s*\([^)]*\)\s*/u', ' ', $title);
    $title = preg_replace('/\s*-\s*/u', ' - ', $title);
    $title = preg_replace('/\s+/u', ' ', trim($title));
    for($i = 0; $i < 4; $i++) {
        $next = archivStripWorkTitleSuffix($title);
        if($next === $title) {
            break;
        }
        $title = trim($next);
    }
    $title = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title);
    $title = preg_replace('/\s+/u', ' ', trim($title));
    return $title === null ? '' : $title;
}

/** True when titles differ only slightly (keep prod entry, skip import). */
function archivWorkTitlesLikelySame($titleA, $titleB) {
    $a = archivNormalizeWorkTitleForMatch($titleA);
    $b = archivNormalizeWorkTitleForMatch($titleB);
    if($a === '' || $b === '') {
        return false;
    }
    if($a === $b) {
        return true;
    }
    $lenA = mb_strlen($a, 'UTF-8');
    $lenB = mb_strlen($b, 'UTF-8');
    if($lenA > 0 && $lenB > 0) {
        $shorter = min($lenA, $lenB);
        $longer = max($lenA, $lenB);
        if(($shorter / $longer) >= 0.85
            && (mb_strpos($a, $b, 0, 'UTF-8') !== false || mb_strpos($b, $a, 0, 'UTF-8') !== false)) {
            return true;
        }
    }
    $ta = array();
    foreach(explode(' ', $a) as $word) {
        if(mb_strlen($word, 'UTF-8') >= 2) {
            $ta[] = $word;
        }
    }
    $tb = array();
    foreach(explode(' ', $b) as $word) {
        if(mb_strlen($word, 'UTF-8') >= 2) {
            $tb[$word] = true;
        }
    }
    if($ta === array() || $tb === array()) {
        return false;
    }
    $overlap = 0;
    foreach($ta as $word) {
        if(isset($tb[$word])) {
            $overlap++;
        }
    }
    return ($overlap / max(count($ta), count($tb))) >= 0.75;
}

/**
 * Decide import action for a registration number (never overwrite existing rows).
 *
 * Multiple compositions may share one Inv.-Nr. (Heftchen). Skip only when the
 * same piece is already present (exact or slight title match on Prod).
 *
 * @param string $nTitle Normalized import title
 * @param int $reg Registration number from Excel
 * @param array<int, list<array{id:int,title:string,nTitle:string}>> $byReg Existing rows by reg (DB + this run)
 * @param array<int, true> $pendingRegs Unused; kept for call-site compatibility
 * @return array{action:string,reason?:string,existingId?:int,existingTitle?:string}
 */
function archivImportRegDecision($nTitle, $reg, array $byReg, array $pendingRegs) {
    $reg = (int)$reg;
    $nTitle = (string)$nTitle;
    if(!isset($byReg[$reg])) {
        return array('action' => 'ok');
    }
    foreach($byReg[$reg] as $existing) {
        if($existing['nTitle'] === $nTitle) {
            return array(
                'action' => 'skip',
                'reason' => 'same_piece',
                'existingId' => (int)$existing['id'],
                'existingTitle' => (string)$existing['title'],
            );
        }
        if(archivWorkTitlesLikelySame($nTitle, (string)$existing['nTitle'])) {
            return array(
                'action' => 'skip',
                'reason' => (int)$existing['id'] > 0 ? 'same_piece_title_variant' : 'same_piece',
                'existingId' => (int)$existing['id'],
                'existingTitle' => (string)$existing['title'],
            );
        }
    }
    return array('action' => 'ok');
}

/** Write conflict report as TSV (UTF-8). */
function archivImportWriteConflictReport($path, array $conflicts) {
    $path = (string)$path;
    if($path === '') {
        return false;
    }
    $fh = fopen($path, 'wb');
    if($fh === false) {
        return false;
    }
    fwrite($fh, "excel_row\tregistration_number\timport_title\texisting_id\texisting_title\treason\n");
    foreach($conflicts as $c) {
        $line = array(
            (int)$c['row'],
            (int)$c['registrationNumber'],
            (string)$c['importTitle'],
            isset($c['existingId']) ? (int)$c['existingId'] : '',
            (string)($c['existingTitle'] ?? ''),
            (string)$c['reason'],
        );
        fputcsv($fh, $line, "\t");
    }
    fclose($fh);
    return true;
}
