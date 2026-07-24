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
        return sprintf("Composer-ID: %d, First Name: %s, Last Name: %s",
        $this->Index,
        $this->FirstName,
        $this->LastName
        );
    }
        
    public function delete() {
        $sql = sprintf('DELETE FROM `%sComposer` WHERE `Index` = "%d";',
        $GLOBALS['dbprefix'],
        $this->Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();        
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
        $h = function($s) {
            return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        };
        $id = (int)$this->Index;
        $name = trim((string)$this->FirstName.' '.(string)$this->LastName);
        $search = trim(preg_replace(
            '/\s+/',
            ' ',
            implode(' ', array($name, (string)$this->FirstName, (string)$this->LastName, (string)$id))
        ));

        $classes = array('composer-row', 'list-row');
        $hover = isset($GLOBALS['optionsDB']['HoverEffect']) ? (string)$GLOBALS['optionsDB']['HoverEffect'] : '';
        if($hover !== '') {
            $classes[] = $hover;
        }
        $modalId = 'composer'.$id;
        $openJs = 'document.getElementById(\''.$modalId.'\').style.display=\'block\'';

        $str = '<div class="'.$h(implode(' ', $classes)).'"'
            .' data-search="'.$h($search).'"'
            .' data-sort-name="'.$h($name).'"'
            .' data-sort-index="'.$h((string)$id).'"'
            .' onclick="'.$openJs.'"'
            .' role="button" tabindex="0"'
            .' onkeydown="if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();'.$openJs.';}">';
        $str .= '<div class="composer-id"><div class="composer-id-num">'.$h((string)$id).'</div></div>';
        $str .= '<div class="composer-rail" aria-hidden="true"></div>';
        $str .= '<div class="composer-main"><div class="composer-name">'.$h($name !== '' ? $name : '—').'</div></div>';
        $str .= '</div>';
        return $str;
    }

    public function printEditModal() {
        $h = function($s) {
            return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        };
        $id = (int)$this->Index;
        $modalId = 'composer'.$id;
        $btn = isset($GLOBALS['optionsDB']['colorBtnSubmit'])
            ? (string)$GLOBALS['optionsDB']['colorBtnSubmit']
            : '';
        $inputBg = isset($GLOBALS['optionsDB']['colorInputBackground'])
            ? (string)$GLOBALS['optionsDB']['colorInputBackground']
            : '';

        $str = '<div id="'.$h($modalId).'" class="w3-modal">';
        $str .= '<form class="w3-modal-content profile-shell modal-shell" action="" method="POST">';
        $str .= '<header class="profile-hero">';
        $str .= '<div class="profile-hero-text">';
        $str .= '<p class="profile-kicker">Komponisten</p>';
        $str .= '<h2 class="profile-title">Bearbeiten</h2>';
        $str .= '</div>';
        $str .= '<button type="button" class="modal-close w3-button" onclick="document.getElementById(\''.$h($modalId).'\').style.display=\'none\'" aria-label="Schließen">&times;</button>';
        $str .= '</header>';
        $str .= '<div class="profile-grid">';
        $str .= '<input type="hidden" name="Index" value="'.$id.'">';
        $str .= '<div class="profile-field"><label class="profile-label">Vorname</label>'
            .'<input name="FirstName" type="text" class="w3-input w3-border profile-control '.$h($inputBg).'" value="'.$h($this->FirstName).'"/></div>';
        $str .= '<div class="profile-field"><label class="profile-label">Nachname</label>'
            .'<input name="LastName" type="text" class="w3-input w3-border profile-control '.$h($inputBg).'" value="'.$h($this->LastName).'"/></div>';
        $str .= '<div class="profile-field profile-actions">'
            .'<button type="submit" name="update" value="1" class="w3-button '.$h($btn).'">Speichern</button> '
            .'<button type="submit" name="delete" value="1" class="w3-button w3-border w3-red">Löschen</button>'
            .'</div>';
        $str .= '</div></form></div>';
        return $str;
    }
};
?>
