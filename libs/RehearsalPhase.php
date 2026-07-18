<?php
class RehearsalPhase
{
    private $_data = array(
        'Index' => null,
        'Name' => null,
        'DateFrom' => null,
        'DateTo' => null,
        'Active' => null,
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
            $this->_data[$key] = (int)$val;
            break;
        case 'Active':
            $this->_data[$key] = (bool)$val;
            break;
        case 'Name':
        case 'DateFrom':
        case 'DateTo':
            $this->_data[$key] = trim((string)$val);
            break;
        default:
            break;
        }
    }

    public function save() {
        if(!$this->is_valid()) return false;
        if($this->Index > 0) {
            return $this->update();
        }
        return $this->insert();
    }

    public function is_valid() {
        return (bool)$this->Name;
    }

    protected function insert() {
        $sql = sprintf(
            'INSERT INTO `%sRehearsalPhase` (`Name`, `DateFrom`, `DateTo`, `Active`) VALUES ("%s", %s, %s, %d);',
            $GLOBALS['dbprefix'],
            mysqli_real_escape_string($GLOBALS['conn'], $this->Name),
            $this->sqlDate($this->DateFrom),
            $this->sqlDate($this->DateTo),
            $this->Active ? 1 : 0
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        $this->_data['Index'] = mysqli_insert_id($GLOBALS['conn']);
        return true;
    }

    protected function update() {
        $sql = sprintf(
            'UPDATE `%sRehearsalPhase` SET `Name` = "%s", `DateFrom` = %s, `DateTo` = %s, `Active` = %d WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            mysqli_real_escape_string($GLOBALS['conn'], $this->Name),
            $this->sqlDate($this->DateFrom),
            $this->sqlDate($this->DateTo),
            $this->Active ? 1 : 0,
            $this->Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        return (bool)$dbr;
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
        $sql = sprintf('SELECT * FROM `%sRehearsalPhase` WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            $Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $row = mysqli_fetch_array($dbr);
        if(is_array($row)) {
            $this->fill_from_array($row);
        }
    }

    /**
     * @return RehearsalPhase[]
     */
    public static function listAll($activeOnly = false) {
        $out = array();
        $where = $activeOnly ? ' WHERE `Active` = 1' : '';
        $sql = sprintf('SELECT `Index` FROM `%sRehearsalPhase`%s ORDER BY `DateFrom` DESC, `Name`;',
            $GLOBALS['dbprefix'],
            $where
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        while($row = mysqli_fetch_array($dbr)) {
            $phase = new self();
            $phase->load_by_id((int)$row['Index']);
            $out[] = $phase;
        }
        return $out;
    }

    /**
     * @return Composition[]
     */
    public function listCompositions() {
        $out = array();
        $sql = sprintf(
            'SELECT `Composition` FROM `%sRehearsalPiece` WHERE `Phase` = %d ORDER BY `SortOrder`, `Index`;',
            $GLOBALS['dbprefix'],
            $this->Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        while($row = mysqli_fetch_array($dbr)) {
            $comp = new Composition();
            $comp->load_by_id((int)$row['Composition']);
            $out[] = $comp;
        }
        return $out;
    }

    private function sqlDate($val) {
        if($val === null || $val === '') {
            return 'NULL';
        }
        return '"'.mysqli_real_escape_string($GLOBALS['conn'], (string)$val).'"';
    }
}
?>
