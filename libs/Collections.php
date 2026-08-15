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
        $openJs = 'openModal(\'collection\', '.$id.')';

        $str = '<section class="collection-section" id="collectionID'.$id.'"'
            .' data-search="'.archivEscHtml($name).'"'
            .' data-sort-name="'.archivEscHtml($name).'"'
            .' data-sort-index="'.archivEscHtml((string)$id).'">';
        if($canEdit) {
            $str .= '<h3 class="collection-section-title collection-section-title--editable" role="button" tabindex="0"'
                .' onclick="'.$openJs.'"'
                .' onkeydown="if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();'.$openJs.';}">'
                .archivEscHtml($name !== '' ? $name : '—')
                .'</h3>';
        } else {
            $str .= '<h3 class="collection-section-title collection-section-title--openable" role="button" tabindex="0"'
                .' onclick="'.$openJs.'"'
                .' onkeydown="if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();'.$openJs.';}">'
                .archivEscHtml($name !== '' ? $name : '—')
                .'</h3>';
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

    public function getItemsChipSpec() {
        $items = array();
        $id = (int)$this->Index;
        if($id < 1) {
            return $items;
        }
        $sql = sprintf(
            'SELECT `Composition`, `CollectionNumber` FROM `%sCollectionItem` WHERE `Collections` = "%d" ORDER BY `CollectionNumber` ASC;',
            $GLOBALS['dbprefix'],
            $id
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        while($row = mysqli_fetch_array($dbr)) {
            $items[] = array(
                'id' => (int)$row['Composition'],
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
            'SELECT `CollectionNumber`, `Composition` FROM `%sCollectionItem` WHERE `Collections` = "%d" ORDER BY `CollectionNumber` ASC;',
            $GLOBALS['dbprefix'],
            $id
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        while($row = mysqli_fetch_array($dbr)) {
            $title = '';
            $compId = (int)$row['Composition'];
            if($compId > 0) {
                $piece = new Composition;
                $piece->load_by_id($compId);
                $title = archivPlainText($piece->Title);
            }
            $items[] = array(
                'number' => $row['CollectionNumber'] !== null && $row['CollectionNumber'] !== ''
                    ? (string)$row['CollectionNumber']
                    : '',
                'title' => $title,
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
