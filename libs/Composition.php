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
            $sql = sprintf('SELECT * FROM `%sComposer` WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            $this->Composer
            );
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
            sqlerror();
            $row = mysqli_fetch_array($dbr);
            if($row) $this->ComposerName = $row['FirstName']." ".$row['LastName'];
        }
        if(!$this->ArrangerName) {
            $sql = sprintf('SELECT * FROM `%sComposer` WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            $this->Arranger
            );
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
            sqlerror();
            $row = mysqli_fetch_array($dbr);
            if($row) $this->ArrangerName = $row['FirstName']." ".$row['LastName'];
        }
        if(!$this->PublisherName) {
            $sql = sprintf('SELECT * FROM `%sPublisher` WHERE `Index` = %d;',
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
        $this->fillJoins();
        return sprintf("Composition-ID: %d, Registration-Nr: %d, Title: %s, Composer: %s, Arranger: %s",
        $this->Index,
        $this->RegistrationNumber,
        $this->Title,
        $this->ComposerName,
        $this->ArrangerName
        );
    }
    
    public function makeFilePath() {
        if($this->Index) {
            $path = "data/Compositions/".$this->Index;
            if(!is_dir($path)) {
                mkdir($path, 0775);
            }
            $this->FilePath = $path."/";
            $this->save();
        }
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
        return true;
    }

    protected function checkFilePath() {
        return is_dir($this->getFilePathPHP());
    }
    
    protected function insert() {
        $sql = sprintf('INSERT INTO `%sComposition` (`RegistrationNumber`, `Title`, `Composer`, `Arranger`, `Publisher`, `Year`, `Grade`, `PerformanceTime`, `FilePath`) VALUES (%s, "%s", %s, %s, %s, %s, "%f", "%s", "%s");',
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

        $sql = "SELECT LAST_INSERT_ID() AS `Index`;";
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        
        $row = mysqli_fetch_array($dbr);
        $this->Index=$row['Index'];
        return true;
    }
    protected function update() {
        $sql = sprintf('UPDATE `%sComposition` SET `RegistrationNumber` = %s, `Title` = "%s", `Composer` = %s, `Arranger` = %s, `Publisher` = %s, `Year` = %s, `Grade` = "%.1f", `PerformanceTime` = "%s", `FilePath` = "%s" WHERE `Index` = "%d";',
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
        $sql = sprintf('SELECT * FROM `%sComposition` WHERE `Index` = "%d";',
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

    public function delete() {
        $sql = sprintf('DELETE FROM `%sCollectionItem` WHERE `Composition` = "%d";',
        $GLOBALS['dbprefix'],
        $this->Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();        

        $sql = sprintf('DELETE FROM `%sScoreFile` WHERE `Composition` = "%d";',
        $GLOBALS['dbprefix'],
        $this->Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();        

        $sql = sprintf('DELETE FROM `%sComposition` WHERE `Index` = "%d";',
        $GLOBALS['dbprefix'],
        $this->Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $logentry = new Log;
        $logentry->DBdelete($this->getVars());
}

    public function printLine() {
        $id = (int)$this->Index;
        $title = archivPlainText($this->Title);
        $composer = archivPlainText($this->ComposerName);
        $arranger = archivPlainText($this->ArrangerName);
        $publisher = archivPlainText($this->PublisherName);
        $year = $this->Year !== null && $this->Year !== '' ? (string)$this->Year : '';
        $grade = $this->Grade !== null && $this->Grade !== '' ? (string)$this->Grade : '';
        $reg = $this->RegistrationNumber !== null && $this->RegistrationNumber !== ''
            ? (string)$this->RegistrationNumber
            : '';

        $searchParts = array($reg, $title, $composer, $arranger, $publisher, $year, $grade);
        $search = trim(preg_replace('/\s+/', ' ', implode(' ', $searchParts)));

        $classes = array('piece-row', 'list-row');
        $hover = isset($GLOBALS['optionsDB']['HoverEffect']) ? (string)$GLOBALS['optionsDB']['HoverEffect'] : '';
        if($hover !== '') {
            $classes[] = $hover;
        }

        $formId = 'form'.$id;
        $openJs = 'document.getElementById(\''.$formId.'\').submit();';

        $str = '<form id="'.archivEscHtml($formId).'" action="composition.php" method="POST" class="archiv-list-nav-form">'
            .'<input type="hidden" name="pieceID" value="'.$id.'">'
            .'</form>';
        $str .= '<div class="'.archivEscHtml(implode(' ', $classes)).'"'
            .' id="pieceID'.$id.'"'
            .' data-search="'.archivEscHtml($search).'"'
            .' data-sort-nr="'.archivEscHtml($reg).'"'
            .' data-sort-title="'.archivEscHtml($title).'"'
            .' data-sort-composer="'.archivEscHtml($composer).'"'
            .' data-sort-publisher="'.archivEscHtml($publisher).'"'
            .' data-sort-year="'.archivEscHtml($year).'"'
            .' data-sort-grade="'.archivEscHtml($grade).'"'
            .' onclick="'.$openJs.'"'
            .' role="button" tabindex="0"'
            .' onkeydown="if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();'.$openJs.'}">';
        $str .= '<div class="piece-id">';
        $str .= '<div class="piece-reg">'.archivEscHtml($reg !== '' ? $reg : '—').'</div>';
        if($grade !== '') {
            $str .= '<span class="piece-chip">'.archivEscHtml($grade).'</span>';
        }
        $str .= '</div>';
        $str .= '<div class="piece-rail" aria-hidden="true"></div>';
        $str .= '<div class="piece-main">';
        $str .= '<div class="piece-title">'.archivEscHtml($title).'</div>';
        $str .= '<div class="piece-meta-line">';
        if($composer !== '') {
            $str .= '<span class="piece-meta-item"><span class="piece-meta-k">Komponist</span> '.archivEscHtml($composer).'</span>';
        }
        if($arranger !== '') {
            $str .= '<span class="piece-meta-item"><span class="piece-meta-k">Arrangeur</span> '.archivEscHtml($arranger).'</span>';
        }
        if($publisher !== '') {
            $str .= '<span class="piece-meta-item"><span class="piece-meta-k">Verlag</span> '.archivEscHtml($publisher).'</span>';
        }
        if($year !== '') {
            $str .= '<span class="piece-meta-item"><span class="piece-meta-k">Jahr</span> '.archivEscHtml($year).'</span>';
        }
        $str .= '</div>';
        $str .= '</div>';
        $str .= '</div>';
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
        if($this->getCover() != $GLOBALS['optionsDB']['defaultCompositionCover']) {
            unlink($this->getCover());
        }
    }
    
    public function listParts() {
        $sql = sprintf('SELECT `Index` FROM `%sScoreFile` INNER JOIN (SELECT `Index` AS `iIndex`, `CustomOrder` FROM `%sInstrument`) `%sInstrument` ON `iIndex` = `Instrument` WHERE `Composition` = "%d" ORDER BY `CustomOrder`, `VoiceLabel`;',
        $GLOBALS['dbprefix'],
        identityPrefix(),
        identityPrefix(),
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

    public function listCollections() {
        $sql = sprintf('SELECT `Index`, `CollectionNumber`, `cName` FROM `%sCollectionItem` INNER JOIN (SELECT `Index` AS `iIndex`, `Name` AS `cName` FROM `%sCollection`) `%sCollection` ON `iIndex` = `Collections` WHERE `Composition` = "%d" ORDER BY `cName`;',
        $GLOBALS['dbprefix'],
        $GLOBALS['dbprefix'],
        $GLOBALS['dbprefix'],
        $this->Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $str = "";
        $indent=0;
        while($row = mysqli_fetch_array($dbr)) {
            $line = new div;
            $line->tag="form";
            $line->action="";
            $line->method="POST";
            $line->indent=$indent;
            $line->class="w3-row w3-padding";
            $str=$str.$line->open();

            $indent++;
            $name = new div;
            $name->indent=$indent;
            $name->col(2,6,6);
            $name->body="<b>".$row['cName']."</b>";
            $str=$str.$name->print();

            $collnum = new div;
            $collnum->indent=$indent;
            $collnum->col(2,6,6);
            $collnum->class="w3-input";
            $collnum->tag="input";
            $collnum->type="number";
            $collnum->name="CollectionNumber";
            $collnum->value=$row['CollectionNumber'];
            $str=$str.$collnum->print();
            
            $Index = new div;
            $Index->indent=$indent;
            $Index->tag="input";
            $Index->type="hidden";
            $Index->name="Index";
            $Index->value=$row['Index'];
            $str=$str.$Index->print();

            $Index = new div;
            $Index->indent=$indent;
            $Index->tag="input";
            $Index->type="hidden";
            $Index->name="Composition";
            $Index->value=$this->Index;
            $str=$str.$Index->print();

            $save = new div;
            $save->indent=$indent;
            $save->col(2,6,6);
            $save->class="w3-button";
            $save->class=$GLOBALS['optionsDB']['colorBtnSubmit'];
            $save->tag="input";
            $save->type="submit";
            $save->name="updateCollection";
            $save->value="speichern";
            $str=$str.$save->print();

            $delete = new div;
            $delete->indent=$indent;
            $delete->col(2,6,6);
            $delete->class="w3-button";
            $delete->class=$GLOBALS['optionsDB']['colorBtnDelete'];
            $delete->tag="input";
            $delete->type="submit";
            $delete->name="deleteCollection";
            $delete->value="l&ouml;schen";
            $str=$str.$delete->print();

            $str=$str.$line->close();
        }
        $line = new div;
        $line->tag="form";
        $line->action="";
        $line->method="POST";
        $line->indent=$indent;
        $line->class="w3-row w3-padding";
        $str=$str.$line->open();

        $select = new div;
        $select->indent=$indent;
        $select->tag="select";
        $select->name="Collections";
        $select->class="w3-input";
        $select->col(4,12,12);
        $str=$str.$select->open();
        $str=$str.collectionsOption();
        $str=$str.$select->close();

        $collnum = new div;
        $collnum->indent=$indent;
        $collnum->col(2,6,6);
        $collnum->class="w3-input";
        $collnum->tag="input";
        $collnum->type="number";
        $collnum->name="CollectionNumber";
        $collnum->value=0;
        $str=$str.$collnum->print();

        $Index = new div;
        $Index->indent=$indent;
        $Index->tag="input";
        $Index->type="hidden";
        $Index->name="Composition";
        $Index->value=$this->Index;
        $str=$str.$Index->print();

        $save = new div;
        $save->indent=$indent;
        $save->col(2,6,6);
        $save->class="w3-button";
        $save->class=$GLOBALS['optionsDB']['colorBtnSubmit'];
        $save->tag="input";
        $save->type="submit";
        $save->name="insertCollection";
        $save->value="hinzuf&uuml;gen";
        $str=$str.$save->print();

        $str=$str.$line->close();
        return $str;        
    }
};
?>
