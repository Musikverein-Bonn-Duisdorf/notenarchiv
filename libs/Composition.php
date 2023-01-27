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
            if($row) $this->ComposerName = $row['FirstName']." ".$row['LastName'];
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
            if($row) $this->PublisherName = $row['Name'];
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
            $this->makeFilePath();
            $logentry = new Log;
            $logentry->DBinsert($this->getVars());
        }
    }

    public function getVars() {
        
    }
    
    public function makeFilePath() {
        $path = "data/Compositions/".$this->Index;
        mkdir($path, 0775);
        $this->FilePath = $path."/";
    }

    public function getFilePathPHP() {
        return $GLOBALS['optionsDB']['dataDirectory'].$this->FilePath;
    }
    
    public function fill_from_array($row) {
        foreach($row as $key => $val) {
                $this->_data[$key] = $val;
        }
    }
    public function is_valid() {
        if(!$this->Title) return false;
        if($this->FilePath == null || $this->checkFilePath() == false) {
            $this->makeFilePath();
        }
        return true;
    }

    protected function checkFilePath() {
        return is_dir($this->getFilePathPHP());
    }
    
    protected function insert() {
        $sql = sprintf('INSERT INTO `%sCompositions` (`RegistrationNumber`, `Title`, `Composer`, `Arranger`, `Publisher`, `Year`, `Grade`, `PerformanceTime`, `FilePath`) VALUES (%s, "%s", %s, %s, %s, %s, "%f", "%s", "%s");',
        $GLOBALS['dbprefix'],
        mkNULLonNull($this->RegistrationNumber),
        mysqli_real_escape_string($GLOBALS['conn'], $this->Title),
        mkNULLonNull($this->Composer),
        mkNULLonNull($this->Arranger),
        mkNULLonNull($this->Publisher),
        mkNULLonNull($this->Year),
        $this->Grade,
        $this->PerformanceTime,
        $this->FilePath
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        $this->_data['Index'] = mysqli_insert_id($GLOBALS['conn']);
        return true;
    }
    protected function update() {
        $sql = sprintf('UPDATE `%sCompositions` SET `RegistrationNumber` = %s, `Title` = "%s", `Composer` = %s, `Arranger` = %s, `Publisher` = %s, `Year` = %s, `Grade` = "%.1f", `PerformanceTime` = "%s", `FilePath` = "%s" WHERE `Index` = "%d";',
        $GLOBALS['dbprefix'],
        mkNULLonNull($this->RegistrationNumber),
        mysqli_real_escape_string($GLOBALS['conn'], $this->Title),
        mkNULLonNull($this->Composer),
        mkNULLonNull($this->Arranger),
        mkNULLonNull($this->Publisher),
        mkNULLonNull($this->Year),
        $this->Grade,
        $this->PerformanceTime,
        $this->FilePath,
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
        $maindiv->class="w3-row w3-padding w3-mobile w3-border-bottom w3-border-black";
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
            if(is_file($this->getFilePathPHP()."cover.png")) return $this->FilePath."cover.png";
            if(is_file($this->getFilePathPHP()."cover.jpg")) return $this->FilePath."cover.jpg";
            if(is_file($this->getFilePathPHP()."cover.jpeg")) return $this->FilePath."cover.jpeg";
            if(is_file($this->getFilePathPHP()."cover.gif")) return $this->FilePath."cover.gif";
        }
        return $GLOBALS['optionsDB']['defaultCompositionCover'];
    }

    public function deleteCover() {
        if($this->getCover()) {
            unlink($this->getCover());
        }
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
