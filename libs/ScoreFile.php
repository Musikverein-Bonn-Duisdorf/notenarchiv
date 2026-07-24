<?php
class ScoreFile
{
    private $_data = array(
        'Index' => null,
        'Composition' => null,
        'Instrument' => null,
        'VoiceLabel' => null,
        'NextcloudPath' => null,
        'PageCount' => null,
        'Checksum' => null,
        'FilePath' => null,
        'InstrumentName' => null,
    );

    public function __get($key) {
        if(array_key_exists($key, $this->_data)) {
            return $this->_data[$key];
        }
        return null;
    }

    public function __set($key, $val) {
        switch($key) {
        case 'Index':
        case 'Composition':
        case 'Instrument':
        case 'PageCount':
            $this->_data[$key] = (int)$val;
            break;
        case 'VoiceLabel':
        case 'NextcloudPath':
        case 'Checksum':
        case 'FilePath':
        case 'InstrumentName':
            $this->_data[$key] = $val;
            break;
        default:
            break;
        }
    }

    public function fillJoins() {
        if(!$this->InstrumentName && $this->Instrument) {
            $prefix = identityPrefix();
            $sql = sprintf('SELECT `Name` FROM `%sInstrument` WHERE `Index` = %d LIMIT 1;',
                $prefix,
                $this->Instrument
            );
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
            sqlerror();
            $row = $dbr ? mysqli_fetch_array($dbr) : null;
            if($row) {
                $this->InstrumentName = $row['Name'];
            }
        }
    }

    public function save() {
        if(!$this->is_valid()) return false;
        if($this->Index > 0) {
            $changes = $this->getChanges();
            if(!$this->update()) {
                return false;
            }
            if($changes !== '') {
                $logentry = new Log;
                $logentry->DBupdate($changes);
            }
            return true;
        }
        if(!$this->insert()) {
            return false;
        }
        $logentry = new Log;
        $logentry->DBinsert($this->getVars());
        return true;
    }

    public function getVars() {
        $this->fillJoins();
        $parts = array();
        $parts[] = sprintf('ScoreFile-ID: %d', (int)$this->Index);
        logAppendFilled($parts, 'Composition', $this->Composition, (string)(int)$this->Composition, true);
        logAppendFilled($parts, 'Instrument', $this->InstrumentName !== null && $this->InstrumentName !== ''
            ? $this->InstrumentName
            : $this->Instrument,
            htmlspecialchars((string)($this->InstrumentName !== null && $this->InstrumentName !== '' ? $this->InstrumentName : $this->Instrument), ENT_QUOTES, 'UTF-8'),
            true);
        logAppendFilled($parts, 'Voice', $this->VoiceLabel);
        logAppendFilled($parts, 'FilePath', $this->FilePath);
        return implode(', ', $parts);
    }

    public function getChanges() {
        $old = new ScoreFile;
        $old->load_by_id($this->Index);
        $parts = array();
        logAppendChange($parts, 'Composition', $old->Composition, $this->Composition);
        logAppendChange($parts, 'Instrument', $old->Instrument, $this->Instrument);
        logAppendChange($parts, 'Voice', $old->VoiceLabel, $this->VoiceLabel);
        logAppendChange($parts, 'FilePath', $old->FilePath, $this->FilePath);
        logAppendChange($parts, 'NextcloudPath', $old->NextcloudPath, $this->NextcloudPath);
        logAppendChange($parts, 'PageCount', $old->PageCount, $this->PageCount);
        if(!$parts) {
            return '';
        }
        return sprintf('ScoreFile-ID: %d, ', (int)$this->Index).implode(', ', $parts);
    }

    public function is_valid() {
        if(!$this->Composition) return false;
        if(!$this->Instrument) return false;
        if($this->VoiceLabel === null || $this->VoiceLabel === '') return false;
        return true;
    }

    protected function insert() {
        $sql = sprintf(
            'INSERT INTO `%sScoreFile` (`Composition`, `Instrument`, `VoiceLabel`, `NextcloudPath`, `PageCount`, `Checksum`, `FilePath`) VALUES (%d, %d, "%s", %s, %s, %s, %s);',
            $GLOBALS['dbprefix'],
            $this->Composition,
            $this->Instrument,
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->VoiceLabel),
            $this->sqlNullable($this->NextcloudPath),
            $this->sqlNullableInt($this->PageCount),
            $this->sqlNullable($this->Checksum),
            $this->sqlNullable($this->FilePath)
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        $this->_data['Index'] = mysqli_insert_id($GLOBALS['conn']);
        return true;
    }

    protected function update() {
        $sql = sprintf(
            'UPDATE `%sScoreFile` SET `Composition` = %d, `Instrument` = %d, `VoiceLabel` = "%s", `NextcloudPath` = %s, `PageCount` = %s, `Checksum` = %s, `FilePath` = %s WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            $this->Composition,
            $this->Instrument,
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->VoiceLabel),
            $this->sqlNullable($this->NextcloudPath),
            $this->sqlNullableInt($this->PageCount),
            $this->sqlNullable($this->Checksum),
            $this->sqlNullable($this->FilePath),
            $this->Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        return (bool)$dbr;
    }

    public function delete() {
        if(!$this->Index) return false;
        $vars = $this->getVars();
        $sql = sprintf('DELETE FROM `%sScoreFile` WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            $this->Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        if($vars !== '') {
            $logentry = new Log;
            $logentry->DBdelete($vars);
        }
        $this->_data['Index'] = null;
        return true;
    }

    public function fill_from_array($row) {
        foreach($row as $key => $val) {
            if(array_key_exists($key, $this->_data)) {
                $this->_data[$key] = $val;
            }
        }
    }

    public function load_by_id($Index) {
        $Index = (int)$Index;
        $sql = sprintf('SELECT * FROM `%sScoreFile` WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            $Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $row = mysqli_fetch_array($dbr);
        if(is_array($row)) {
            $this->fill_from_array($row);
        }
        $this->fillJoins();
    }

    /**
     * @return ScoreFile[]
     */
    public static function listForCompositionInstrumentVoice($compositionId, $instrumentId, $voiceLabel) {
        $out = array();
        $sql = sprintf(
            'SELECT `Index` FROM `%sScoreFile` WHERE `Composition` = %d AND `Instrument` = %d AND `VoiceLabel` = "%s" ORDER BY `Index`;',
            $GLOBALS['dbprefix'],
            (int)$compositionId,
            (int)$instrumentId,
            mysqli_real_escape_string($GLOBALS['conn'], (string)$voiceLabel)
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        while($row = mysqli_fetch_array($dbr)) {
            $sf = new self();
            $sf->load_by_id((int)$row['Index']);
            $out[] = $sf;
        }
        return $out;
    }

    private function sqlNullable($val) {
        if($val === null || $val === '') {
            return 'NULL';
        }
        return '"'.mysqli_real_escape_string($GLOBALS['conn'], (string)$val).'"';
    }

    private function sqlNullableInt($val) {
        if($val === null || $val === '') {
            return 'NULL';
        }
        return (string)(int)$val;
    }
}
?>
