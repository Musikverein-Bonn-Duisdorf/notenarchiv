<?php
class Composer
{
    private $_data = array('Index' => null, 'FirstName' => null, 'LastName' => null);
    public function __get($key) {
        switch($key) {
	    case 'Index':
	    case 'FirstName':
	    case 'LastName':
            return $this->_data[$key];
            break;
        default:
            break;
        }
    }
    public function __set($key, $val) {
        switch($key) {
	    case 'Index':
	    case 'FirstName':
	    case 'LastName':
            $this->_data[$key] = $val;
            break;
        default:
            break;
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
        if(!$this->FirstName) return false;
        if(!$this->LastName) return false;
        return true;
    }
    protected function insert() {
        $sql = sprintf('INSERT INTO `%sComposers` (`FirstName`, `LastName`) VALUES ("%s", "%s");',
        $GLOBALS['dbprefix'],
        $this->FirstName,
        $this->LastName
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        $this->_data['Index'] = mysqli_insert_id($GLOBALS['conn']);
        return true;
    }
    protected function update() {
        $sql = sprintf('UPDATE `%sComposers` SET `FirstName` = "%s", `LastName` = "%s" WHERE `Index` = "%d";',
        $GLOBALS['dbprefix'],
        $this->FirstName,
        $this->LastName,
        $this->Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        return true;
    }
    public function load_by_id($Index) {
        $Index = (int) $Index;
        $sql = sprintf('SELECT * FROM `%sComposers` WHERE `Index` = "%d";',
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

    public function printLine() {
        $str = "";
        $maindiv = new div;
        $maindiv->class="w3-margin w3-row w3-border-bottom w3-border-black";
        $str=$str.$maindiv->open();

        $row = new div;
        $row->col(1,3,3);
        $row->body=$this->FirstName." ".$this->LastName;
        $str=$str.$row->print();
        
        $str=$str.$maindiv->close();
        return $str;
    }
};
?>
