<?php
/**
 * Best-effort sheet-music cover fetch from publisher / wind-band shop sites.
 *
 * Many publisher frontends are SPAs or bot-blocked. Rundel’s public catalog
 * (rundel.de) exposes searchable HTML with official edition covers (incl.
 * De Haske, Anglo, Amstel, Mitropa, Barnhouse, …) via Cloudinary og:image.
 */

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
 * Resolve a cover URL for a piece: publisher site first, then Rundel wind catalog.
 *
 * @return array{url:string,title:string,score:float,source:string,page?:string}|null
 */
function archivResolvePublisherCover($title, $publisherWebsite = '') {
    $hit = null;
    if(trim((string)$publisherWebsite) !== '') {
        $hit = archivCoverFromPublisherSite($publisherWebsite, $title, 90.0);
    }
    if($hit === null) {
        $hit = archivCoverFromRundel($title, 90.0);
    }
    return $hit;
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

} // function_exists guard
