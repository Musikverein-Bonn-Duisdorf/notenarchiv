<?php
/**
 * Parse score PDF basenames into instrument token + voice label (ARCHIV-11).
 * Matching against Melde Instrument rows happens in the indexer.
 */
class ScoreFilenameParser
{
    /** @var array<string, string> normalized alias → preferred instrument token */
    private static $aliases = array(
        'flute' => 'flute',
        'fluit' => 'flute',
        'floete' => 'flute',
        'flote' => 'flute',
        'flöte' => 'flute',
        'piccolo' => 'piccolo',
        'cpiccolo' => 'piccolo',
        'oboe' => 'oboe',
        'bassoon' => 'bassoon',
        'fagott' => 'bassoon',
        'clarinet' => 'clarinet',
        'klarinette' => 'clarinet',
        'bbclarinet' => 'clarinet',
        'ebclarinet' => 'ebclarinet',
        'ebklarinette' => 'ebclarinet',
        'bassclarinet' => 'bassclarinet',
        'bbbassclarinet' => 'bassclarinet',
        'basclarinet' => 'bassclarinet',
        'bassklarinette' => 'bassclarinet',
        'altoclarinet' => 'altoclarinet',
        'altklarinette' => 'altoclarinet',
        'sopranosax' => 'sopranosax',
        'sopranosaxophone' => 'sopranosax',
        'altosaxophone' => 'altosax',
        'altosax' => 'altosax',
        'altsax' => 'altosax',
        'ebaltsax' => 'altosax',
        'ebaltsaxofoon' => 'altosax',
        'tenorsaxophone' => 'tenorsax',
        'tenorsax' => 'tenorsax',
        'baritonesaxophone' => 'barisax',
        'baritonesax' => 'barisax',
        'barisax' => 'barisax',
        'trumpet' => 'trumpet',
        'trompete' => 'trumpet',
        'cornet' => 'cornet',
        'flugelhorn' => 'flugelhorn',
        'horn' => 'horn',
        'frenchhorn' => 'horn',
        'trombone' => 'trombone',
        'posaune' => 'trombone',
        'euphonium' => 'euphonium',
        'baritone' => 'baritone',
        'baryton' => 'baritone',
        'tuba' => 'tuba',
        'bass' => 'bass',
        'stringbass' => 'bass',
        'percussion' => 'percussion',
        'drums' => 'percussion',
        'englishhorn' => 'englishhorn',
        'timpani' => 'timpani',
        'pauke' => 'timpani',
        'mallets' => 'mallets',
        'keyboard' => 'keyboard',
        'piano' => 'piano',
        'harp' => 'harp',
        'guitar' => 'guitar',
        'gesang' => 'voice',
        'voice' => 'voice',
        'vocal' => 'voice',
        'solo' => 'solo',
        'score' => 'score',
        'unknown' => 'unknown',
    );

    /**
     * @return array{ok:bool,kind?:string,instrumentToken?:string,voiceLabel?:string,reason?:string}
     */
    public static function parse($basename) {
        $basename = (string)$basename;
        $name = pathinfo($basename, PATHINFO_FILENAME);
        if($name === '') {
            return array('ok' => false, 'reason' => 'empty');
        }

        if(preg_match('/^doc\d+/i', $name)) {
            return array('ok' => false, 'reason' => 'scan_name');
        }

        $score = self::detectScore($name);
        if($score !== null) {
            return $score;
        }

        $stripped = self::stripLeadingCatalogPrefix($name);

        // Canonical mirror names: "01_Flute_1", "03_Clarinet_1-2", "00_Score", "12_Baritone_BC"
        if(preg_match('/^(?:\d{1,3}_)?([A-Za-z]+)(?:_([\w\-]+))?$/u', $stripped, $m)) {
            $token = self::normalizeToken($m[1]);
            if($token === 'score' || isset(self::$aliases[$token]) || self::looksLikeInstrument($token)) {
                $voice = isset($m[2]) && $m[2] !== '' ? $m[2] : '1';
                $kind = ($token === 'score') ? 'score' : 'part';
                return array(
                    'ok' => true,
                    'kind' => $kind,
                    'instrumentToken' => isset(self::$aliases[$token]) ? self::$aliases[$token] : $token,
                    'voiceLabel' => $voice,
                );
            }
        }

        // Legacy: "24 - 03_BbClarinet1" / "03_BbClarinet1" / "01_FluteI-II"
        if(preg_match('/^(?:\d+\s*[-_.]\s*)?(\d{1,3})[_-](.+)$/u', $stripped, $m)) {
            $parsed = self::parseInstrumentVoiceToken($m[2]);
            if($parsed !== null) {
                return $parsed;
            }
        }

        // Underscore form without leading index: "BbClarinet1", "FluteI-II"
        $parsed = self::parseInstrumentVoiceToken($stripped);
        if($parsed !== null) {
            return $parsed;
        }

        // "Miss Saigon Alto Saxophone 1" / "Napoleon Marsch - Alto Sax 1"
        $tail = self::stripTitlePrefix($stripped);
        $parsed = self::parseSpacedInstrument($tail);
        if($parsed !== null) {
            return $parsed;
        }

        $parsed = self::parseSpacedInstrument($stripped);
        if($parsed !== null) {
            return $parsed;
        }

        return array('ok' => false, 'reason' => 'unmatched');
    }

