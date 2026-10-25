<?php
/**
 * Local score mirror under data/Notenarchiv/ (ARCHIV-11).
 * Source mount is read-only; the app indexes and serves only this copy.
 */
class ScoreMirror
{
    public static function relativeRoot() {
        $opt = isset($GLOBALS['optionsDB']['scoreMirrorDirectory'])
            ? trim((string)$GLOBALS['optionsDB']['scoreMirrorDirectory'])
            : '';
        if($opt === '') {
            $opt = 'data/Notenarchiv/';
        }
        return rtrim(str_replace('\\', '/', $opt), '/').'/';
    }

    public static function absoluteRoot() {
        $rel = self::relativeRoot();
        if(isset($GLOBALS['optionsDB']['dataDirectory']) && $GLOBALS['optionsDB']['dataDirectory'] !== '') {
            $data = rtrim(str_replace('\\', '/', (string)$GLOBALS['optionsDB']['dataDirectory']), '/').'/';
            // scoreMirrorDirectory may be "data/Notenarchiv/" or absolute
            if(preg_match('#^[A-Za-z]:/#', $rel) || strpos($rel, '/') === 0) {
                return $rel;
            }
            if(strpos($rel, 'data/') === 0) {
                $suffix = substr($rel, strlen('data/'));
                return $data.$suffix;
            }
            return $data.$rel;
        }
        $root = dirname(__DIR__);
        return rtrim(str_replace('\\', '/', $root), '/').'/'.ltrim($rel, '/');
    }

    /**
     * Resolve filesystem path for a ScoreFile row (upload under composition or mirror relative).
     * @return string|null absolute path if file exists
     */
    public static function resolveScorePath(ScoreFile $sf, Composition $piece = null) {
        $fp = trim((string)$sf->FilePath);
        if($fp === '') {
            return null;
        }
        $fpNorm = str_replace('\\', '/', $fp);
        $candidates = array();

        if($piece !== null && (int)$piece->Index > 0 && $piece->FilePath) {
            $candidates[] = rtrim(str_replace('\\', '/', $piece->getFilePathPHP()), '/').'/'.ltrim($fpNorm, '/');
        }

        $mirror = rtrim(self::absoluteRoot(), '/').'/';
        if(strpos($fpNorm, 'Notenarchiv/') === 0 || strpos($fpNorm, 'data/Notenarchiv/') === 0) {
            $tail = preg_replace('#^(data/)?Notenarchiv/#', '', $fpNorm);
            $candidates[] = $mirror.ltrim($tail, '/');
        }
        else {
            $candidates[] = $mirror.ltrim($fpNorm, '/');
        }

        if(isset($GLOBALS['optionsDB']['dataDirectory'])) {
            $data = rtrim(str_replace('\\', '/', (string)$GLOBALS['optionsDB']['dataDirectory']), '/').'/';
            $candidates[] = $data.ltrim($fpNorm, '/');
        }

        foreach($candidates as $path) {
            if($path !== '' && is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Parse folder name "{RegistrationNumber} - Title" → registration number or null.
     * @return int|null
     */
    public static function registrationFromFolderName($folderName) {
        $folderName = trim((string)$folderName);
        if(preg_match('/^(\d+)\b/u', $folderName, $m)) {
            return (int)$m[1];
        }
        return null;
    }

    /**
     * @return array<int, array{id:int,name:string,norm:string}>
     */
    public static function loadInstrumentCatalog() {
        $list = array();
        if(!function_exists('archivIdentityInstrumentSelectSql')) {
            return $list;
        }
        $sql = archivIdentityInstrumentSelectSql();
        $dbr = @mysqli_query($GLOBALS['conn'], $sql);
        if(!$dbr) {
            return $list;
        }
        while($row = mysqli_fetch_assoc($dbr)) {
            $name = (string)$row['Name'];
            $list[] = array(
                'id' => (int)$row['Index'],
                'name' => $name,
                'norm' => ScoreFilenameParser::normalizeToken($name),
            );
        }
        return $list;
    }

    /**
     * @return int composition Index or 0
     */
    public static function findCompositionByRegistration($registrationNumber) {
        $registrationNumber = (int)$registrationNumber;
        if($registrationNumber < 1) {
            return 0;
        }
        $sql = sprintf(
            'SELECT `Index` FROM `%sComposition` WHERE `RegistrationNumber` = %d LIMIT 1;',
            $GLOBALS['dbprefix'],
            $registrationNumber
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
        return $row ? (int)$row['Index'] : 0;
    }

    /**
     * Upsert ScoreFile for mirror-relative path.
     * @param array{instrumentId:int,voiceLabel:string} $voice
     * @return array{ok:bool,id?:int,action?:string,error?:string}
     */
    public static function upsertMirrorScore($compositionId, array $voice, $mirrorRelativePath) {
        $compositionId = (int)$compositionId;
        $instrumentId = (int)$voice['instrumentId'];
        $voiceLabel = trim((string)$voice['voiceLabel']);
        $rel = str_replace('\\', '/', (string)$mirrorRelativePath);
        if($compositionId < 1 || $instrumentId < 1 || $voiceLabel === '' || $rel === '') {
            return array('ok' => false, 'error' => 'invalid');
        }

        $sql = sprintf(
            'SELECT `Index` FROM `%sScoreFile` WHERE `Composition` = %d AND `Instrument` = %d AND `VoiceLabel` = "%s" LIMIT 1;',
            $GLOBALS['dbprefix'],
            $compositionId,
            $instrumentId,
            mysqli_real_escape_string($GLOBALS['conn'], $voiceLabel)
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
        $sf = new ScoreFile;
        $action = 'insert';
        if($row && (int)$row['Index'] > 0) {
            $sf->load_by_id((int)$row['Index']);
            $action = 'update';
        }
        $sf->Composition = $compositionId;
        $sf->Instrument = $instrumentId;
        $sf->VoiceLabel = $voiceLabel;
        $sf->FilePath = $rel;
        $sf->NextcloudPath = $rel;
        if(!$sf->save()) {
            return array('ok' => false, 'error' => 'save_failed');
        }
        return array('ok' => true, 'id' => (int)$sf->Index, 'action' => $action);
    }
}
?>
