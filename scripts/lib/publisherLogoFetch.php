<?php
/**
 * Resolve publisher logos/icons from the publisher website (and favicon fallbacks).
 */

if(!function_exists('archivLogoNormalizeGd')) {
    $avatarLib = dirname(__DIR__, 2).'/libs/entityAvatar.php';
    if(is_file($avatarLib)) {
        require_once $avatarLib;
    }
}

if(!function_exists('archivLogoHttp')) {

function archivLogoHttp($url, $timeout = 25) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => (int)$timeout,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; NotenarchivLogoBot/1.0; +https://github.com/Musikverein-Bonn-Duisdorf/notenarchiv)',
        CURLOPT_HTTPHEADER => array(
            'Accept: text/html,image/*,*/*;q=0.8',
            'Accept-Language: de,en;q=0.9',
        ),
    ));
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $eff = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return array($code, is_string($body) ? $body : '', $ctype, $eff);
}

function archivLogoAbsUrl($base, $href) {
    $href = html_entity_decode(trim((string)$href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if($href === '' || str_starts_with($href, 'data:')) {
        return '';
    }
    if(preg_match('#^https?://#i', $href)) {
        return $href;
    }
    if(str_starts_with($href, '//')) {
        return 'https:'.$href;
    }
    $p = parse_url($base);
    if(empty($p['host'])) {
        return '';
    }
    $origin = ($p['scheme'] ?? 'https').'://'.$p['host'];
    if(str_starts_with($href, '/')) {
        return $origin.$href;
    }
    $dir = isset($p['path']) ? preg_replace('#/[^/]*$#', '/', $p['path']) : '/';
    return $origin.$dir.$href;
}

function archivLogoHostFromWebsite($website) {
    $website = trim((string)$website);
    if($website === '') {
        return '';
    }
    if(!preg_match('#^https?://#i', $website)) {
        $website = 'https://'.$website;
    }
    $host = parse_url($website, PHP_URL_HOST);
    if(!is_string($host) || $host === '') {
        return '';
    }
    return mb_strtolower($host, 'UTF-8');
}

/**
 * Collect candidate logo URLs from HTML, scored higher for larger apple-touch / logo assets.
 *
 * @return list<array{url:string,score:int,kind:string}>
 */
function archivLogoCandidatesFromHtml($html, $baseUrl) {
    $cands = array();
    $add = function($url, $score, $kind) use (&$cands) {
        $url = (string)$url;
        if($url === '' || preg_match('/\.svg(\?|$)/i', $url)) {
            return; // GD cannot load SVG reliably
        }
        if(preg_match('/ups_logo|amazon_pay|payment|facebook|twitter|instagram|youtube|sprite|pixel|1x1|tracking/i', $url)) {
            return;
        }
        $cands[] = array('url' => $url, 'score' => (int)$score, 'kind' => $kind);
    };

    // <link rel="…icon…">
    if(preg_match_all('#<link\b[^>]*>#i', $html, $tags)) {
        foreach($tags[0] as $tag) {
            if(!preg_match('#\brel=["\']([^"\']+)["\']#i', $tag, $rm)) {
                continue;
            }
            $rel = strtolower($rm[1]);
            if(strpos($rel, 'icon') === false) {
                continue;
            }
            if(!preg_match('#\bhref=["\']([^"\']+)["\']#i', $tag, $hm)) {
                continue;
            }
            $href = archivLogoAbsUrl($baseUrl, $hm[1]);
            $score = 40;
            if(strpos($rel, 'apple-touch-icon') !== false) {
                $score = 90;
            }
            if(preg_match('#\bsizes=["\'](\d+)x(\d+)["\']#i', $tag, $sm)) {
                $score += min(40, (int)$sm[1] / 4);
            } elseif(preg_match('#(\d{2,3})x\1#', $href, $sm)) {
                $score += min(40, (int)$sm[1] / 4);
            }
            if(preg_match('/favicon\.ico/i', $href)) {
                $score = min($score, 35);
            }
            $add($href, $score, 'icon');
        }
    }

    // logo-ish <img>
    if(preg_match_all('#<img\b[^>]*>#i', $html, $tags)) {
        foreach($tags[0] as $tag) {
            $src = '';
            if(preg_match('#\b(?:src|data-src)=["\']([^"\']+)["\']#i', $tag, $sm)) {
                $src = $sm[1];
            }
            if($src === '') {
                continue;
            }
            $blob = strtolower($tag.' '.$src);
            if(!preg_match('/logo|brand|verlag|publisher/i', $blob)) {
                continue;
            }
            if(preg_match('/footer|partner|payment|social|badge|award|swissmade|freeshipping/i', $blob)) {
                continue;
            }
            $href = archivLogoAbsUrl($baseUrl, $src);
            $score = 80;
            if(preg_match('/logo[-_]?(header|main|site|primary)?/i', $src)) {
                $score = 95;
            }
            $add($href, $score, 'logo');
        }
    }

    // og:image only if path suggests logo/brand (not product/teaser)
    if(preg_match('/property=["\']og:image["\']\s+content=["\']([^"\']+)/i', $html, $m)
        || preg_match('/content=["\']([^"\']+)["\']\s+property=["\']og:image["\']/i', $html, $m)) {
        $href = archivLogoAbsUrl($baseUrl, $m[1]);
        if(preg_match('/logo|brand|icon|apple/i', $href)) {
            $add($href, 70, 'og');
        }
    }

    // Common static paths
    $hostBase = preg_replace('#/[^/]*$#', '/', $baseUrl);
    $origin = (string)(parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https').'://'.(string)parse_url($baseUrl, PHP_URL_HOST);
    foreach(array(
        array($origin.'/apple-touch-icon.png', 85),
        array($origin.'/apple-touch-icon-precomposed.png', 84),
        array($origin.'/icon.png', 60),
        array($origin.'/logo.png', 75),
        array($origin.'/images/logo.png', 75),
        array($origin.'/img/logo.png', 75),
    ) as $row) {
        $add($row[0], $row[1], 'guess');
    }

    usort($cands, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    // Unique by URL
    $seen = array();
    $out = array();
    foreach($cands as $c) {
        if(isset($seen[$c['url']])) {
            continue;
        }
        $seen[$c['url']] = true;
        $out[] = $c;
    }
    return $out;
}

/**
 * Reject icon.horse-style letter placeholders (gray square + single letter).
 */
function archivLogoIsLetterPlaceholder($img) {
    $w = imagesx($img);
    $h = imagesy($img);
    if($w < 32 || $h < 32) {
        return false;
    }
    $colors = array();
    $samples = 0;
    $step = max(1, (int)floor(min($w, $h) / 32));
    for($y = 0; $y < $h; $y += $step) {
        for($x = 0; $x < $w; $x += $step) {
            $rgb = imagecolorat($img, $x, $y);
            $r = ($rgb >> 16) & 0xff;
            $g = ($rgb >> 8) & 0xff;
            $b = $rgb & 0xff;
            $key = ((int)($r / 32)).'-'.((int)($g / 32)).'-'.((int)($b / 32));
            $colors[$key] = isset($colors[$key]) ? $colors[$key] + 1 : 1;
            $samples++;
        }
    }
    if($samples < 10) {
        return false;
    }
    arsort($colors);
    $top = array_slice($colors, 0, 3, true);
    $topShare = array_sum($top) / $samples;
    return count($colors) <= 6 && $topShare > 0.92;
}

/**
 * Download and convert to PNG; reject tiny/broken images.
 *
 * @return string|null path to PNG
 */
function archivLogoDownloadPng($url, $minPx = 48) {
    list($code, $bin, $ctype) = array_slice(archivLogoHttp($url, 30), 0, 3);
    if($code < 200 || $code >= 300 || strlen($bin) < 60) {
        return null;
    }
    $img = @imagecreatefromstring($bin);
    if(!$img) {
        return null;
    }
    $w = imagesx($img);
    $h = imagesy($img);
    if($w < $minPx && $h < $minPx) {
        imagedestroy($img);
        return null;
    }
    if(archivLogoIsLetterPlaceholder($img)) {
        imagedestroy($img);
        return null;
    }
    // Upscale tiny icons for list/detail readability, then center on square canvas
    $target = 256;
    if($w < $target && $h < $target) {
        $scale = min($target / max(1, $w), $target / max(1, $h));
        $nw = (int)max(1, round($w * $scale));
        $nh = (int)max(1, round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $dst;
    } elseif($w > 800 || $h > 800) {
        $scale = min(800 / $w, 800 / $h);
        $nw = (int)max(1, round($w * $scale));
        $nh = (int)max(1, round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $dst;
    }
    if(function_exists('archivLogoNormalizeGd')) {
        $img = archivLogoNormalizeGd($img, 512, 0.02);
    }
    $path = tempnam(sys_get_temp_dir(), 'plogo').'.png';
    imagesavealpha($img, true);
    imagepng($img, $path);
    imagedestroy($img);
    return is_file($path) ? $path : null;
}

/**
 * @return array{path:string,source:string,url:string}|null
 */
function archivResolvePublisherLogo($website) {
    $website = trim((string)$website);
    if($website === '') {
        return null;
    }
    if(!preg_match('#^https?://#i', $website)) {
        $website = 'https://'.$website;
    }
    $host = archivLogoHostFromWebsite($website);
    if($host === '') {
        return null;
    }

    list($code, $html, $ctype, $eff) = archivLogoHttp($website, 25);
    $candidates = array();
    if($code >= 200 && $code < 400 && $html !== '' && stripos($ctype, 'html') !== false) {
        $candidates = archivLogoCandidatesFromHtml($html, $eff !== '' ? $eff : $website);
    }

    // Favicon services as lower-priority fallbacks
    $domain = preg_replace('/^www\./', '', $host);
    $candidates[] = array(
        'url' => 'https://icon.horse/icon/'.$domain,
        'score' => 45,
        'kind' => 'icon-horse',
    );
    $candidates[] = array(
        'url' => 'https://www.google.com/s2/favicons?sz=256&domain_url='.rawurlencode('https://'.$host),
        'score' => 20,
        'kind' => 'google-favicon',
    );
    $candidates[] = array(
        'url' => 'https://icons.duckduckgo.com/ip3/'.$domain.'.ico',
        'score' => 15,
        'kind' => 'ddg-favicon',
    );

    usort($candidates, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    $tried = array();
    foreach($candidates as $c) {
        $url = $c['url'];
        if(isset($tried[$url])) {
            continue;
        }
        $tried[$url] = true;
        $min = ($c['score'] >= 70) ? 32 : 16;
        $path = archivLogoDownloadPng($url, $min);
        if($path) {
            return array(
                'path' => $path,
                'source' => $c['kind'],
                'url' => $url,
            );
        }
    }
    return null;
}

} // guard
