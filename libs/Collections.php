<?php
class Collections
{
    private $_data = array('Index' => null, 'Name' => null, 'Archived' => 0);
    public function __get($key) {
        switch($key) {
	    case 'Index':
	    case 'Name':
	    case 'Archived':
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
	    case 'Archived':
            $this->_data[$key] = ((int)$val) ? 1 : 0;
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
        if((int)$this->Archived) {
            logAppendFilled($parts, 'Archived', bool2string($this->Archived));
        }
        return implode(', ', $parts);
    }

    public function getChanges() {
        $old = new Collections;
        $old->load_by_id($this->Index);
        $parts = array();
        logAppendChange($parts, 'Name', $old->Name, $this->Name);
        logAppendChange(
            $parts,
            'Archived',
            bool2string((int)$old->Archived),
            bool2string((int)$this->Archived)
        );
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
        if(!is_array($row)) {
            return;
        }
        if(array_key_exists('Index', $row)) {
            $this->Index = $row['Index'];
        }
        if(array_key_exists('Name', $row)) {
            $this->Name = $row['Name'];
        }
        if(array_key_exists('Archived', $row)) {
            $this->Archived = $row['Archived'];
        }
    }
    public function is_valid() {
        if(!$this->Name) return false;
        return true;
    }
    protected function insert() {
        $sql = sprintf(
            'INSERT INTO `%sCollection` (`Name`, `Archived`) VALUES ("%s", %d);',
            $GLOBALS['dbprefix'],
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->Name),
            (int)$this->Archived ? 1 : 0
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        $this->_data['Index'] = mysqli_insert_id($GLOBALS['conn']);
        return true;
    }
    protected function update() {
        $sql = sprintf(
            'UPDATE `%sCollection` SET `Name` = "%s", `Archived` = %d WHERE `Index` = "%d";',
            $GLOBALS['dbprefix'],
            mysqli_real_escape_string($GLOBALS['conn'], (string)$this->Name),
            (int)$this->Archived ? 1 : 0,
            (int)$this->Index
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
        $archived = (int)$this->Archived ? 1 : 0;
        $openJs = 'openModal(\'collection\', '.$id.')';

        $sql = sprintf(
            'SELECT `Index`, `Composition` FROM `%sCollectionItem` WHERE `Collections` = "%d" ORDER BY `CollectionNumber` ASC, `Index` ASC;',
            $GLOBALS['dbprefix'],
            $id
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $lines = '';
        $itemCount = 0;
        $seen = array();
        if($dbr) {
            while($row = mysqli_fetch_array($dbr)) {
                $compId = (int)$row['Composition'];
                if($compId > 0 && isset($seen[$compId])) {
                    continue;
                }
                if($compId > 0) {
                    $seen[$compId] = true;
                }
                $content = new Collection;
                $content->load_by_id($row['Index']);
                $lines .= $content->printLine();
                $itemCount++;
            }
        }

        $str = '<details class="collection-section" id="collectionID'.$id.'"'
            .' data-search="'.archivEscHtml($name).'"'
            .' data-sort-name="'.archivEscHtml($name).'"'
            .' data-sort-index="'.archivEscHtml((string)$id).'"'
            .' data-archived="'.$archived.'"'
            .' data-item-count="'.$itemCount.'">';
        $str .= '<summary class="collection-section-summary">';
        $str .= '<span class="collection-section-summary-main">';
        $str .= '<span class="collection-section-name">'.archivEscHtml($name !== '' ? $name : '—').'</span>';
        $str .= ' <span class="collection-section-count" title="Stücke">'.$itemCount.'</span>';
        if($archived) {
            $str .= ' <span class="mail-recipient-chip mail-recipient-chip--collection">Archiviert</span>';
        }
        $str .= '</span>';
        $str .= '<button type="button" class="collection-section-detail w3-button w3-small w3-border"'
            .' onclick="event.preventDefault();event.stopPropagation();'.$openJs.';"'
            .' aria-label="Details">Details</button>';
        $str .= '</summary>';
        $str .= '<div class="collection-section-list">'.$lines.'</div>';
        $str .= '</details>';
        return $str;
    }

    public function getItemsChipSpec() {
        $items = array();
        $id = (int)$this->Index;
        if($id < 1) {
            return $items;
        }
        $sql = sprintf(
            'SELECT `Composition`, `CollectionNumber` FROM `%sCollectionItem` WHERE `Collections` = "%d" ORDER BY `CollectionNumber` ASC, `Index` ASC;',
            $GLOBALS['dbprefix'],
            $id
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $seen = array();
        while($row = mysqli_fetch_array($dbr)) {
            $compId = (int)$row['Composition'];
            if($compId < 1 || isset($seen[$compId])) {
                continue;
            }
            $seen[$compId] = true;
            $items[] = array(
                'id' => $compId,
                'number' => (int)$row['CollectionNumber'],
            );
        }
        return $items;
    }

    public function getItemSummaries() {
        $id = (int)$this->Index;
        $items = array();
        if($id < 1) {
            return $items;
        }
        $sql = sprintf(
            'SELECT `CollectionNumber`, `Composition` FROM `%sCollectionItem` WHERE `Collections` = "%d" ORDER BY `CollectionNumber` ASC, `Index` ASC;',
            $GLOBALS['dbprefix'],
            $id
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $seen = array();
        while($row = mysqli_fetch_array($dbr)) {
            $compId = (int)$row['Composition'];
            if($compId < 1 || isset($seen[$compId])) {
                continue;
            }
            $seen[$compId] = true;
            $title = '';
            $coverHtml = '';
            $recordingHtml = '';
            if($compId > 0) {
                $piece = new Composition;
                $piece->load_by_id($compId);
                if((int)$piece->Index > 0) {
                    $title = archivPlainText($piece->Title);
                    $coverHtml = $piece->coverHtml('archiv-thumb piece-cover');
                    $recordingHtml = $piece->recordingCellHtml(true);
                }
            }
            $items[] = array(
                'number' => $row['CollectionNumber'] !== null && $row['CollectionNumber'] !== ''
                    ? (string)$row['CollectionNumber']
                    : '',
                'id' => $compId,
                'title' => $title,
                'coverHtml' => $coverHtml,
                'recordingHtml' => $recordingHtml,
            );
        }
        return $items;
    }

    public function getModalHtml($showEditButton = false) {
        $items = $this->getItemSummaries();
        return render('collection/modal', array(
            'collection' => $this,
            'showEditButton' => (bool)$showEditButton,
            'itemCount' => count($items),
            'items' => $items,
        ));
    }
};
?>
