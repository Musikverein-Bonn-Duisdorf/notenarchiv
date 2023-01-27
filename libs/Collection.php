<?php
class Collection
{
    private $_data = array('Index' => null, 'Collections' => null, 'Composition' => null, 'CollectionNumber' => null, 'Title' => null, 'CollectionName' => null);
    public function __get($key) {
        switch($key) {
	    case 'Index':
	    case 'Collections':
	    case 'Composition':
	    case 'CollectionNumber':
	    case 'Title':
        case 'CollectionName':
            return $this->_data[$key];
            break;
        default:
            break;
        }
    }
    public function __set($key, $val) {
        switch($key) {
	    case 'Index':
	    case 'Collections':
	    case 'Composition':
	    case 'CollectionNumber':
            $this->_data[$key] = (int)$val;
            break;
	    case 'Title':
        case 'CollectionName':
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

    public function fillJoins() {
        if(!$this->Title) {
            $sql = sprintf('SELECT * FROM `%sCompositions` WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            $this->Composition
            );
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
            sqlerror();
            $row = mysqli_fetch_array($dbr);
            if($row) $this->Title = $row['Title'];
        }
        if(!$this->CollectionName) {
            $sql = sprintf('SELECT * FROM `%sCollections` WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            $this->Collections
            );
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
            sqlerror();
            $row = mysqli_fetch_array($dbr);
            if($row) $this->CollectionName = $row['Name'];
        }
    }
    
    public function fill_from_array($row) {
        foreach($row as $key => $val) {
                $this->_data[$key] = $val;
        }
        $this->fillJoins();
    }
    public function is_valid() {
        if(!$this->Collections) return false;
        if(!$this->Composition) return false;
        return true;
    }
    protected function insert() {
        $sql = sprintf('INSERT INTO `%sCollection` (`Collections`, `Composition`, `CollectionNumber`) VALUES ("%d", "%d", %s);',
        $GLOBALS['dbprefix'],
        $this->Collections,
        $this->Composition,
        mkNULLonNull($this->CollectionNumber)
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        $this->_data['Index'] = mysqli_insert_id($GLOBALS['conn']);
        return true;
    }
    protected function update() {
        $sql = sprintf('UPDATE `%sCollection` SET `Collections` = "%d", `Composition` = "%d", `CollectionNumber` = %s WHERE `Index` = "%d";',
        $GLOBALS['dbprefix'],
        $this->Collections,
        $this->Composition,
        mkNULLonNull($this->CollectionNumber),
        $this->Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        return true;
    }
    public function load_by_id($Index) {
        $Index = (int) $Index;
        $sql = sprintf('SELECT * FROM `%sCollection` WHERE `Index` = "%d";',
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
        $maindiv->class="w3-margin w3-row w3-border-bottom w3-border-black";
        $str=$str.$maindiv->open();

        $col = new div;
        $col->col(1,6,6);
        $col->body=$this->CollectionNumber;
        $str=$str.$col->print();

        $col = new div;
        $col->col(2,6,6);
        $col->body=$this->Title;
        $str=$str.$col->print();
        
        $str=$str.$maindiv->close();
        return $str;
    }
};
?>
