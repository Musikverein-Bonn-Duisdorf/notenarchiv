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
            $changes = $this->getChanges();
            $this->update();
            if($changes !== '') {
                $logentry = new Log;
                $logentry->DBupdate($changes);
            }
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
        $parts = array();
        $parts[] = sprintf('Composition-ID: %d', (int)$this->Index);
        logAppendFilled($parts, 'RegistrationNumber', $this->RegistrationNumber, (string)(int)$this->RegistrationNumber, true);
        logAppendFilled($parts, 'Title', archivPlainText($this->Title));
        logAppendFilled($parts, 'Composer', $this->ComposerName, htmlspecialchars(archivPlainText($this->ComposerName), ENT_QUOTES, 'UTF-8'), true);
        logAppendFilled($parts, 'Arranger', $this->ArrangerName, htmlspecialchars(archivPlainText($this->ArrangerName), ENT_QUOTES, 'UTF-8'), true);
        logAppendFilled($parts, 'Publisher', $this->PublisherName, htmlspecialchars(archivPlainText($this->PublisherName), ENT_QUOTES, 'UTF-8'), true);
        logAppendFilled($parts, 'Year', $this->Year, (string)(int)$this->Year, true);
        logAppendFilled($parts, 'Grade', $this->Grade, (string)$this->Grade, true);
        logAppendFilled($parts, 'PerformanceTime', $this->PerformanceTime);
        return implode(', ', $parts);
    }

    public function getChanges() {
        $old = new Composition;
        $old->load_by_id($this->Index);
        $old->fillJoins();
        $this->fillJoins();
        $parts = array();
        logAppendChange($parts, 'RegistrationNumber', $old->RegistrationNumber, $this->RegistrationNumber);
        logAppendChange($parts, 'Title', archivPlainText($old->Title), archivPlainText($this->Title));
        if((int)$old->Composer !== (int)$this->Composer) {
            $parts[] = 'Composer: '.htmlspecialchars(archivPlainText($old->ComposerName), ENT_QUOTES, 'UTF-8')
                .' &rArr; <b>'.htmlspecialchars(archivPlainText($this->ComposerName), ENT_QUOTES, 'UTF-8').'</b>';
        }
        if((int)$old->Arranger !== (int)$this->Arranger) {
            $parts[] = 'Arranger: '.htmlspecialchars(archivPlainText($old->ArrangerName), ENT_QUOTES, 'UTF-8')
                .' &rArr; <b>'.htmlspecialchars(archivPlainText($this->ArrangerName), ENT_QUOTES, 'UTF-8').'</b>';
        }
        if((int)$old->Publisher !== (int)$this->Publisher) {
            $parts[] = 'Publisher: '.htmlspecialchars(archivPlainText($old->PublisherName), ENT_QUOTES, 'UTF-8')
                .' &rArr; <b>'.htmlspecialchars(archivPlainText($this->PublisherName), ENT_QUOTES, 'UTF-8').'</b>';
        }
        logAppendChange($parts, 'Year', $old->Year, $this->Year);
        logAppendChange($parts, 'Grade', $old->Grade, $this->Grade);
        logAppendChange($parts, 'PerformanceTime', $old->PerformanceTime, $this->PerformanceTime);
        logAppendChange($parts, 'FilePath', $old->FilePath, $this->FilePath);
        if(!$parts) {
            return '';
        }
        return sprintf('Composition-ID: %d, ', (int)$this->Index).implode(', ', $parts);
    }
    
    public function makeFilePath() {
        if($this->Index) {
            $path = "data/Compositions/".$this->Index;
            if(!is_dir($path)) {
                mkdir($path, 0775);
            }
            $this->FilePath = $path."/";
            $this->update();
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
        $vars = $this->getVars();
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
        if($vars !== '') {
            $logentry = new Log;
            $logentry->DBdelete($vars);
        }
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

        $openJs = 'openModal(\'composition\', '.$id.')';

        $str = '<div class="'.archivEscHtml(implode(' ', $classes)).'"'
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
            .' onkeydown="if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();'.$openJs.';}">';
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

    public function getModalHtml($showEditButton = false) {
        return render('composition/modal', array(
            'piece' => $this,
            'showEditButton' => (bool)$showEditButton,
        ));
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

    public function deleteCover($writeLog = true) {
        $cover = $this->getCover();
        $default = isset($GLOBALS['optionsDB']['defaultCompositionCover'])
            ? (string)$GLOBALS['optionsDB']['defaultCompositionCover']
            : '';
        if($cover === $default || $cover === '') {
            return;
        }
        $base = basename($cover);
        $path = $this->getFilePathPHP().$base;
        if(is_file($path)) {
            unlink($path);
        } elseif(is_file($cover)) {
            unlink($cover);
        }
        if($writeLog) {
            $logentry = new Log;
            $logentry->DBupdate(sprintf(
                'Composition-ID: %d, Cover: %s &rArr; <b>(gelöscht)</b>',
                (int)$this->Index,
                htmlspecialchars($base, ENT_QUOTES, 'UTF-8')
            ));
        }
    }

    public function logCoverUpload($fileName) {
        $logentry = new Log;
        $logentry->DBupdate(sprintf(
            'Composition-ID: %d, Cover: (leer) &rArr; <b>%s</b>',
            (int)$this->Index,
            htmlspecialchars(basename((string)$fileName), ENT_QUOTES, 'UTF-8')
        ));
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

    public function getCollectionsChipSpec() {
        $items = array();
        $id = (int)$this->Index;
        if($id < 1) {
            return $items;
        }
        $sql = sprintf(
            'SELECT `Collections`, `CollectionNumber` FROM `%sCollectionItem` WHERE `Composition` = "%d" ORDER BY `CollectionNumber` ASC;',
            $GLOBALS['dbprefix'],
            $id
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        while($row = mysqli_fetch_array($dbr)) {
            $items[] = array(
                'id' => (int)$row['Collections'],
                'number' => (int)$row['CollectionNumber'],
            );
        }
        return $items;
    }

    public function listCollections() {
        $str = '<form class="profile-grid collection-chips-form" action="" method="POST">';
        $str .= '<input type="hidden" name="Composition" value="'.(int)$this->Index.'">';
        $str .= '<div class="profile-field">';
        $spec = $this->getCollectionsChipSpec();
        $includeIds = array();
        foreach($spec as $row) {
            $includeIds[] = (int)$row['id'];
        }
        $str .= archivCollectionChipsEditorHtml(
            'piece-coll',
            'mail-recipient-chip--collection',
            'collectionsSpec',
            archivCollectionsCatalog($includeIds),
            $spec,
            'Sammlung…'
        );
        $str .= '</div>';
        $btn = isset($GLOBALS['optionsDB']['colorBtnSubmit'])
            ? (string)$GLOBALS['optionsDB']['colorBtnSubmit']
            : '';
        $str .= '<div class="profile-field profile-actions">';
        $str .= '<button type="submit" name="syncCollections" value="1" class="w3-button '.archivEscHtml($btn).'">Speichern</button>';
        $str .= '</div></form>';
        return $str;
    }
};
?>
