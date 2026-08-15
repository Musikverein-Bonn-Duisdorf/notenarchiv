<?php
class Publisher
{
    private $_data = array('Index' => null, 'Name' => null, 'Address' => null);
    public function __get($key) {
        switch($key) {
	    case 'Index':
	    case 'Name':
	    case 'Address':
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
	    case 'Address':
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
        $parts[] = sprintf('Publisher-ID: %d', (int)$this->Index);
        logAppendFilled($parts, 'Name', $this->Name);
        logAppendFilled($parts, 'Address', $this->Address);
        return implode(', ', $parts);
    }

    public function getChanges() {
        $old = new Publisher;
        $old->load_by_id($this->Index);
        $parts = array();
        logAppendChange($parts, 'Name', $old->Name, $this->Name);
        logAppendChange($parts, 'Address', $old->Address, $this->Address);
        if(!$parts) {
            return '';
        }
        return sprintf('Publisher-ID: %d, ', (int)$this->Index).implode(', ', $parts);
    }

    public function delete() {
        $id = (int)$this->Index;
        if($id < 1) {
            return false;
        }
        $vars = $this->getVars();
        $sql = sprintf('DELETE FROM `%sPublisher` WHERE `Index` = "%d";',
            $GLOBALS['dbprefix'],
            $id
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if($dbr) {
            archivEntityAvatarDelete('Publishers', $id, false);
            if($vars !== '') {
                $logentry = new Log;
                $logentry->DBdelete($vars);
            }
        }
        return (bool)$dbr;
    }

    public function avatarInitials() {
        $name = trim(archivPlainText($this->Name));
        $s = mb_substr($name, 0, 2, 'UTF-8');
        return $s !== '' ? mb_strtoupper($s, 'UTF-8') : '—';
    }

    public function uploadAvatar(array $file) {
        return archivEntityAvatarUpload(
            'Publishers',
            (int)$this->Index,
            $file,
            sprintf('Publisher-ID: %d', (int)$this->Index)
        );
    }

    public function deleteAvatar($writeLog = true) {
        return archivEntityAvatarDelete(
            'Publishers',
            (int)$this->Index,
            $writeLog,
            sprintf('Publisher-ID: %d', (int)$this->Index)
        );
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
        $sql = sprintf('INSERT INTO `%sPublisher` (`Name`, `Address`) VALUES ("%s", "%s");',
        $GLOBALS['dbprefix'],
        $this->Name,
        $this->Address
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        $this->_data['Index'] = mysqli_insert_id($GLOBALS['conn']);
        return true;
    }
    protected function update() {
        $sql = sprintf('UPDATE `%sPublisher` SET `Name` = "%s", `Address` = "%s" WHERE `Index` = "%d";',
        $GLOBALS['dbprefix'],
        $this->Name,
        $this->Address,
        $this->Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        return true;
    }
    public function load_by_id($Index) {
        $Index = (int) $Index;
        $sql = sprintf('SELECT * FROM `%sPublisher` WHERE `Index` = "%d";',
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
        $name = archivPlainText($this->Name);
        $address = archivPlainText($this->Address);
        $search = trim(preg_replace('/\s+/', ' ', implode(' ', array($name, $address, (string)$id))));

        $classes = array('publisher-row', 'list-row');
        $hover = isset($GLOBALS['optionsDB']['HoverEffect']) ? (string)$GLOBALS['optionsDB']['HoverEffect'] : '';
        if($hover !== '') {
            $classes[] = $hover;
        }
        $openJs = 'openModal(\'publisher\', '.$id.')';

        $str = '<div class="'.archivEscHtml(implode(' ', $classes)).'"'
            .' id="publisherID'.$id.'"'
            .' data-search="'.archivEscHtml($search).'"'
            .' data-sort-name="'.archivEscHtml($name).'"'
            .' data-sort-index="'.archivEscHtml((string)$id).'"'
            .' onclick="'.$openJs.'"'
            .' role="button" tabindex="0"'
            .' onkeydown="if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();'.$openJs.';}">';
        $str .= '<div class="publisher-id"><div class="publisher-id-num">'.archivEscHtml((string)$id).'</div></div>';
        $str .= '<div class="publisher-rail" aria-hidden="true"></div>';
        $str .= '<div class="publisher-main">';
        $str .= archivEntityAvatarHtml('Publishers', $id, $this->avatarInitials(), 'entity-avatar entity-avatar--row');
        $str .= '<div class="publisher-text">';
        $str .= '<div class="publisher-name">'.archivEscHtml($name !== '' ? $name : '—').'</div>';
        if($address !== '') {
            $str .= '<div class="publisher-address">'.archivEscHtml($address).'</div>';
        }
        $str .= '</div></div></div>';
        return $str;
    }

    public function getModalHtml($showEditButton = false) {
        return render('publisher/modal', array(
            'publisher' => $this,
            'showEditButton' => (bool)$showEditButton,
        ));
    }
};
?>
