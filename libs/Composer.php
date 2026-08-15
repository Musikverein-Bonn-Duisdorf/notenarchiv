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
            $changes = $this->getChanges();
            $this->update();
            if($changes !== '') {
                $logentry = new Log;
                $logentry->DBupdate($changes);
            }
        }
        else {
            $this->insert();
            $logentry = new Log;
            $logentry->DBinsert($this->getVars());
        }
    }

    public function getVars() {
        $parts = array();
        $parts[] = sprintf('Composer-ID: %d', (int)$this->Index);
        logAppendFilled($parts, 'FirstName', $this->FirstName);
        logAppendFilled($parts, 'LastName', $this->LastName);
        return implode(', ', $parts);
    }

    public function getChanges() {
        $old = new Composer;
        $old->load_by_id($this->Index);
        $parts = array();
        logAppendChange($parts, 'FirstName', $old->FirstName, $this->FirstName);
        logAppendChange($parts, 'LastName', $old->LastName, $this->LastName);
        if(!$parts) {
            return '';
        }
        return sprintf('Composer-ID: %d, ', (int)$this->Index).implode(', ', $parts);
    }
        
    public function delete() {
        $vars = $this->getVars();
        $id = (int)$this->Index;
        $sql = sprintf('DELETE FROM `%sComposer` WHERE `Index` = "%d";',
        $GLOBALS['dbprefix'],
        $id
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if($dbr) {
            archivEntityAvatarDelete('Composers', $id, false);
            if($vars !== '') {
                $logentry = new Log;
                $logentry->DBdelete($vars);
            }
        }
    }

    public function avatarInitials() {
        $a = mb_substr(trim(archivPlainText($this->FirstName)), 0, 1, 'UTF-8');
        $b = mb_substr(trim(archivPlainText($this->LastName)), 0, 1, 'UTF-8');
        $s = $a.$b;
        return $s !== '' ? mb_strtoupper($s, 'UTF-8') : '—';
    }

    public function uploadAvatar(array $file) {
        return archivEntityAvatarUpload(
            'Composers',
            (int)$this->Index,
            $file,
            sprintf('Composer-ID: %d', (int)$this->Index)
        );
    }

    public function deleteAvatar($writeLog = true) {
        return archivEntityAvatarDelete(
            'Composers',
            (int)$this->Index,
            $writeLog,
            sprintf('Composer-ID: %d', (int)$this->Index)
        );
    }
    
    public function fill_from_array($row) {
        foreach($row as $key => $val) {
                $this->_data[$key] = $val;
        }
    }
    public function is_valid() {
        if(!$this->LastName) return false;
        return true;
    }
    protected function insert() {
        $sql = sprintf('INSERT INTO `%sComposer` (`FirstName`, `LastName`) VALUES ("%s", "%s");',
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
        $sql = sprintf('UPDATE `%sComposer` SET `FirstName` = "%s", `LastName` = "%s" WHERE `Index` = "%d";',
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
        $sql = sprintf('SELECT * FROM `%sComposer` WHERE `Index` = "%d";',
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
        $id = (int)$this->Index;
        $name = trim(archivPlainText($this->FirstName).' '.archivPlainText($this->LastName));
        $search = trim(preg_replace(
            '/\s+/',
            ' ',
            implode(' ', array($name, archivPlainText($this->FirstName), archivPlainText($this->LastName), (string)$id))
        ));

        $classes = array('composer-row', 'list-row');
        $hover = isset($GLOBALS['optionsDB']['HoverEffect']) ? (string)$GLOBALS['optionsDB']['HoverEffect'] : '';
        if($hover !== '') {
            $classes[] = $hover;
        }
        $openJs = 'openModal(\'composer\', '.$id.')';

        $str = '<div class="'.archivEscHtml(implode(' ', $classes)).'"'
            .' id="composerID'.$id.'"'
            .' data-search="'.archivEscHtml($search).'"'
            .' data-sort-name="'.archivEscHtml($name).'"'
            .' data-sort-index="'.archivEscHtml((string)$id).'"'
            .' onclick="'.$openJs.'"'
            .' role="button" tabindex="0"'
            .' onkeydown="if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();'.$openJs.';}">';
        $str .= '<div class="composer-id"><div class="composer-id-num">'.archivEscHtml((string)$id).'</div></div>';
        $str .= '<div class="composer-rail" aria-hidden="true"></div>';
        $str .= '<div class="composer-main">';
        $str .= archivEntityAvatarHtml('Composers', $id, $this->avatarInitials(), 'archiv-thumb entity-avatar');
        $str .= '<div class="composer-text">';
        $str .= '<div class="composer-name">'.archivEscHtml($name !== '' ? $name : '—').'</div>';
        $asComposer = $this->countCompositionsAsComposer();
        $asArranger = $this->countCompositionsAsArranger();
        if($asComposer > 0 || $asArranger > 0) {
            $bits = array();
            if($asComposer > 0) {
                $bits[] = $asComposer.' Stück'.($asComposer === 1 ? '' : 'e');
            }
            if($asArranger > 0) {
                $bits[] = $asArranger.' Arr.';
            }
            $str .= '<div class="composer-piece-count">'.archivEscHtml(implode(' · ', $bits)).'</div>';
        }
        $str .= '</div></div></div>';
        return $str;
    }

    public function countCompositionsAsComposer() {
        return $this->countCompositionsForRole('Composer');
    }

    public function countCompositionsAsArranger() {
        return $this->countCompositionsForRole('Arranger');
    }

    private function countCompositionsForRole($column) {
        $id = (int)$this->Index;
        if($id < 1 || ($column !== 'Composer' && $column !== 'Arranger')) {
            return 0;
        }
        $sql = sprintf(
            'SELECT COUNT(`Index`) AS `Count` FROM `%sComposition` WHERE `%s` = %d;',
            $GLOBALS['dbprefix'],
            $column,
            $id
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
        return $row ? (int)$row['Count'] : 0;
    }

    /**
     * @param 'Composer'|'Arranger' $column
     * @return list<array{id:int,registrationNumber:?int,title:string}>
     */
    public function getCompositionSummariesForRole($column, $limit = 100) {
        $id = (int)$this->Index;
        $items = array();
        if($id < 1 || ($column !== 'Composer' && $column !== 'Arranger')) {
            return $items;
        }
        $limit = max(1, min(500, (int)$limit));
        $sql = sprintf(
            'SELECT `Index`, `Title`, `RegistrationNumber`
             FROM `%sComposition`
             WHERE `%s` = %d
             ORDER BY `RegistrationNumber` DESC, `Title` ASC
             LIMIT %d;',
            $GLOBALS['dbprefix'],
            $column,
            $id,
            $limit
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        while($dbr && ($row = mysqli_fetch_assoc($dbr))) {
            $items[] = array(
                'id' => (int)$row['Index'],
                'registrationNumber' => $row['RegistrationNumber'] !== null && $row['RegistrationNumber'] !== ''
                    ? (int)$row['RegistrationNumber']
                    : null,
                'title' => archivPlainText($row['Title']),
            );
        }
        return $items;
    }

    public function getModalHtml($showEditButton = false) {
        $asComposer = $this->getCompositionSummariesForRole('Composer', 50);
        $asArranger = $this->getCompositionSummariesForRole('Arranger', 50);
        return render('composer/modal', array(
            'composer' => $this,
            'showEditButton' => (bool)$showEditButton,
            'composerPieceCount' => $this->countCompositionsAsComposer(),
            'arrangerPieceCount' => $this->countCompositionsAsArranger(),
            'composerPieces' => $asComposer,
            'arrangerPieces' => $asArranger,
        ));
    }
};
?>