    /**
     * Best Melde instrument match for a parser token.
     *
     * @param array<int, array{id:int,name:string,norm:string}> $instruments
     * @return array{id:int,name:string}|null
     */
    public static function matchInstrument(array $instruments, $token) {
        $token = self::normalizeToken((string)$token);
        if($token === '') {
            return null;
        }
        $alias = isset(self::$aliases[$token]) ? self::$aliases[$token] : $token;

        $best = null;
        $bestScore = 0;
        foreach($instruments as $inst) {
            $norm = $inst['norm'];
            $score = 0;
            if($norm === $alias || $norm === $token) {
                $score = 100;
            }
            elseif(strpos($norm, $alias) !== false || strpos($alias, $norm) !== false) {
                $score = 70;
            }
            else {
                foreach(explode(' ', $norm) as $word) {
                    if($word === $alias || $word === $token) {
                        $score = max($score, 60);
                    }
                }
            }
            if($score > $bestScore) {
                $bestScore = $score;
                $best = $inst;
            }
        }
        if($bestScore < 60 || $best === null) {
            return null;
        }
        return array('id' => $best['id'], 'name' => $best['name']);
    }

    /**
     * @return array{ok:bool,kind:string,instrumentToken:string,voiceLabel:string}|null
     */
    private static function detectScore($name) {
        $n = self::normalizeToken($name);
        $n = preg_replace('/^\d+/', '', $n);
        if(preg_match('/^(fullscore|condensedscore|score|direktion|partitur|conductor|conductorscore)$/', $n)
            || preg_match('/(fullscore|condensedscore|conductorscore)$/', $n)
            || $n === 'score' || $n === 'direktion' || $n === 'partitur') {
            return array(
                'ok' => true,
                'kind' => 'score',
                'instrumentToken' => 'score',
                'voiceLabel' => '1',
            );
        }
        return null;
    }

    private static function stripLeadingCatalogPrefix($name) {
        // "1 - 01_Flute…" / "24 - 03_Bb…" / "Miss Saigon Alto…"
        if(preg_match('/^\d+\s*[-–]\s+(.+)$/u', $name, $m)) {
            return $m[1];
        }
        return $name;
    }

    private static function stripTitlePrefix($name) {
        if(preg_match('/^(.+?)\s+[-–]\s+(.+)$/u', $name, $m)) {
            return $m[2];
        }
        // Drop leading words until an instrument-like token appears (heuristic: keep last 1–4 tokens)
        $parts = preg_split('/\s+/u', trim($name));
        if($parts === false || count($parts) < 2) {
            return $name;
        }
        for($i = 0; $i < count($parts); $i++) {
            $slice = implode(' ', array_slice($parts, $i));
            if(self::parseSpacedInstrument($slice) !== null) {
                return $slice;
            }
        }
        return $name;
    }

