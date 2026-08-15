<?php
/**
 * Best-effort sheet-music cover fetch from publisher / wind-band shop sites.
 *
 * Many publisher frontends are SPAs or bot-blocked. Rundel’s public catalog
 * (rundel.de) exposes searchable HTML with official edition covers (incl.
 * De Haske, Anglo, Amstel, Mitropa, Barnhouse, …) via Cloudinary og:image.
 *
 * Library for CLI scripts only (see scripts/.htaccess + root RedirectMatch).
 */
if(PHP_SAPI !== 'cli') {
    // Direct HTTP hit must not run even if directory deny is misconfigured.
    if(!empty($_SERVER['SCRIPT_FILENAME'])
        && realpath((string)$_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Forbidden\n";
        exit;
    }
}

if(!function_exists('archivCoverFetchHttp')) {

function archivCoverFetchHttp($url, $timeout = 30) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => (int)$timeout,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; NotenarchivCoverBot/1.0; +https://github.com/)',
        CURLOPT_HTTPHEADER => array(
            'Accept: text/html,application/xhtml+xml,image/*;q=0.9,*/*;q=0.8',
            'Accept-Language: de-DE,de;q=0.9,en;q=0.8',
        ),
    ));
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return array($code, is_string($body) ? $body : '', $ctype);
}

