<?php
/**
 * Resolve composer portrait images (overrides → Rundel → Wikimedia / Wikidata).
 */

if(!function_exists('archivPortraitNormalizeGd')) {
    $avatarLib = dirname(__DIR__, 2).'/libs/entityAvatar.php';
    if(is_file($avatarLib)) {
        require_once $avatarLib;
    }
}

if(!function_exists('archivPortraitHttp')) {

function archivPortraitHttp($url, $timeout = 30) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => (int)$timeout,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; NotenarchivPortraitBot/1.0; +https://github.com/Musikverein-Bonn-Duisdorf/notenarchiv)',
        CURLOPT_HTTPHEADER => array(
            'Accept: text/html,application/json,image/*;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9,de;q=0.8',
        ),
    ));
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return array($code, is_string($body) ? $body : '', $ctype);
}

function archivPortraitNorm($s) {
    $s = html_entity_decode((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = strip_tags($s);
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s);
    return preg_replace('/\s+/u', ' ', trim((string)$s));
}

function archivPortraitScore($want, $have) {
    $a = archivPortraitNorm($want);
    $b = archivPortraitNorm($have);
    if($a === '' || $b === '') {
        return 0.0;
    }
    if($a === $b) {
        return 100.0;
    }
    similar_text($a, $b, $pct);
    return (float)$pct;
}

/**
 * True when text looks like a musician/composer (not football CEO etc.).
 */
function archivPortraitLooksLikeMusician($text) {
    $t = archivPortraitNorm($text);
    if($t === '') {
        return false;
    }
    return (bool)preg_match(
        '/\b(composer|komponist|componist|compositeur|compositore|conductor|dirigent|arrangeur|arranger|musician|musiker|bandmaster|band leader|wind band|concert band|brass band|orchestrat)\b/u',
        $t
    );
}

/**
 * Reject biographies that are clearly another person with the same name.
 */
function archivPortraitLooksLikeWrongPerson($text) {
    $t = archivPortraitNorm($text);
    if($t === '') {
        return false;
    }
    return (bool)preg_match(
        '/\b(football|american football|nfl|soccer|basketball|baseball|ceo|businessman|bishop|politician|novelist|researcher|schoolteacher|public official|actor|actress|singer songwriter)\b/u',
        $t
    );
}

function archivPortraitPreferLarge($url) {
    $url = (string)$url;
    if(preg_match('#^(https://res\.cloudinary\.com/[^/]+/image/upload/)(?:[^/]+/)*(v\d+/.+)$#', $url, $m)) {
        return $m[1].$m[2];
    }
    // Drop Wikipedia thumb width when possible
    $url = preg_replace('#/thumb/(.+)/(\d+px-[^/]+)$#', '/$1', $url);
    // Drop Wikimedia tracking params
    $url = preg_replace('~\?utm_.*$~', '', (string)$url);
    return $url;
}

/**
 * Wikipedia pageimages thumb URL.
 */
function archivPortraitFromWikipedia($pageTitle, $lang = 'en') {
    $pageTitle = trim((string)$pageTitle);
    if($pageTitle === '') {
        return null;
    }
    $lang = preg_replace('/[^a-z]/', '', strtolower($lang)) ?: 'en';
    $url = 'https://'.$lang.'.wikipedia.org/w/api.php?'.http_build_query(array(
        'action' => 'query',
        'titles' => $pageTitle,
        'prop' => 'pageimages|description|pageterms',
        'format' => 'json',
        'pithumbsize' => 800,
        'pilicense' => 'any',
        'wbptterms' => 'description',
    ));
    list($code, $body) = archivPortraitHttp($url, 25);
    if($code !== 200) {
        return null;
    }
    $j = json_decode($body, true);
    if(!is_array($j)) {
        return null;
    }
    foreach(($j['query']['pages'] ?? array()) as $page) {
        if(isset($page['missing'])) {
            return null;
        }
        $src = isset($page['thumbnail']['source']) ? (string)$page['thumbnail']['source'] : '';
        if($src === '') {
            return null;
        }
        $desc = '';
        if(!empty($page['description'])) {
            $desc = (string)$page['description'];
        } elseif(!empty($page['terms']['description'][0])) {
            $desc = (string)$page['terms']['description'][0];
        }
        $title = isset($page['title']) ? (string)$page['title'] : $pageTitle;
        if(archivPortraitLooksLikeWrongPerson($title.' '.$desc)) {
            return null;
        }
        // Bare name pages need a musician signal (avoid same-name athletes etc.)
        $disambigComposer = (bool)preg_match('/\((composer|komponist|componist|musician|musiker|dirigent|conductor)/i', $title);
        if(!$disambigComposer && !archivPortraitLooksLikeMusician($desc) && !archivPortraitLooksLikeMusician($title)) {
            return null;
        }
        return array(
            'url' => archivPortraitPreferLarge($src),
            'source' => $lang.'.wikipedia.org',
            'label' => $title,
        );
    }
    return null;
}

/**
 * Commons File:… thumb.
 */
function archivPortraitFromCommons($fileTitle) {
    $fileTitle = preg_replace('#^File:#i', '', trim((string)$fileTitle));
    if($fileTitle === '') {
        return null;
    }
    $url = 'https://commons.wikimedia.org/w/api.php?'.http_build_query(array(
        'action' => 'query',
        'titles' => 'File:'.$fileTitle,
        'prop' => 'imageinfo',
        'iiprop' => 'url|mime',
        'iiurlwidth' => 800,
        'format' => 'json',
    ));
    list($code, $body) = archivPortraitHttp($url, 25);
    if($code !== 200) {
        return null;
    }
    $j = json_decode($body, true);
    foreach(($j['query']['pages'] ?? array()) as $page) {
        if(isset($page['missing'])) {
            return null;
        }
        $ii = $page['imageinfo'][0] ?? null;
        if(!$ii) {
            return null;
        }
        $src = isset($ii['thumburl']) ? (string)$ii['thumburl'] : (string)$ii['url'];
        return array(
            'url' => archivPortraitPreferLarge($src),
            'source' => 'commons.wikimedia.org',
            'label' => $fileTitle,
        );
    }
    return null;
}

/**
 * Rundel person catalog portrait (Cloudinary /rundel/persons/).
 */
function archivPortraitFromRundel($fullName) {
    $fullName = trim((string)$fullName);
    if($fullName === '') {
        return null;
    }
    list($code, $html) = archivPortraitHttp('https://www.rundel.de/de/search?q='.rawurlencode($fullName), 30);
    if($code !== 200 || $html === '') {
        return null;
    }
    $best = null;
    $bestScore = 0.0;
    if(preg_match_all('#href="(/de/person/[a-z0-9_]+/\d+)"[^>]*>([^<]{2,80})</a>#i', $html, $m, PREG_SET_ORDER)) {
        foreach($m as $row) {
            $label = trim(html_entity_decode($row[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if(preg_match('/university|orchestra|band|winds|verschiedene|ensemble|choir|korps|conservat|symphonic/i', $label)) {
                continue;
            }
            $score = archivPortraitScore($fullName, $label);
            if($score > $bestScore) {
                $bestScore = $score;
                $best = array('path' => $row[1], 'title' => $label, 'score' => $score);
            }
        }
    }
    if($best === null || $bestScore < 88.0) {
        return null;
    }
    list($code2, $page) = archivPortraitHttp('https://www.rundel.de'.$best['path'], 30);
    if($code2 !== 200) {
        return null;
    }
    $img = null;
    if(preg_match('/property=["\']og:image["\']\s+content=["\']([^"\']+)/i', $page, $mm)) {
        $img = $mm[1];
    }
    if($img === null || !preg_match('#/rundel/persons/#', $img)) {
        if(preg_match('#(https://res\.cloudinary\.com/pim-red/image/upload/[^"\']+/rundel/persons/[^"\']+\.(?:jpg|jpeg|png|webp))#i', $page, $mm)) {
            $img = $mm[1];
        }
    }
    if($img === null || !preg_match('#/rundel/persons/#', $img)) {
        return null;
    }
    return array(
        'url' => archivPortraitPreferLarge($img),
        'source' => 'rundel.de',
        'label' => $best['title'],
        'score' => $bestScore,
    );
}

/**
 * Exact Wikipedia titles across languages / disambiguators.
 *
 * @return array{url:string,source:string,label?:string}|null
 */
function archivPortraitFromWikipediaExact($fullName) {
    $fullName = trim((string)$fullName);
    if($fullName === '') {
        return null;
    }
    // Prefer disambiguated composer titles first (fewer false same-name hits)
    $attempts = array(
        array('en', $fullName.' (composer)'),
        array('de', $fullName.' (Komponist)'),
        array('nl', $fullName.' (componist)'),
        array('en', $fullName.' (musician)'),
        array('en', $fullName),
        array('de', $fullName),
        array('nl', $fullName),
        array('ja', $fullName),
        array('fr', $fullName.' (compositeur)'),
        array('fr', $fullName),
        array('it', $fullName),
        array('es', $fullName),
    );
    foreach($attempts as $row) {
        $hit = archivPortraitFromWikipedia($row[1], $row[0]);
        if($hit) {
            return $hit;
        }
    }
    return null;
}

/**
 * Wikipedia full-text search with pageimages (broader than exact title).
 *
 * @return array{url:string,source:string,label?:string}|null
 */
function archivPortraitFromWikipediaSearch($fullName) {
    $fullName = trim((string)$fullName);
    if($fullName === '') {
        return null;
    }
    $queriesByLang = array(
        'en' => array($fullName.' composer', $fullName),
        'de' => array($fullName.' Komponist', $fullName),
        'nl' => array($fullName.' componist', $fullName),
        'ja' => array($fullName),
        'fr' => array($fullName.' compositeur', $fullName),
    );
    $best = null;
    $bestScore = 0.0;
    foreach($queriesByLang as $lang => $queries) {
        foreach($queries as $q) {
            $url = 'https://'.$lang.'.wikipedia.org/w/api.php?'.http_build_query(array(
                'action' => 'query',
                'generator' => 'search',
                'gsrsearch' => $q,
                'gsrlimit' => 8,
                'gsrnamespace' => 0,
                'prop' => 'pageimages|description|pageterms',
                'piprop' => 'thumbnail',
                'pithumbsize' => 800,
                'pilicense' => 'any',
                'wbptterms' => 'description',
                'format' => 'json',
            ));
            list($code, $body) = archivPortraitHttp($url, 25);
            if($code !== 200 || $body === '') {
                continue;
            }
            $j = json_decode($body, true);
            if(!is_array($j)) {
                continue;
            }
            foreach(($j['query']['pages'] ?? array()) as $page) {
                $title = isset($page['title']) ? (string)$page['title'] : '';
                $src = isset($page['thumbnail']['source']) ? (string)$page['thumbnail']['source'] : '';
                if($title === '' || $src === '') {
                    continue;
                }
                if(preg_match('/^List of |^Topics referred to|disambiguation/i', $title)) {
                    continue;
                }
                $desc = '';
                if(!empty($page['description'])) {
                    $desc = (string)$page['description'];
                } elseif(!empty($page['terms']['description'][0])) {
                    $desc = (string)$page['terms']['description'][0];
                }
                if(archivPortraitLooksLikeWrongPerson($title.' '.$desc)) {
                    continue;
                }
                $titleForScore = preg_replace('/\s*\([^)]*\)\s*$/u', '', $title);
                $score = archivPortraitScore($fullName, $titleForScore);
                if(archivPortraitLooksLikeMusician($desc) || archivPortraitLooksLikeMusician($title)) {
                    $score += 8.0;
                } elseif($desc !== '' && !archivPortraitLooksLikeMusician($desc)) {
                    $score -= 15.0;
                }
                if($score < 88.0) {
                    continue;
                }
                if($score > $bestScore) {
                    $bestScore = $score;
                    $best = array(
                        'url' => archivPortraitPreferLarge($src),
                        'source' => $lang.'.wikipedia.org',
                        'label' => $title,
                        'score' => $score,
                    );
                }
            }
        }
    }
    return $best;
}

/**
 * Wikidata person → Commons image (P18), preferring composer/conductor.
 *
 * @return array{url:string,source:string,label?:string}|null
 */
function archivPortraitFromWikidata($fullName) {
    $fullName = trim((string)$fullName);
    if($fullName === '') {
        return null;
    }
    $searches = array($fullName, $fullName.' composer', $fullName.' Komponist');
    $candidates = array();
    foreach($searches as $q) {
        $url = 'https://www.wikidata.org/w/api.php?'.http_build_query(array(
            'action' => 'wbsearchentities',
            'search' => $q,
            'language' => 'en',
            'uselang' => 'en',
            'type' => 'item',
            'limit' => 8,
            'format' => 'json',
        ));
        list($code, $body) = archivPortraitHttp($url, 25);
        if($code !== 200 || $body === '') {
            continue;
        }
        $j = json_decode($body, true);
        foreach(($j['search'] ?? array()) as $hit) {
            $id = isset($hit['id']) ? (string)$hit['id'] : '';
            $label = isset($hit['label']) ? (string)$hit['label'] : '';
            $desc = isset($hit['description']) ? (string)$hit['description'] : '';
            if($id === '' || $label === '') {
                continue;
            }
            if(archivPortraitLooksLikeWrongPerson($label.' '.$desc)) {
                continue;
            }
            $score = archivPortraitScore($fullName, $label);
            if(archivPortraitLooksLikeMusician($desc)) {
                $score += 10.0;
            }
            if($score < 88.0) {
                continue;
            }
            if(!isset($candidates[$id]) || $score > $candidates[$id]['score']) {
                $candidates[$id] = array(
                    'id' => $id,
                    'label' => $label,
                    'desc' => $desc,
                    'score' => $score,
                );
            }
        }
    }
    if(!$candidates) {
        return null;
    }
    uasort($candidates, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    $ids = array_slice(array_keys($candidates), 0, 5);
    $url = 'https://www.wikidata.org/w/api.php?'.http_build_query(array(
        'action' => 'wbgetentities',
        'ids' => implode('|', $ids),
        'props' => 'claims|labels|descriptions',
        'languages' => 'en|de',
        'format' => 'json',
    ));
    list($code, $body) = archivPortraitHttp($url, 30);
    if($code !== 200 || $body === '') {
        return null;
    }
    $j = json_decode($body, true);
    $composerOcc = array(
        'Q36834' => true, // composer
        'Q158852' => true, // conductor
        'Q14915627' => true, // music arranger
        'Q639669' => true, // musician
        'Q855091' => true, // guitarist
        'Q486748' => true, // pianist
        'Q12377274' => true, // music director
        'Q1643514' => true, // music educator
    );
    $best = null;
    $bestScore = 0.0;
    foreach(($j['entities'] ?? array()) as $id => $ent) {
        if(!isset($candidates[$id])) {
            continue;
        }
        $score = (float)$candidates[$id]['score'];
        $hasComposerOcc = false;
        foreach(($ent['claims']['P106'] ?? array()) as $claim) {
            $qid = $claim['mainsnak']['datavalue']['value']['id'] ?? '';
            if(isset($composerOcc[$qid])) {
                $hasComposerOcc = true;
                break;
            }
        }
        $desc = $candidates[$id]['desc'];
        if(!$hasComposerOcc && !archivPortraitLooksLikeMusician($desc)) {
            continue;
        }
        if($hasComposerOcc) {
            $score += 5.0;
        }
        $file = $ent['claims']['P18'][0]['mainsnak']['datavalue']['value'] ?? '';
        if($file === '') {
            continue;
        }
        $hit = archivPortraitFromCommons((string)$file);
        if(!$hit) {
            continue;
        }
        if($score > $bestScore) {
            $bestScore = $score;
            $label = $candidates[$id]['label'];
            if(!empty($ent['labels']['en']['value'])) {
                $label = (string)$ent['labels']['en']['value'];
            }
            $best = array(
                'url' => $hit['url'],
                'source' => 'wikidata.org',
                'label' => $label,
                'score' => $score,
            );
        }
    }
    return $best;
}

/**
 * Score a Commons file title against a person name (strict).
 */
function archivPortraitCommonsFileScore($fullName, $fileTitle) {
    $raw = preg_replace('#^File:#i', '', (string)$fileTitle);
    if(preg_match('/\bcropped\b|\(\d{6,}\)|flickr|wikimania|wikimedia conference/i', (string)$raw)) {
        return 0.0;
    }
    $file = preg_replace('/\.[a-z0-9]+$/i', '', (string)$raw);
    $file = str_replace('_', ' ', (string)$file);
    $file = preg_replace('/\s*\([^)]*\)\s*/u', ' ', $file);
    $a = archivPortraitNorm($fullName);
    $b = archivPortraitNorm($file);
    if($a === '' || $b === '') {
        return 0.0;
    }
    if(archivPortraitLooksLikeWrongPerson($b)) {
        return 0.0;
    }
    // Reject group / event / wrong-person photos that merely mention the name
    if(preg_match('/\b(performs|live at|team photo|postcard|jersey|with|whole foods|football|colts|press photo)\b/u', $b)) {
        return 0.0;
    }
    $leftover = trim(preg_replace('/\b'.preg_quote($a, '/').'\b/u', ' ', $b));
    $leftover = preg_replace('/\b(portrait|photo|foto|komponist|composer|headshot)\b/u', ' ', (string)$leftover);
    $leftover = trim(preg_replace('/\s+/u', ' ', (string)$leftover));
    if($leftover !== '' && !preg_match('/^(jr\.?|sr\.?|ii|iii|iv)$/iu', $leftover)) {
        return 0.0;
    }
    $score = archivPortraitScore($fullName, $b);
    if($b === $a || strpos($b, $a) === 0) {
        $score = max($score, 96.0);
    } elseif(strpos($b, $a) !== false && mb_strlen($b, 'UTF-8') <= mb_strlen($a, 'UTF-8') + 18) {
        $score = max($score, 92.0);
    }
    if(mb_strlen($b, 'UTF-8') > mb_strlen($a, 'UTF-8') * 2.2) {
        $score *= 0.65;
    }
    return (float)$score;
}

/**
 * Broader Commons file search by person name.
 *
 * @return array{url:string,source:string,label?:string}|null
 */
function archivPortraitFromCommonsSearch($fullName) {
    $fullName = trim((string)$fullName);
    if($fullName === '') {
        return null;
    }
    $queries = array(
        '"'.$fullName.'"',
        $fullName.' portrait',
        $fullName.' Komponist',
        $fullName,
    );
    $bestTitle = '';
    $bestScore = 0.0;
    foreach($queries as $q) {
        $url = 'https://commons.wikimedia.org/w/api.php?'.http_build_query(array(
            'action' => 'query',
            'list' => 'search',
            'srsearch' => $q,
            'srnamespace' => 6,
            'srlimit' => 10,
            'format' => 'json',
        ));
        list($code, $body) = archivPortraitHttp($url, 25);
        if($code !== 200 || $body === '') {
            continue;
        }
        $j = json_decode($body, true);
        foreach(($j['query']['search'] ?? array()) as $hit) {
            $title = isset($hit['title']) ? (string)$hit['title'] : '';
            if($title === '') {
                continue;
            }
            $score = archivPortraitCommonsFileScore($fullName, $title);
            if($score > $bestScore) {
                $bestScore = $score;
                $bestTitle = $title;
            }
        }
    }
    if($bestTitle === '' || $bestScore < 90.0) {
        return null;
    }
    $hit = archivPortraitFromCommons($bestTitle);
    if(!$hit) {
        return null;
    }
    $hit['score'] = $bestScore;
    return $hit;
}

/**
 * Curated overrides: key "firstname|lastname" (lowercase).
 * Values: wikipedia page, commons file, or direct url.
 *
 * @return array<string, array{wikipedia?:string, wikipediaLang?:string, commons?:string, url?:string, rundelName?:string}>
 */
function archivPortraitOverrides() {
    return array(
        'john philip|sousa' => array('wikipedia' => 'John Philip Sousa'),
        'gustav|holst' => array('wikipedia' => 'Gustav Holst'),
        'percy|grainger' => array('wikipedia' => 'Percy Grainger'),
        'ralph|vaughan williams' => array('wikipedia' => 'Ralph Vaughan Williams'),
        'henry|fillmore' => array('wikipedia' => 'Henry Fillmore'),
        'karl l.|king' => array('rundelName' => 'Karl Lawrence King'),
        'johan|de meij' => array(
            'url' => 'https://johandemeij.com/wp-content/uploads/2025/09/Johan-de-Meij_web-1.jpg',
            'commons' => 'Johan_De_Meij.jpg',
        ),
        'philip|sparke' => array(
            'url' => 'https://images.squarespace-cdn.com/content/v1/5f465c3ec14652238d7092a1/1600862551979-5UE3M5PW1NRBE6A1FF43/Philip+Sparke+Headshot+B%26W.jpg',
            'commons' => 'Philip_Sparke.jpg',
        ),
        'jacob|de haan' => array('rundelName' => 'Jacob de Haan'),
        'jan|van der roost' => array(
            'url' => 'https://janvanderroost.com/wp-content/uploads/2022/10/foto.png',
            'rundelName' => 'Jan Van der Roost',
        ),
        'bert|appermont' => array('rundelName' => 'Bert Appermont'),
        'alfred|reed' => array('commons' => 'Alfred_Reed_portrait.jpg', 'wikipedia' => 'Alfred Reed'),
        'frank|ticheli' => array('commons' => 'Photos_piano-left.jpg', 'wikipedia' => 'Frank Ticheli'),
        'james|barnes' => array('commons' => 'James_C._Barnes.jpg', 'wikipedia' => 'James Barnes (composer)'),
        'david r.|holsinger' => array(
            'url' => 'https://davidrholsinger.com/wp-content/uploads/2020/04/HOLSINGER-CONDUCTOR-1.jpg',
        ),
        'eric|whitacre' => array(
            'url' => 'https://ericwhitacre.com/wp-content/uploads/01-Eric-Whitacre-Credit-Marc-Royce-1000x996.jpg',
            'commons' => 'Ewcolor_cropped.jpg',
        ),
        'otto m.|schwarz' => array('rundelName' => 'Otto M. Schwarz'),
        'thomas|doss' => array('rundelName' => 'Thomas Doss'),
        'franco|cesarini' => array(
            'url' => 'https://www.francocesarini.com/wp-content/uploads/2021/08/franco_cesarini_home_page_composer_1920x1280.jpg',
            'rundelName' => 'Franco Cesarini',
        ),
        'hardy|mertens' => array('wikipedia' => 'Hardy Mertens', 'wikipediaLang' => 'nl', 'rundelName' => 'Hardy Mertens'),
        'kees|vlak' => array('rundelName' => 'Kees Vlak'),
        'clifton|williams' => array('rundelName' => 'Clifton Williams'),
        'robert w.|smith' => array('rundelName' => 'Robert W. Smith'),
        'julie|giroux' => array('wikipedia' => 'Julie Giroux'),
        'brian|balmages' => array(
            'url' => 'https://static.wixstatic.com/media/e76d88_c012f5f1828c4298b78ae6da9c7ddd4e.jpg',
        ),
        'samuel r.|hazo' => array('rundelName' => 'Samuel R. Hazo'),
        'james|curnow' => array('wikipedia' => 'James Curnow', 'commons' => 'James_Curnow.png'),
    );
}

/**
 * @return array{url:string,source:string,label?:string}|null
 */
function archivResolveComposerPortrait($firstName, $lastName) {
    $firstName = trim((string)$firstName);
    $lastName = trim((string)$lastName);
    $full = trim($firstName.' '.$lastName);
    $key = mb_strtolower($firstName.'|'.$lastName, 'UTF-8');
    $over = archivPortraitOverrides();
    $cfg = isset($over[$key]) ? $over[$key] : array();

    // 1) Direct URL override
    if(!empty($cfg['url'])) {
        return array('url' => (string)$cfg['url'], 'source' => 'official', 'label' => $full);
    }
    // 2) Commons override
    if(!empty($cfg['commons'])) {
        $hit = archivPortraitFromCommons($cfg['commons']);
        if($hit) {
            return $hit;
        }
    }
    // 3) Wikipedia override
    if(!empty($cfg['wikipedia'])) {
        $lang = !empty($cfg['wikipediaLang']) ? (string)$cfg['wikipediaLang'] : 'en';
        $hit = archivPortraitFromWikipedia($cfg['wikipedia'], $lang);
        if($hit) {
            return $hit;
        }
    }
    // 4) Publisher catalog (Rundel persons)
    $rundelName = !empty($cfg['rundelName']) ? (string)$cfg['rundelName'] : $full;
    $hit = archivPortraitFromRundel($rundelName);
    if($hit) {
        return $hit;
    }
    // 5+) Broader public sources when publisher pages have no portrait
    $hit = archivPortraitFromWikipediaExact($full);
    if($hit) {
        return $hit;
    }
    $hit = archivPortraitFromWikidata($full);
    if($hit) {
        return $hit;
    }
    $hit = archivPortraitFromWikipediaSearch($full);
    if($hit) {
        return $hit;
    }
    $hit = archivPortraitFromCommonsSearch($full);
    if($hit) {
        return $hit;
    }
    return null;
}

/**
 * Download image to local JPEG temp file.
 */
function archivDownloadPortraitFile($url) {
    list($code, $bin, $ctype) = archivPortraitHttp($url, 60);
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
    // Extreme banners are almost never usable composer portraits
    if(($w / max(1, $h)) > 2.4) {
        imagedestroy($img);
        return null;
    }
    if(function_exists('archivPortraitNormalizeGd')) {
        $img = archivPortraitNormalizeGd($img);
    } else {
        $max = 1200;
        if($w > $max || $h > $max) {
            $scale = min($max / $w, $max / $h);
            $nw = (int)max(1, round($w * $scale));
            $nh = (int)max(1, round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $dst;
        }
    }
    $path = tempnam(sys_get_temp_dir(), 'cport').'.jpg';
    imagejpeg($img, $path, 90);
    imagedestroy($img);
    return is_file($path) ? $path : null;
}

} // guard
