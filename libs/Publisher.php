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
        $modalId = 'publisher'.$id;
        $openJs = 'document.getElementById(\''.$modalId.'\').style.display=\'block\'';

        $str = '<div class="'.archivEscHtml(implode(' ', $classes)).'"'
            .' data-search="'.archivEscHtml($search).'"'
            .' data-sort-name="'.archivEscHtml($name).'"'
            .' data-sort-index="'.archivEscHtml((string)$id).'"'
            .' onclick="'.$openJs.'"'
            .' role="button" tabindex="0"'
            .' onkeydown="if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();'.$openJs.';}">';
        $str .= '<div class="publisher-id"><div class="publisher-id-num">'.archivEscHtml((string)$id).'</div></div>';
        $str .= '<div class="publisher-rail" aria-hidden="true"></div>';
        $str .= '<div class="publisher-main">';
        $str .= '<div class="publisher-name">'.archivEscHtml($name !== '' ? $name : '—').'</div>';
        if($address !== '') {
            $str .= '<div class="publisher-address">'.archivEscHtml($address).'</div>';
        }
        $str .= '</div></div>';
        return $str;
    }

    public function printEditModal() {
        $id = (int)$this->Index;
        $modalId = 'publisher'.$id;
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
        $str .= '<p class="profile-kicker">Verlage</p>';
        $str .= '<h2 class="profile-title">Bearbeiten</h2>';
        $str .= '</div>';
        $str .= '<button type="button" class="modal-close w3-button" onclick="document.getElementById(\''.archivEscHtml($modalId).'\').style.display=\'none\'" aria-label="Schließen">&times;</button>';
        $str .= '</header>';
        $str .= '<div class="profile-grid">';
        $str .= '<input type="hidden" name="Index" value="'.$id.'">';
        $str .= '<div class="profile-field"><label class="profile-label">Name</label>'
            .'<input name="Name" type="text" class="w3-input w3-border profile-control '.archivEscHtml($inputBg).'" value="'.archivEscHtml($this->Name).'"/></div>';
        $str .= '<div class="profile-field"><label class="profile-label">Adresse</label>'
            .'<textarea name="Address" class="w3-input w3-border profile-control '.archivEscHtml($inputBg).'" rows="3">'.archivEscHtml($this->Address).'</textarea></div>';
        $str .= '<div class="profile-field profile-actions">'
            .'<button type="submit" name="update" value="1" class="w3-button '.archivEscHtml($btn).'">Speichern</button> '
            .'<button type="submit" name="delete" value="1" class="w3-button w3-border w3-red">Löschen</button>'
            .'</div>';
        $str .= '</div></form></div>';
        return $str;
    }
};
?>
