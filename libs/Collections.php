<?php
class Collections
{
    private $_data = array('Index' => null, 'Name' => null);
    public function __get($key) {
        switch($key) {
	    case 'Index':
	    case 'Name':
            return $this->_data[$key];
            break;
        default:
            break;
        }
    }
    public function __set($key, $val) {
        switch($key) {
	    case 'Index':
	    case 'Name':
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
        $parts[] = sprintf('Collection-ID: %d', (int)$this->Index);
        logAppendFilled($parts, 'Name', $this->Name);
        return implode(', ', $parts);
    }

    public function getChanges() {
        $old = new Collections;
        $old->load_by_id($this->Index);
        $parts = array();
        logAppendChange($parts, 'Name', $old->Name, $this->Name);
        if(!$parts) {
            return '';
        }
        return sprintf('Collection-ID: %d, ', (int)$this->Index).implode(', ', $parts);
    }

    public function delete() {
        $id = (int)$this->Index;
        if($id < 1) {
            return false;
        }
        $vars = $this->getVars();
        $sql = sprintf(
            'DELETE FROM `%sCollectionItem` WHERE `Collections` = "%d";',
            $GLOBALS['dbprefix'],
            $id
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $sql = sprintf(
            'DELETE FROM `%sCollection` WHERE `Index` = "%d";',
            $GLOBALS['dbprefix'],
            $id
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if($dbr && $vars !== '') {
            $logentry = new Log;
            $logentry->DBdelete($vars);
        }
        return (bool)$dbr;
    }
        
    public function fill_from_array($row) {
        foreach($row as $key => $val) {
                $this->_data[$key] = $val;
        }
    }
    public function is_valid() {
        if(!$this->Name) return false;
        return true;
    }
    protected function insert() {
        $sql = sprintf('INSERT INTO `%sCollection` (`Name`) VALUES ("%s");',
        $GLOBALS['dbprefix'],
        $this->Name
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        $this->_data['Index'] = mysqli_insert_id($GLOBALS['conn']);
        return true;
    }
    protected function update() {
        $sql = sprintf('UPDATE `%sCollection` SET `Name` = "%s" WHERE `Index` = "%d";',
        $GLOBALS['dbprefix'],
        $this->Name,
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
    }

    public function printContent() {
        $id = (int)$this->Index;
        $name = archivPlainText($this->Name);
        $canEdit = !empty($_SESSION['admin']);

        $str = '<section class="collection-section" data-search="'.archivEscHtml($name).'">';
        if($canEdit) {
            $modalId = 'collectionEdit'.$id;
            $openJs = 'document.getElementById(\''.$modalId.'\').style.display=\'block\'';
            $str .= '<h3 class="collection-section-title collection-section-title--editable" role="button" tabindex="0"'
                .' onclick="'.$openJs.'"'
                .' onkeydown="if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();'.$openJs.';}">'
                .archivEscHtml($name !== '' ? $name : '—')
                .'</h3>';
        } else {
            $str .= '<h3 class="collection-section-title">'.archivEscHtml($name !== '' ? $name : '—').'</h3>';
        }
        $str .= '<div class="collection-section-list">';

        $sql = sprintf(
            'SELECT `Index` FROM `%sCollectionItem` WHERE `Collections` = "%d" ORDER BY `CollectionNumber` ASC;',
            $GLOBALS['dbprefix'],
            $id
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        while($row = mysqli_fetch_array($dbr)) {
            $content = new Collection;
            $content->load_by_id($row['Index']);
            $str .= $content->printLine();
        }

        $str .= '</div></section>';
        return $str;
    }

    public function printEditModal() {
        $id = (int)$this->Index;
        $modalId = 'collectionEdit'.$id;
        $btn = isset($GLOBALS['optionsDB']['colorBtnSubmit'])
            ? (string)$GLOBALS['optionsDB']['colorBtnSubmit']
            : '';
        $inputBg = isset($GLOBALS['optionsDB']['colorInputBackground'])
            ? (string)$GLOBALS['optionsDB']['colorInputBackground']
            : '';

        $str = '<div id="'.archivEscHtml($modalId).'" class="w3-modal">';
        $str .= '<form class="w3-modal-content profile-shell modal-shell" action="" method="POST">';
        $str .= '<header class="profile-hero">';
        $str .= '<div class="profile-hero-text">';
        $str .= '<p class="profile-kicker">Sammlungen</p>';
        $str .= '<h2 class="profile-title">Bearbeiten</h2>';
        $str .= '</div>';
        $str .= '<button type="button" class="modal-close w3-button" onclick="document.getElementById(\''.archivEscHtml($modalId).'\').style.display=\'none\'" aria-label="Schließen">&times;</button>';
        $str .= '</header>';
        $str .= '<div class="profile-grid">';
        $str .= '<input type="hidden" name="Index" value="'.$id.'">';
        $str .= '<div class="profile-field"><label class="profile-label">Name</label>'
            .'<input name="Name" type="text" class="w3-input w3-border profile-control '.archivEscHtml($inputBg).'" value="'.archivEscHtml($this->Name).'" required/></div>';
        $str .= '<div class="profile-field profile-actions">'
            .'<button type="submit" name="update" value="1" class="w3-button '.archivEscHtml($btn).'">Speichern</button> '
            .'<button type="submit" name="delete" value="1" class="w3-button w3-border w3-red">Löschen</button>'
            .'</div>';
        $str .= '</div></form></div>';
        return $str;
    }
};
?>