function archivCoverNormTitle($s) {
    $s = html_entity_decode((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = strip_tags($s);
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s);
    $s = preg_replace('/\s+/u', ' ', trim((string)$s));
    return $s;
}

function archivCoverTitleScore($want, $have) {
    $a = archivCoverNormTitle($want);
    $b = archivCoverNormTitle($have);
    if($a === '' || $b === '') {
        return 0.0;
    }
    if($a === $b) {
        return 100.0;
    }
    similar_text($a, $b, $pct);
    $la = mb_strlen($a, 'UTF-8');
    $lb = mb_strlen($b, 'UTF-8');
    // Containment boost only when lengths are close (avoids "Elements" → "Essential Jazz Elements")
    if($la >= 8 && $lb >= 8) {
        $ratio = $la <= $lb ? ($la / $lb) : ($lb / $la);
        if($ratio >= 0.72 && (str_contains($b, $a) || str_contains($a, $b))) {
            $pct = max($pct, 92.0);
        }
    }
    return (float)$pct;
}

/**
 * Prefer a larger Cloudinary derivative when og:image is already transformed.
 */
function archivCoverPreferLargeUrl($url) {
    $url = (string)$url;
    if(preg_match('#^(https://res\.cloudinary\.com/[^/]+/image/upload/)(?:[^/]+/)*(v\d+/.+)$#', $url, $m)) {
        return $m[1].$m[2];
    }
    // Stretta / Shopify thumbs → drop size suffix when present
    $url = preg_replace('#_(\d+x\d+)(\.(?:jpg|jpeg|png|webp))#i', '$2', $url);
    return $url;
}

/**
 * Search Rundel catalog; return ['url'=>…,'title'=>…,'score'=>…,'source'=>'rundel'] or null.
 */
function archivCoverFromRundel($title, $minScore = 90.0) {
    $title = trim((string)$title);
    if($title === '') {
        return null;
    }
    list($code, $html) = archivCoverFetchHttp('https://www.rundel.de/de/search?q='.rawurlencode($title));
    if($code !== 200 || $html === '') {
        return null;
    }

    $best = null;
    $bestScore = 0.0;
    $patterns = array(
        array('re' => '#href="(/de/artikel/[^"]+)"[^>]*title="([^"]+)"#i', 'path' => 1, 'title' => 2),
        array('re' => '#title="([^"]+)"[^>]*href="(/de/artikel/[^"]+)"#i', 'path' => 2, 'title' => 1),
    );
    foreach($patterns as $p) {
        if(!preg_match_all($p['re'], $html, $m, PREG_SET_ORDER)) {
            continue;
        }
        foreach($m as $row) {
            $path = $row[$p['path']];
            $hitTitle = html_entity_decode($row[$p['title']], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // Prefer full score set over parts (…AK)
            if(preg_match('/AK$/i', $path)) {
                continue;
            }
            $score = archivCoverTitleScore($title, $hitTitle);
            if($score > $bestScore) {
                $bestScore = $score;
                $best = array('path' => $path, 'title' => $hitTitle, 'score' => $score);
            }
        }
    }
    if($best === null || $bestScore < $minScore) {
        return null;
    }

    list($code2, $page) = archivCoverFetchHttp('https://www.rundel.de'.$best['path']);
    if($code2 !== 200) {
        return null;
    }
    $cover = null;
    if(preg_match('/property=["\']og:image["\']\s+content=["\']([^"\']+)/i', $page, $mm)
        || preg_match('/content=["\']([^"\']+)["\']\s+property=["\']og:image["\']/i', $page, $mm)) {
        $cover = $mm[1];
    }
    if($cover === null || $cover === '') {
        return null;
    }
    if(preg_match('/teaser|logo|og-image|social/i', $cover)) {
        return null;
    }

    return array(
        'url' => archivCoverPreferLargeUrl($cover),
        'title' => $best['title'],
        'score' => $bestScore,
        'source' => 'rundel.de',
        'page' => 'https://www.rundel.de'.$best['path'],
    );
}

/**
 * Generic: try a few search URL patterns on the publisher host, score link text, read og:image.
 *
 * @param string $website Publisher Website URL
 * @param string $title Piece title
 */
function archivCoverFromPublisherSite($website, $title, $minScore = 90.0) {
    $website = trim((string)$website);
    $title = trim((string)$title);
    if($website === '' || $title === '') {
        return null;
    }
    $parts = parse_url($website);
    if(empty($parts['host'])) {
        return null;
    }
    $origin = ($parts['scheme'] ?? 'https').'://'.$parts['host'];
    $host = mb_strtolower($parts['host'], 'UTF-8');
    if(str_starts_with($host, 'www.')) {
        $host = substr($host, 4);
    }

    // Dedicated handlers
    if($host === 'rundel.de') {
        return archivCoverFromRundel($title, $minScore);
    }

    $q = rawurlencode($title);
    $candidates = array(
        $origin.'/de/search?q='.$q,
        $origin.'/search?q='.$q,
        $origin.'/en/search?q='.$q,
        $origin.'/?s='.$q,
        $origin.'/suche?q='.$q,
    );
    $best = null;
    $bestScore = 0.0;
    $bestPage = null;

    foreach($candidates as $searchUrl) {
        list($code, $html) = archivCoverFetchHttp($searchUrl, 20);
        if($code !== 200 || strlen($html) < 500) {
            continue;
        }
        if(!preg_match_all('#<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', $html, $m, PREG_SET_ORDER)) {
            continue;
        }
        foreach($m as $row) {
            $href = html_entity_decode($row[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = trim(preg_replace('/\s+/u', ' ', strip_tags($row[2])));
            if($text === '' || mb_strlen($text, 'UTF-8') < 3) {
                continue;
            }
            $score = archivCoverTitleScore($title, $text);
            if($score < $minScore || $score <= $bestScore) {
                continue;
            }
            if(preg_match('#^(https?:)?//#i', $href)) {
                $abs = $href;
                if(str_starts_with($abs, '//')) {
                    $abs = 'https:'.$abs;
                }
            } else {
                $abs = rtrim($origin, '/').'/'.ltrim($href, '/');
            }
            $absHost = parse_url($abs, PHP_URL_HOST);
            if(!$absHost || !str_ends_with(mb_strtolower($absHost, 'UTF-8'), $host)) {
                continue;
            }
            if(preg_match('/search|suche|login|cart|account|contact/i', $abs)) {
                continue;
            }
            $bestScore = $score;
            $best = $text;
            $bestPage = $abs;
        }
        if($bestPage !== null) {
            break;
        }
    }

    if($bestPage === null) {
        return null;
    }
    list($code2, $page) = archivCoverFetchHttp($bestPage);
    if($code2 !== 200) {
        return null;
    }
    $cover = null;
    if(preg_match('/property=["\']og:image["\']\s+content=["\']([^"\']+)/i', $page, $mm)
        || preg_match('/content=["\']([^"\']+)["\']\s+property=["\']og:image["\']/i', $page, $mm)) {
        $cover = $mm[1];
    }
    if($cover === null || $cover === '' || preg_match('/logo|og-image|social|favicon|sprite/i', $cover)) {
        return null;
    }

    return array(
        'url' => archivCoverPreferLargeUrl($cover),
        'title' => $best,
        'score' => $bestScore,
        'source' => $host,
        'page' => $bestPage,
    );
}

/**
 * Search HeBu catalog (fallback when Rundel is unreachable).
 *
 * @return array{url:string,title:string,score:float,source:string,page:string}|null
 */
function archivCoverFromHebu($title, $minScore = 90.0) {
    $title = trim((string)$title);
    if($title === '') {
        return null;
    }
    $queries = array($title);
    if(preg_match('/^(.+?)\s+[–—-]\s+/u', $title, $m)) {
        $queries[] = trim($m[1]);
    }
    if(preg_match('/^(.+?)\s*\/\s*/u', $title, $m)) {
        $queries[] = trim($m[1]);
    }
    if(preg_match('/^(.+?)\s*\[/u', $title, $m)) {
        $queries[] = trim($m[1]);
    }
    $queries = array_values(array_unique(array_filter($queries)));

    $best = null;
    $bestScore = 0.0;
    foreach($queries as $q) {
        $url = 'https://www.hebu-music.com/de/suche/?s='.rawurlencode($q);
        list($code, $html) = archivCoverFetchHttp($url, 25);
        if($code !== 200 || $html === '') {
            continue;
        }
        if(!preg_match_all(
            '#<img[^>]+src="(https://www\.hebu-music\.com/thumb\.php\?[^"]+)"[^>]*title="([^"]+)"#i',
            $html,
            $imgs,
            PREG_SET_ORDER
        )) {
            continue;
        }
        foreach($imgs as $row) {
            $thumb = html_entity_decode($row[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $hitTitle = html_entity_decode($row[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // Drop bare composer suffix " - Name / Arr. …" for scoring when present
            $scoreTitle = $hitTitle;
            if(preg_match('/^(.+?)\s+-\s+[A-ZÄÖÜ]/i', $hitTitle, $tm)) {
                $scoreTitle = $tm[1];
            }
            if(preg_match('/name=00000000/i', $thumb)) {
                continue;
            }
            $score = max(
                archivCoverTitleScore($title, $hitTitle),
                archivCoverTitleScore($title, $scoreTitle),
                archivCoverTitleScore($q, $scoreTitle)
            );
            if($score < $minScore || $score <= $bestScore) {
                continue;
            }
            $page = '';
            $escThumb = preg_quote($row[1], '#');
            if(preg_match('#href="(https://www\.hebu-music\.com/de/artikel/[^"]+)"[^>]*>\s*<img[^>]+src="'.$escThumb.'"#is', $html, $pm)
                || preg_match('#src="'.$escThumb.'"[^>]*>\s*</a>.*?href="(https://www\.hebu-music\.com/de/artikel/[^"]+)"#is', $html, $pm)) {
                $page = $pm[1];
            } elseif(preg_match('#href="(https://www\.hebu-music\.com/de/artikel/[^/]+/[^/]+/[^"]+\.\d+/)"#i', $html, $pm)) {
                $page = $pm[1];
            }
            $thumb = str_replace('&amp;', '&', $thumb);
            $thumb = preg_replace('/([?&]width=)\d+/i', '${1}800', $thumb);
            $thumb = preg_replace('/([?&]height=)\d+/i', '${1}800', $thumb);
            $bestScore = $score;
            $best = array(
                'url' => $thumb,
                'title' => $hitTitle,
                'score' => $score,
                'source' => 'hebu-music.com',
                'page' => $page !== '' ? $page : $url,
            );
        }
        if($best !== null && $bestScore >= 98.0) {
            break;
        }
    }
    return $best;
}

/**
 * Resolve a cover URL for a piece: publisher site first, then Rundel, then HeBu.
 *
 * @return array{url:string,title:string,score:float,source:string,page?:string}|null
 */
function archivResolvePublisherCover($title, $publisherWebsite = '') {
    $variants = array(trim((string)$title));
    if(preg_match('/^(.+?)\s+[–—-]\s+/u', $title, $m)) {
        $variants[] = trim($m[1]);
    }
    if(preg_match('/^(.+?)\s*\/\s*/u', $title, $m)) {
        $variants[] = trim($m[1]);
    }
    if(preg_match('/^(.+?)\s*\[/u', $title, $m)) {
        $variants[] = trim($m[1]);
    }
    $variants = array_values(array_unique(array_filter($variants)));

    foreach($variants as $i => $variant) {
        $minScore = $i === 0 ? 90.0 : 88.0;
        $hit = null;
        if(trim((string)$publisherWebsite) !== '') {
            $hit = archivCoverFromPublisherSite($publisherWebsite, $variant, $minScore);
        }
        if($hit === null) {
            $hit = archivCoverFromRundel($variant, $minScore);
        }
        if($hit === null) {
            $hit = archivCoverFromHebu($variant, $minScore);
        }
        if($hit !== null) {
            return $hit;
        }
    }
    return null;
}

/**
 * Download image URL to a local temp JPEG/PNG path, or null.
 */
function archivDownloadCoverFile($url) {
    list($code, $bin, $ctype) = archivCoverFetchHttp($url, 60);
    if($code !== 200 || strlen($bin) < 200) {
        return null;
    }
    $img = @imagecreatefromstring($bin);
    if(!$img) {
        return null;
    }
    $w = imagesx($img);
    $h = imagesy($img);
    if($w < 40 || $h < 40) {
        imagedestroy($img);
        return null;
    }
    // Prefer JPEG for covers
    $path = tempnam(sys_get_temp_dir(), 'pcover').'.jpg';
    imagejpeg($img, $path, 90);
    imagedestroy($img);
    return is_file($path) ? $path : null;
}

/**
 * Parse Komponist / Arrangeur / Verlag from a HeBu product HTML page.
 *
 * @return array{composer:?string,arranger:?string,publisher:?string}
 */
function archivParseHebuProductMeta($html) {
    $out = array('composer' => null, 'arranger' => null, 'publisher' => null);
    $html = (string)$html;
    if($html === '') {
        return $out;
    }
    if(preg_match('#Komponist:\s*<a[^>]*>([^<]+)</a>#ui', $html, $m)) {
        $cmp = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if($cmp !== '' && $cmp !== '-') {
            $out['composer'] = $cmp;
        }
    }
    if(preg_match('#Arrangeur:\s*<a[^>]*>([^<]+)</a>#ui', $html, $m)) {
        $arr = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if($arr !== '' && $arr !== '-' && !preg_match('/^arr\.?$/iu', $arr)) {
            $out['arranger'] = $arr;
        }
    }
    if(preg_match('#Verlag:\s*<a[^>]*>([^<]+)</a>#ui', $html, $m)) {
        $out['publisher'] = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if(($out['composer'] === null || $out['arranger'] === null || $out['publisher'] === null)
        && preg_match('#<script type="application/ld\+json">(.+?)</script>#si', $html, $jm)
    ) {
        $j = json_decode($jm[1], true);
        if(is_array($j)) {
            foreach(($j['additionalProperty'] ?? array()) as $prop) {
                $name = isset($prop['name']) ? (string)$prop['name'] : '';
                $val = isset($prop['value']) ? trim((string)$prop['value']) : '';
                if($val === '') {
                    continue;
                }
                if($out['composer'] === null && strcasecmp($name, 'Komponist') === 0
                    && $val !== '' && $val !== '-'
                ) {
                    $out['composer'] = $val;
                }
                if($out['arranger'] === null && strcasecmp($name, 'Arrangeur') === 0
                    && $val !== '' && $val !== '-'
                ) {
                    $out['arranger'] = $val;
                }
                if($out['publisher'] === null && strcasecmp($name, 'Verlag') === 0) {
                    $out['publisher'] = $val;
                }
            }
        }
    }
    return $out;
}

/**
 * Map HeBu / catalog publisher labels (and URL slugs) to a canonical Archiv name key.
 */
function archivNormalizePublisherLabel($label) {
    $s = mb_strtolower(trim((string)$label), 'UTF-8');
    $s = str_replace(array('–', '—'), '-', $s);
    $s = preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $s);
    $s = preg_replace('/\s+/u', ' ', trim((string)$s));
    $aliases = array(
        'mitropa music' => 'mitropa',
        'mitropa' => 'mitropa',
        'musikverlag rundel gmbh' => 'rundel',
        'musikverlag rundel' => 'rundel',
        'rundel' => 'rundel',
        'hebu musikverlag gmbh' => 'hebu',
        'hebu musikverlag' => 'hebu',
        'hebu' => 'hebu',
        'de haske bv' => 'dehaske',
        'de haske' => 'dehaske',
        'de haske publications bv' => 'dehaske',
        'de haske publications' => 'dehaske',
        'anglo music' => 'anglo',
        'anglo music press' => 'anglo',
        'beriato' => 'beriato',
        'beriato music' => 'beriato',
        'molenaar' => 'molenaar',
        'molenaar edition' => 'molenaar',
        'molenaar edition bv' => 'molenaar',
        'c l barnhouse company' => 'barnhouse',
        'barnhouse' => 'barnhouse',
        'hal leonard' => 'halleonard',
        'hal leonard publishing co' => 'halleonard',
        'hal leonard europe' => 'halleonard',
        'musikverlag halter gmbh' => 'halter',
        'musikverlag halter' => 'halter',
        'halter' => 'halter',
        'auren' => 'auren',
        'auren musik' => 'auren',
        'amstel music' => 'dehaske', // Amstel often under De Haske group — skip? safer leave unmapped
    );
    // Do not map Amstel to De Haske — remove that mistaken alias
    unset($aliases['amstel music']);
    if(isset($aliases[$s])) {
        return $aliases[$s];
    }
    $slug = str_replace(' ', '-', $s);
    $slugAliases = array(
        'mitropa-music' => 'mitropa',
        'musikverlag-rundel' => 'rundel',
        'hebu-musikverlag-gmbh' => 'hebu',
        'de-haske-publications-bv' => 'dehaske',
        'de-haske' => 'dehaske',
        'anglo-music-press' => 'anglo',
        'beriato-music' => 'beriato',
        'molenaar-edition-bv' => 'molenaar',
        'c-l-barnhouse-company' => 'barnhouse',
        'hal-leonard-publishing-co' => 'halleonard',
        'musikverlag-halter-gmbh' => 'halter',
    );
    return $slugAliases[$slug] ?? $s;
}

/**
 * Publisher key from HeBu artikel URL path segment.
 */
function archivHebuPublisherKeyFromUrl($url) {
    if(preg_match('#/de/artikel/[^/]+/([^/]+)/#i', (string)$url, $m)) {
        return archivNormalizePublisherLabel(str_replace('-', ' ', $m[1]));
    }
    return '';
}

/**
 * Split "First Middle Last" / "Last, First" into firstName + lastName.
 *
 * @return array{first:string,last:string}|null
 */
function archivSplitPersonName($full) {
    $full = trim(preg_replace('/\s+/u', ' ', (string)$full));
    if($full === '' || $full === '-'
        || preg_match('/^(traditional|trad\.?|anon\.?|anonymous|various|diverse)$/iu', $full)
    ) {
        return null;
    }
    // Compound credits are not a single person
    if(preg_match('/[&;\/]|\\band\\b|\\bund\\b/iu', $full) || str_contains($full, '(')) {
        return null;
    }
    if(str_contains($full, ',')) {
        $parts = array_map('trim', explode(',', $full, 2));
        $last = $parts[0];
        $first = $parts[1] ?? '';
        if($last === '') {
            return null;
        }
        return array('first' => $first, 'last' => $last);
    }
    $parts = preg_split('/\s+/u', $full);
    if(!$parts || count($parts) < 1) {
        return null;
    }
    if(count($parts) === 1) {
        // Single token: only usable for matching existing last names, not for create
        return array('first' => '', 'last' => $parts[0], 'single' => true);
    }
    $last = array_pop($parts);
    return array('first' => implode(' ', $parts), 'last' => $last, 'single' => false);
}

/**
 * Fetch HeBu product meta for a page URL (must be hebu-music.com artikel).
 *
 * @return array{composer:?string,arranger:?string,publisher:?string,page:string}|null
 */
function archivFetchHebuProductMeta($pageUrl) {
    $pageUrl = trim((string)$pageUrl);
    if($pageUrl === '' || stripos($pageUrl, 'hebu-music.com') === false) {
        return null;
    }
    list($code, $html) = archivCoverFetchHttp($pageUrl, 25);
    if($code !== 200 || $html === '') {
        return null;
    }
    $meta = archivParseHebuProductMeta($html);
    if($meta['publisher'] === null) {
        $key = archivHebuPublisherKeyFromUrl($pageUrl);
        if($key !== '') {
            $meta['publisher'] = $key; // normalized key; mapper resolves
        }
    }
    $meta['page'] = $pageUrl;
    return $meta;
}

} // function_exists guard
