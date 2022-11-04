<?php
class Composition
{
    private $_data = array('Index' => null, 'RegistrationNumber' => null, 'Title' => null, 'Composer' => null, 'Arranger' => null, 'Publisher' => null, 'Year' => null, 'PerformanceTime' => null, 'Grade' => null, 'FilePath' => null, 'ComposerName' => null, 'ArrangerName' => null, 'PublisherName' => null);
    public function __get($key) {
        switch($key) {
	    case 'Index':
	    case 'RegistrationNumber':
	    case 'Title':
        case 'Composer':
        case 'ComposerName':
	    case 'Arranger':
        case 'ArrangerName':
	    case 'Publisher':
	    case 'PublisherName':
	    case 'Year':
	    case 'Grade':
	    case 'PerformanceTime':
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
	    case 'RegistrationNumber':
        case 'Composer':
	    case 'Arranger':
	    case 'Publisher':
	    case 'Year':
            $this->_data[$key] = (int)$val;
            break;
	    case 'Title':
	    case 'ComposerName':
	    case 'ArrangerName':
	    case 'PublisherName':
            $this->_data[$key] = htmlentities(trim($val));
            break;
	    case 'Grade':
            $this->_data[$key] = (double)$val;
            break;
	    case 'PerformanceTime':
	    case 'FilePath':
            $this->_data[$key] = $val;
            break;
        default:
            break;
        }	
    }

    public function fillJoins() {
        if(!$this->ComposerName) {
            $sql = sprintf('SELECT * FROM `%sComposers` WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            $this->Composer
            );
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
            sqlerror();
            $row = mysqli_fetch_array($dbr);
            $this->ComposerName = $row['FirstName']." ".$row['LastName'];
        }
        if(!$this->ArrangerName) {
            $sql = sprintf('SELECT * FROM `%sComposers` WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            $this->Arranger
            );
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
            sqlerror();
            $row = mysqli_fetch_array($dbr);
            if($row) $this->ArrangerName = $row['FirstName']." ".$row['LastName'];
        }
        if(!$this->PublisherName) {
            $sql = sprintf('SELECT * FROM `%sPublishers` WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            $this->Publisher
            );
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
            sqlerror();
            $row = mysqli_fetch_array($dbr);
            $this->PublisherName = $row['Name'];
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
            $this->mkdir();
            $logentry = new Log;
            $logentry->DBinsert($this->getVars());
        }
    }

    public function getVars() {
        
    }
    
    public function mkdir() {
        $path = $GLOBALS['optionsDB']['dataDirectory']."/Compositions/".$this->Index;
echo $path;
        mkdir($path, 0700);
    }
    
    public function fill_from_array($row) {
        foreach($row as $key => $val) {
                $this->_data[$key] = $val;
        }
    }
    public function is_valid() {
        if(!$this->Title) return false;
        if(!$this->Composer) return false;
        return true;
    }
    protected function insert() {
        $sql = sprintf('INSERT INTO `%sCompositions` (`RegistrationNumber`, `Title`, `Composer`, `Arranger`, `Publisher`, `Year`, `Grade`, `PerformanceTime`) VALUES ("%d", "%s", "%d", "%d", "%d", "%d", "%f", "%s");',
        $GLOBALS['dbprefix'],
        $this->RegistrationNumber,
        mysqli_real_escape_string($GLOBALS['conn'], $this->Title),
        $this->Composer,
        $this->Arranger,
        $this->Publisher,
        $this->Year,
        $this->Grade,
        $this->PerformanceTime
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        $this->_data['Index'] = mysqli_insert_id($GLOBALS['conn']);
        return true;
    }
    protected function update() {
        $sql = sprintf('UPDATE `%sCompositions` SET `RegistrationNumber` = "%d", `Title` = "%s", `Composer` = "%d", `Arranger` = "%d", `Publisher` = "%d", `Year` = "%d", `Grade` = "%f", `PerformanceTime` = "%s" WHERE `Index` = "%d";',
        $GLOBALS['dbprefix'],
        $this->RegistrationNumber,
        mysqli_real_escape_string($GLOBALS['conn'], $this->Title),
        $this->Composer,
        $this->Arranger,
        $this->Publisher,
        $this->Year,
        $this->Grade,
        $this->PerformanceTime,
        $this->Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        return true;
    }
    public function load_by_id($Index) {
        $Index = (int) $Index;
        $sql = sprintf('SELECT * FROM `%sCompositions` WHERE `Index` = "%d";',
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
        $maindiv->id="pieceID".$this->Index;
        $str=$str.$maindiv->open();

        $str=$str."<form id=\"form".$this->Index."\" action=\"composition.php\" method=\"POST\">";
        $str=$str."<input type=\"hidden\" name=\"pieceID\" value=\"".$this->Index."\">";
        $str=$str."</form>";

        $str=$str."<script>";
        $str=$str."var form".$this->Index." = document.getElementById(\"form".$this->Index."\");";
        $str=$str."document.getElementById(\"pieceID".$this->Index."\").addEventListener(\"click\", function () {form".$this->Index.".submit();});";
        $str=$str."</script>";
        
        $row = new div;
        $row->col(1,3,3);
        $row->body=$this->RegistrationNumber;
        $str=$str.$row->print();

        $row = new div;
        $row->col(3,6,6);
        $row->body=$this->Title;
        $str=$str.$row->print();

        $row = new div;
        $row->col(2,6,6);
        $row->body=$this->ComposerName;
        $str=$str.$row->print();

        $row = new div;
        $row->col(2,6,6);
        $row->body=$this->ArrangerName;
        $str=$str.$row->print();

        $row = new div;
        $row->col(2,6,6);
        $row->body=$this->PublisherName;
        $str=$str.$row->print();

        $row = new div;
        $row->col(1,3,3);
        $row->class="w3-center";
        $row->body=$this->Year;
        $str=$str.$row->print();

        $row = new div;
        $row->col(1,3,3);
        $row->class="w3-center";
        $row->body=$this->Grade;
        $str=$str.$row->print();

        $str=$str.$maindiv->close();
        return $str;
    }
    public function getCover() {
        if($this->FilePath) {
            return $this->FilePath+"/cover.png";
        }
        return "";
    }

    public function listParts() {
        $sql = sprintf('SELECT `Index` FROM `%sParts` INNER JOIN (SELECT `Index` AS `iIndex`, `CustomOrder` FROM `%sInstruments`) `%sInstruments` ON `iIndex` = `Instrument` WHERE `Composition` = "%d" ORDER BY `CustomOrder`, `Part`;',
        $GLOBALS['dbprefix'],
        $GLOBALS['dbprefix'],
        $GLOBALS['dbprefix'],
        $this->Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $str = "";
        while($row = mysqli_fetch_array($dbr)) {
            $part = new Part;
            $part->load_by_id($row['Index']);
            $str=$str.$part->printLine();
        }
        return $str;
    }
};
?>