    /**
     * @return array{ok:bool,kind:string,instrumentToken:string,voiceLabel:string}|null
     */
    private static function parseInstrumentVoiceToken($token) {
        $token = trim((string)$token);
        if($token === '') {
            return null;
        }
        // BbClarinet1 / FluteI-II / Klarinette1B / Horn1Es / Solo B
        if(preg_match('/^(.+?)[\s_-]*(I{1,3}|IV|V|VI{0,3}|\d+)(?:\s*[-–]\s*(I{1,3}|IV|V|VI{0,3}|\d+))?([A-Za-z]*)$/u', $token, $m)) {
            $inst = self::normalizeToken($m[1].(isset($m[4]) ? $m[4] : ''));
            // Fix: trailing key like B/Es attached to voice — prefer key on instrument
            if(isset($m[4]) && $m[4] !== '' && preg_match('/^(b|es|as|f|c|eb|bb)$/i', $m[4])) {
                $inst = self::normalizeToken($m[1].$m[4]);
            }
            $voice = self::romanOrDigit($m[2]);
            if(isset($m[3]) && $m[3] !== '') {
                $voice = $voice.'-'.self::romanOrDigit($m[3]);
            }
            if($inst === '' || !self::looksLikeInstrument($inst)) {
                return null;
            }
            return array(
                'ok' => true,
                'kind' => 'part',
                'instrumentToken' => isset(self::$aliases[$inst]) ? self::$aliases[$inst] : $inst,
                'voiceLabel' => $voice,
            );
        }
        // Combined voices without trailing digit: FluteI-II already handled; FluteI II
        $norm = self::normalizeToken($token);
        if(self::looksLikeInstrument($norm) && isset(self::$aliases[$norm])) {
            return array(
                'ok' => true,
                'kind' => 'part',
                'instrumentToken' => self::$aliases[$norm],
                'voiceLabel' => '1',
            );
        }
        return null;
    }

    /**
     * @return array{ok:bool,kind:string,instrumentToken:string,voiceLabel:string}|null
     */
    private static function parseSpacedInstrument($text) {
        $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
        if($text === '') {
            return null;
        }
        // Alto Saxophone 1 / Alto Sax 1 / Bb Clarinet 2 / Klarinette 1 B
        if(preg_match('/^(.+?)\s+(I{1,3}|IV|V|\d+)(?:\s*[-–]\s*(I{1,3}|IV|V|\d+))?(?:\s+([A-Za-z]))?$/u', $text, $m)) {
            $instRaw = $m[1];
            if(isset($m[4]) && $m[4] !== '') {
                $instRaw .= ' '.$m[4];
            }
            $inst = self::normalizeToken($instRaw);
            if(!self::looksLikeInstrument($inst)) {
                return null;
            }
            $voice = self::romanOrDigit($m[2]);
            if(isset($m[3]) && $m[3] !== '') {
                $voice = $voice.'-'.self::romanOrDigit($m[3]);
            }
            return array(
                'ok' => true,
                'kind' => 'part',
                'instrumentToken' => isset(self::$aliases[$inst]) ? self::$aliases[$inst] : $inst,
                'voiceLabel' => $voice,
            );
        }
        $inst = self::normalizeToken($text);
        if(self::looksLikeInstrument($inst)) {
            return array(
                'ok' => true,
                'kind' => 'part',
                'instrumentToken' => isset(self::$aliases[$inst]) ? self::$aliases[$inst] : $inst,
                'voiceLabel' => '1',
            );
        }
        return null;
    }

    private static function looksLikeInstrument($norm) {
        if($norm === '' || strlen($norm) < 3) {
            return false;
        }
        if(isset(self::$aliases[$norm])) {
            return true;
        }
        foreach(array_keys(self::$aliases) as $key) {
            if(strpos($norm, $key) !== false || strpos($key, $norm) !== false) {
                return true;
            }
        }
        return (bool)preg_match('/(flute|oboe|clarinet|sax|trumpet|trombone|horn|tuba|bassoon|percussion|piano|harp|posaune|klarinette|trompete|fagott)/i', $norm);
    }

    public static function normalizeToken($s) {
        if(function_exists('archivMbStrtolower')) {
            $s = archivMbStrtolower((string)$s);
        }
        else {
            $s = function_exists('mb_strtolower')
                ? mb_strtolower((string)$s, 'UTF-8')
                : strtolower((string)$s);
        }
        $s = str_replace(array('ä', 'ö', 'ü', 'ß'), array('ae', 'oe', 'ue', 'ss'), $s);
        $s = preg_replace('/[^a-z0-9]+/', '', $s);
        return (string)$s;
    }

    private static function romanOrDigit($v) {
        $v = strtoupper(trim((string)$v));
        $map = array('I' => '1', 'II' => '2', 'III' => '3', 'IV' => '4', 'V' => '5', 'VI' => '6');
        if(isset($map[$v])) {
            return $map[$v];
        }
        if(preg_match('/^\d+$/', $v)) {
            return (string)(int)$v;
        }
        return $v;
    }
}
?>
