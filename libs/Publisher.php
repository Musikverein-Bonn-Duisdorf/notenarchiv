<?php
/**
 * Publisher entity (with optional Website + filesystem avatar).
 */
class Publisher
{
    private $_data = array('Index' => null, 'Name' => null, 'Address' => null, 'Website' => null);

    public function __get($key) {
        switch($key) {
            case 'Index':
            case 'Name':
            case 'Address':
            case 'Website':
                return $this->_data[$key];
            default:
                break;
        }
    }

    public function __set($key, $val) {
        switch($key) {
            case 'Index':
            case 'Name':
            case 'Address':
            case 'Website':
                $this->_data[$key] = $val;
                break;
            default:
                break;
        }
    }

    public function save() {
        if(!$this->is_valid()) {
            return false;
        }
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
        logAppendFilled($parts, 'Website', $this->Website);
        return implode(', ', $parts);
    }

    public function getChanges() {
        $old = new Publisher;
        $old->load_by_id($this->Index);
        $parts = array();
        logAppendChange($parts, 'Name', $old->Name, $this->Name);
        logAppendChange($parts, 'Address', $old->Address, $this->Address);
        logAppendChange($parts, 'Website', $old->Website, $this->Website);
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
        $sql = sprintf(
            'DELETE FROM `%sPublisher` WHERE `Index` = "%d";',
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
            if(array_key_exists($key, $this->_data)) {
                $this->_data[$key] = $val;
            }
        }
    }

    public function is_valid() {
        return (bool)$this->Name;
    }

    private function esc($val) {
        return mysqli_real_escape_string($GLOBALS['conn'], (string)$val);
    }

    protected function insert() {
        $sql = sprintf(
            'INSERT INTO `%sPublisher` (`Name`, `Address`, `Website`) VALUES ("%s", "%s", "%s");',
            $GLOBALS['dbprefix'],
            $this->esc($this->Name),
            $this->esc($this->Address),
            $this->esc($this->Website)
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) {
            return false;
        }
        $this->_data['Index'] = mysqli_insert_id($GLOBALS['conn']);
        return true;
    }

    protected function update() {
        $sql = sprintf(
            'UPDATE `%sPublisher` SET `Name` = "%s", `Address` = "%s", `Website` = "%s" WHERE `Index` = "%d";',
            $GLOBALS['dbprefix'],
            $this->esc($this->Name),
            $this->esc($this->Address),
            $this->esc($this->Website),
            (int)$this->Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        return (bool)$dbr;
    }

    public function load_by_id($Index) {
        $Index = (int)$Index;
        $sql = sprintf(
            'SELECT * FROM `%sPublisher` WHERE `Index` = "%d";',
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
        $website = trim(archivPlainText($this->Website));
        $search = trim(preg_replace('/\s+/', ' ', implode(' ', array($name, $address, $website, (string)$id))));

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
        $str .= archivEntityAvatarHtml('Publishers', $id, $this->avatarInitials(), 'archiv-thumb entity-avatar');
        $str .= '<div class="publisher-text">';
        $str .= '<div class="publisher-name">'.archivEscHtml($name !== '' ? $name : '—').'</div>';
        if($website !== '') {
            $str .= '<div class="publisher-website">'.archivEscHtml($website).'</div>';
        }
        if($address !== '') {
            $str .= '<div class="publisher-address">'.archivEscHtml($address).'</div>';
        }
        $pieceCount = $this->countCompositions();
        if($pieceCount > 0) {
            $str .= '<div class="publisher-piece-count">'.$pieceCount.' Stück'.($pieceCount === 1 ? '' : 'e').'</div>';
        }
        $str .= '</div></div></div>';
        return $str;
    }

    public function countCompositions() {
        $id = (int)$this->Index;
        if($id < 1) {
            return 0;
        }
        $sql = sprintf(
            'SELECT COUNT(`Index`) AS `Count` FROM `%sComposition` WHERE `Publisher` = %d;',
            $GLOBALS['dbprefix'],
            $id
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $row = $dbr ? mysqli_fetch_assoc($dbr) : null;
        return $row ? (int)$row['Count'] : 0;
    }

    /**
     * @return list<array{id:int,registrationNumber:?int,title:string}>
     */
    public function getCompositionSummaries($limit = 100) {
        $id = (int)$this->Index;
        $items = array();
        if($id < 1) {
            return $items;
        }
        $limit = max(1, min(500, (int)$limit));
        $sql = sprintf(
            'SELECT `Index`, `Title`, `RegistrationNumber`
             FROM `%sComposition`
             WHERE `Publisher` = %d
             ORDER BY `RegistrationNumber` DESC, `Title` ASC
             LIMIT %d;',
            $GLOBALS['dbprefix'],
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
        $pieces = $this->getCompositionSummaries(50);
        return render('publisher/modal', array(
            'publisher' => $this,
            'showEditButton' => (bool)$showEditButton,
            'pieceCount' => $this->countCompositions(),
            'pieces' => $pieces,
        ));
    }
};
?>
