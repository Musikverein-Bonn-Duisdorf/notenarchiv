<?php
class Part
{
    private $_data = array('Index' => null, 'Composition' => null, 'Instrument' => null, 'Part' => null, 'FilePath' => null, 'InstrumentName' => null);
    public function __get($key) {
        switch($key) {
	    case 'Index':
	    case 'Composition':
	    case 'Instrument':
        case 'InstrumentName':
        case 'Part':
        case 'FilePath':
            return $this->_data[$key];
            break;
        default:
            break;
        }
    }
    public function __set($key, $val) {
        switch($key) {
	    case 'Index':
	    case 'Composition':
        case 'Instrument':
	    case 'Part':
            $this->_data[$key] = (int)$val;
            break;
	    case 'FilePath':
        case 'InstrumentName':
            $this->_data[$key] = $val;
            break;
        default:
            break;
        }	
    }

    public function fillJoins() {
        if(!$this->InstrumentName) {
            $sql = sprintf('SELECT * FROM `%sInstruments` WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            $this->Instrument
            );
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
            sqlerror();
            $row = mysqli_fetch_array($dbr);
            $this->InstrumentName = $row['Name'];
        }
    }
    
    public function save() {
        if(!$this->is_valid()) return false;
        if($this->Index > 0) {
            $this->update();
            $logentry = new Log;
            $logentry->DBupdate($this->getVars());
        }
        else {
            $this->insert();
            $logentry = new Log;
            $logentry->DBinsert($this->getVars());
        }
    }

    public function getVars() {
        
    }
        
    public function fill_from_array($row) {
        foreach($row as $key => $val) {
                $this->_data[$key] = $val;
        }
    }
    public function is_valid() {
        if(!$this->Part) return false;
        if(!$this->Composition) return false;
        if(!$this->Instrument) return false;
        return true;
    }
    protected function insert() {
        $sql = sprintf('INSERT INTO `%sParts` (`Composition`, `Instrument`, `Part`, `FilePath`) VALUES ("%d", "%d", "%d", "%s");',
        $GLOBALS['dbprefix'],
        $this->Composition,
        $this->Instrument,
        $this->Part,
        $this->FilePath
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        $this->_data['Index'] = mysqli_insert_id($GLOBALS['conn']);
        return true;
    }
    protected function update() {
        $sql = sprintf('UPDATE `%sParts` SET `Composition` = "%d", `Instrument` = "%d", `Part` = "%d", `FilePath` = "%s" WHERE `Index` = "%d";',
        $GLOBALS['dbprefix'],
        $this->Composition,
        $this->Instrument,
        $this->Part,
        $this->FilePath
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        return true;
    }
    public function load_by_id($Index) {
        $Index = (int) $Index;
        $sql = sprintf('SELECT * FROM `%sParts` WHERE `Index` = "%d";',
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

    public function printLine() {
        $str = "";
        $maindiv = new div;
        $maindiv->class="w3-card-4 w3-margin w3-row w3-padding";
        $str=$str.$maindiv->open();

        $row = new div;
        $row->col(1,3,3);
        $row->body=$this->InstrumentName;
        $str=$str.$row->print();

        $row = new div;
        $row->col(1,3,3);
        $row->body=$this->Part;
        $str=$str.$row->print();

        $row = new div;
        $row->col(2,3,3);
        $row->class="w3-red w3-center";
        if($this->FilePath) {
            $row->body="<i class=\"fas fa-download\"></i>";
            $row->body="<i class=\"fas fa-print\"></i>";
            $row->body="<i class=\"fas fa-trash\"></i>";
        }
        else {
            $row->body="<i class=\"fas fa-exclamation-circle\"></i> keine Stimme gefunden";
            $row->body="<i class=\"fas fa-upload\"></i>";
        }
        $str=$str.$row->print();

        $str=$str.$maindiv->close();
        return $str;
    }
};
?>
