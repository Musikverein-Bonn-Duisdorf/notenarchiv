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

        $str = '<div class="'.archivEscHtml(implode(' ', $classes)).'"'
            .' data-search="'.archivEscHtml($search).'"'
            .' data-sort-name="'.archivEscHtml($name).'"'
            .' data-sort-index="'.archivEscHtml((string)$id).'">';
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
};
?>
