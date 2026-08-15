#!/usr/bin/env php
<?php
/**
 * Enrich compositions with composer / arranger / publisher from catalog pages
 * when the match is unambiguous.
 *
 * Prefers an existing product Website (HeBu / Rundel host). Otherwise resolves
 * via HeBu search (high title score) and reads product meta.
 *
 * Env: ARCHIV_API_BASE, ARCHIV_API_TOKEN
 * Usage: php scripts/enrichCompositionMetaApi.php [--limit=100] [--dry-run] [--create-composers]
 */
if(PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root.'/scripts/lib/publisherCoverFetch.php';

$base = rtrim((string)getenv('ARCHIV_API_BASE'), '/');
$token = (string)getenv('ARCHIV_API_TOKEN');
if($base === '' || $token === '') {
    fwrite(STDERR, "Set ARCHIV_API_BASE + ARCHIV_API_TOKEN\n");
    exit(1);
}

$limit = 100;
$dryRun = in_array('--dry-run', $argv, true);
$createComposers = in_array('--create-composers', $argv, true);
foreach($argv as $arg) {
    if(strpos($arg, '--limit=') === 0) {
        $limit = max(1, min(600, (int)substr($arg, 8)));
    }
}

function apiRequest($method, $path, $token, $base, $json = null) {
    $url = $base.$path;
    $ch = curl_init($url);
    $headers = array(
        'Authorization: Bearer '.$token,
        'X-Archiv-Token: '.$token,
    );
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

function apiCollectByLetters($pathBase, $listKey, $token, $base) {
    $letters = array_merge(
        range('a', 'z'),
        array('ä', 'ö', 'ü', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', ' ')
    );
    $out = array();
    $seen = array();
    foreach($letters as $letter) {
        list($code, $resp) = apiRequest('GET', $pathBase.'limit=200&q='.rawurlencode($letter), $token, $base);
        if($code !== 200 || empty($resp['ok'])) {
            continue;
        }
        foreach(($resp[$listKey] ?? array()) as $row) {
            $id = (int)($row['id'] ?? 0);
            if($id < 1 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $row;
        }
    }
    return $out;
}

function nameKey($first, $last) {
    $first = mb_strtolower(trim((string)$first), 'UTF-8');
    $last = mb_strtolower(trim((string)$last), 'UTF-8');
    return $last.'|'.$first;
}

list($code, $me) = apiRequest('GET', '/me.php', $token, $base);
if($code !== 200 || empty($me['ok'])) {
    fwrite(STDERR, "Auth failed: ".json_encode($me)."\n");
    exit(1);
}
fwrite(STDERR, 'API OK as '.($me['user']['name'] ?? '?')
    ." limit={$limit}".($dryRun ? ' dry-run' : '').($createComposers ? ' create-composers' : '')."\n");

list($code, $plist) = apiRequest('GET', '/publishers.php?limit=200', $token, $base);
$publishersByKey = array();
foreach(($plist['publishers'] ?? array()) as $row) {
    $key = archivNormalizePublisherLabel($row['name']);
    if($key === '') {
        continue;
    }
    // Prefer first unique key; skip collisions
    if(isset($publishersByKey[$key]) && (int)$publishersByKey[$key]['id'] !== (int)$row['id']) {
        unset($publishersByKey[$key]);
        continue;
    }
    $publishersByKey[$key] = $row;
}
// Explicit slug keys already covered by normalize

$composerRows = apiCollectByLetters('/composers.php?', 'composers', $token, $base);
$composersByLast = array();
foreach($composerRows as $row) {
    $last = mb_strtolower(trim((string)$row['lastName']), 'UTF-8');
    if($last === '') {
        continue;
    }
    if(!isset($composersByLast[$last])) {
        $composersByLast[$last] = array();
    }
    $composersByLast[$last][] = $row;
}
fwrite(STDERR, 'Loaded publishers='.count($publishersByKey).' composers='.count($composerRows)."\n");

$resolveComposerId = function ($fullName) use (&$composersByLast, &$composerRows, $createComposers, $dryRun, $token, $base) {
    $parts = archivSplitPersonName($fullName);
    if($parts === null) {
        return array(null, 'skip-name');
    }
    $lastKey = mb_strtolower($parts['last'], 'UTF-8');
    $firstKey = mb_strtolower($parts['first'], 'UTF-8');
    $cands = $composersByLast[$lastKey] ?? array();
    if(count($cands) === 0) {
        if(!$createComposers || !empty($parts['single']) || $parts['first'] === '') {
            return array(null, 'no-composer');
        }
        if($dryRun) {
            return array(-1, 'would-create');
        }
        list($code, $res) = apiRequest('POST', '/composers.php', $token, $base, array(
            'firstName' => $parts['first'],
            'lastName' => $parts['last'],
        ));
        if(empty($res['ok']) || empty($res['id'])) {
            return array(null, 'create-fail');
        }
        $row = array(
            'id' => (int)$res['id'],
            'firstName' => $parts['first'],
            'lastName' => $parts['last'],
        );
        $composerRows[] = $row;
        $composersByLast[$lastKey][] = $row;
        return array((int)$res['id'], 'created');
    }
    if(count($cands) === 1) {
        $only = $cands[0];
        $candFirst = mb_strtolower(trim((string)$only['firstName']), 'UTF-8');
        if($firstKey !== '' && $candFirst !== '' && $firstKey !== $candFirst) {
            // First names disagree → not unique enough
            // Allow if one starts with the other (J vs Jay) or initial match
            $ok = false;
            if(mb_strlen($firstKey, 'UTF-8') === 1 && str_starts_with($candFirst, $firstKey)) {
                $ok = true;
            } elseif(mb_strlen($candFirst, 'UTF-8') === 1 && str_starts_with($firstKey, $candFirst)) {
                $ok = true;
            } elseif(str_starts_with($candFirst, $firstKey) || str_starts_with($firstKey, $candFirst)) {
                $ok = true;
            }
            if(!$ok) {
                return array(null, 'name-mismatch');
            }
        }
        return array((int)$only['id'], 'matched');
    }
    // Multiple same last name → require first-name disambiguation
    $matched = array();
    foreach($cands as $c) {
        $candFirst = mb_strtolower(trim((string)$c['firstName']), 'UTF-8');
        if($firstKey === '') {
            continue;
        }
        if($candFirst === $firstKey
            || (mb_strlen($firstKey, 'UTF-8') === 1 && str_starts_with($candFirst, $firstKey))
            || (mb_strlen($candFirst, 'UTF-8') === 1 && str_starts_with($firstKey, $candFirst))
            || str_starts_with($candFirst, $firstKey)
            || str_starts_with($firstKey, $candFirst)
        ) {
            $matched[] = $c;
        }
    }
    if(count($matched) === 1) {
        return array((int)$matched[0]['id'], 'matched');
    }
    return array(null, 'ambiguous');
};

$resolvePublisherId = function ($labelOrKey) use ($publishersByKey) {
    $key = archivNormalizePublisherLabel($labelOrKey);
    if($key === '' || !isset($publishersByKey[$key])) {
        // Try raw HeBu slug key already normalized
        if(isset($publishersByKey[$labelOrKey])) {
            return (int)$publishersByKey[$labelOrKey]['id'];
        }
        return null;
    }
    return (int)$publishersByKey[$key]['id'];
};

$comps = apiCollectByLetters('/compositions.php?', 'compositions', $token, $base);
fwrite(STDERR, 'Loaded compositions='.count($comps)."\n");

$need = array();
foreach($comps as $c) {
    $needsPub = empty($c['publisherId']);
    $needsCmp = empty($c['composerId']);
    $needsArr = empty($c['arrangerId']);
    if($needsPub || $needsCmp || $needsArr) {
        $need[] = $c;
    }
}
fwrite(STDERR, 'Need enrichment='.count($need)." (processing up to {$limit})\n");

$stats = array(
    'processed' => 0,
    'patched' => 0,
    'skip' => 0,
    'pub' => 0,
    'cmp' => 0,
    'arr' => 0,
    'created' => 0,
    'fail' => 0,
);

foreach(array_slice($need, 0, $limit) as $comp) {
    $stats['processed']++;
    $id = (int)$comp['id'];
    $title = (string)$comp['title'];
    $website = isset($comp['website']) ? trim((string)$comp['website']) : '';

    $meta = null;
    $source = '';

    if($website !== '' && stripos($website, 'hebu-music.com') !== false) {
        $meta = archivFetchHebuProductMeta($website);
        $source = 'hebu-page';
    } elseif($website !== '' && stripos($website, 'rundel.de') !== false) {
        $meta = array(
            'composer' => null,
            'arranger' => null,
            'publisher' => 'Musikverlag Rundel GmbH',
            'page' => $website,
        );
        $source = 'rundel-host';
        // Try HeBu for people only when the HeBu hit is the Rundel edition
        $hit = archivCoverFromHebu($title, 96.0);
        if($hit && !empty($hit['page']) && stripos($hit['page'], 'hebu-music.com') !== false) {
            $hebuMeta = archivFetchHebuProductMeta($hit['page']);
            if($hebuMeta) {
                $pubKey = archivNormalizePublisherLabel((string)($hebuMeta['publisher'] ?? ''));
                if($pubKey === 'rundel') {
                    $meta['composer'] = $hebuMeta['composer'];
                    $meta['arranger'] = $hebuMeta['arranger'];
                    $source = 'rundel-host+hebu-people';
                }
            }
        }
    } else {
        $hit = archivResolvePublisherCover($title, '');
        if($hit && ($hit['score'] ?? 0) >= 95.0 && !empty($hit['page'])) {
            if(stripos($hit['page'], 'hebu-music.com') !== false) {
                $meta = archivFetchHebuProductMeta($hit['page']);
                $source = 'hebu-search';
            } elseif(stripos($hit['page'], 'rundel.de') !== false) {
                $meta = array(
                    'composer' => null,
                    'arranger' => null,
                    'publisher' => 'Musikverlag Rundel GmbH',
                    'page' => $hit['page'],
                );
                $source = 'rundel-search';
            }
        }
    }

    if($meta === null) {
        fwrite(STDOUT, "{$id} {$title} … no catalog meta\n");
        $stats['skip']++;
        continue;
    }

    $patch = array('id' => $id);
    $notes = array();

    if(empty($comp['publisherId']) && !empty($meta['publisher'])) {
        $pubId = $resolvePublisherId($meta['publisher']);
        if($pubId) {
            $patch['publisherId'] = $pubId;
            $notes[] = 'pub='.$pubId;
            $stats['pub']++;
        } else {
            $notes[] = 'pub?='.$meta['publisher'];
        }
    } elseif(!empty($comp['publisherId']) && !empty($meta['publisher'])) {
        // Correct publisher when catalog maps and differs
        $pubId = $resolvePublisherId($meta['publisher']);
        if($pubId && $pubId !== (int)$comp['publisherId'] && $source !== 'rundel-host+hebu-people') {
            // Only auto-correct when meta came from the same product page we trust
            if(in_array($source, array('hebu-page', 'hebu-search', 'rundel-host', 'rundel-search'), true)) {
                $patch['publisherId'] = $pubId;
                $notes[] = 'pubFix='.$pubId;
                $stats['pub']++;
            }
        }
    }

    if(empty($comp['composerId']) && !empty($meta['composer'])) {
        list($cid, $how) = $resolveComposerId($meta['composer']);
        if($cid && $cid > 0) {
            $patch['composerId'] = $cid;
            $notes[] = 'cmp='.$cid.'('.$how.')';
            $stats['cmp']++;
            if($how === 'created') {
                $stats['created']++;
            }
        } elseif($cid === -1) {
            $notes[] = 'cmp=would-create:'.$meta['composer'];
        } else {
            $notes[] = 'cmp?='.$meta['composer'].'('.$how.')';
        }
    }

    if(empty($comp['arrangerId']) && !empty($meta['arranger'])) {
        list($aid, $how) = $resolveComposerId($meta['arranger']);
        if($aid && $aid > 0) {
            $patch['arrangerId'] = $aid;
            $notes[] = 'arr='.$aid.'('.$how.')';
            $stats['arr']++;
            if($how === 'created') {
                $stats['created']++;
            }
        } elseif($aid === -1) {
            $notes[] = 'arr=would-create:'.$meta['arranger'];
        } else {
            $notes[] = 'arr?='.$meta['arranger'].'('.$how.')';
        }
    }

    // Also set website when we discovered a product page and piece has none
    if($website === '' && !empty($meta['page'])) {
        $patch['website'] = $meta['page'];
        $notes[] = 'url';
    }

    if(count($patch) === 1) {
        fwrite(STDOUT, "{$id} {$title} … nothing unambiguous [{$source}] ".implode(' ', $notes)."\n");
        $stats['skip']++;
        continue;
    }

    fwrite(STDOUT, "{$id} {$title} … {$source} ".implode(' ', $notes));
    if($dryRun) {
        fwrite(STDOUT, " (dry-run)\n");
        $stats['patched']++;
        continue;
    }
    list($code, $res) = apiRequest('PATCH', '/compositions.php', $token, $base, $patch);
    if(empty($res['ok'])) {
        fwrite(STDOUT, " PATCH-FAIL ".json_encode($res)."\n");
        $stats['fail']++;
    } else {
        fwrite(STDOUT, " ok\n");
        $stats['patched']++;
    }
    usleep(120000);
}

fwrite(STDERR, 'Done. '.json_encode($stats)."\n");
?>
