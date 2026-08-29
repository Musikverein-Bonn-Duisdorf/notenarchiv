<?php
class Collection
{
    private $_data = array('Index' => null, 'Collections' => null, 'Composition' => null, 'CollectionNumber' => null, 'Title' => null, 'CollectionName' => null);
    public function __get($key) {
        switch($key) {
	    case 'Index':
	    case 'Collections':
	    case 'Composition':
	    case 'CollectionNumber':
	    case 'Title':
        case 'CollectionName':
            return $this->_data[$key];
            break;
        default:
            break;
        }
    }
    public function __set($key, $val) {
        switch($key) {
	    case 'Index':
	    case 'Collections':
	    case 'Composition':
	    case 'CollectionNumber':
            $this->_data[$key] = (int)$val;
            break;
	    case 'Title':
        case 'CollectionName':
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
        $this->fillJoins();
        $parts = array();
        $parts[] = sprintf('CollectionItem-ID: %d', (int)$this->Index);
        $colId = (int)$this->Collections;
        $colName = archivPlainText($this->CollectionName);
        if($colId > 0) {
            $label = $colName !== '' ? $colName : ('Sammlung #'.$colId);
            $parts[] = sprintf(
                'Collection: (%d) <b>%s</b>',
                $colId,
                htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            );
        }
        $compId = (int)$this->Composition;
        $title = archivPlainText($this->Title);
        if($compId > 0) {
            $label = $title !== '' ? $title : ('Stück #'.$compId);
            $parts[] = sprintf(
                'Composition: (%d) <b>%s</b>',
                $compId,
                htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            );
        }
        logAppendFilled($parts, 'Number', $this->CollectionNumber, (string)(int)$this->CollectionNumber, true);
        return implode(', ', $parts);
    }

    public function getChanges() {
        $old = new Collection;
        $old->load_by_id($this->Index);
        $old->fillJoins();
        $this->fillJoins();
        $parts = array();
        if((int)$old->Collections !== (int)$this->Collections) {
            $oldLabel = archivPlainText($old->CollectionName);
            $newLabel = archivPlainText($this->CollectionName);
            if($oldLabel === '') {
                $oldLabel = '#'.(int)$old->Collections;
            }
            if($newLabel === '') {
                $newLabel = '#'.(int)$this->Collections;
            }
            $parts[] = sprintf(
                'Collection: (%d) %s &rArr; (%d) <b>%s</b>',
                (int)$old->Collections,
                htmlspecialchars($oldLabel, ENT_QUOTES, 'UTF-8'),
                (int)$this->Collections,
                htmlspecialchars($newLabel, ENT_QUOTES, 'UTF-8')
            );
        }
        if((int)$old->Composition !== (int)$this->Composition) {
            $oldLabel = archivPlainText($old->Title);
            $newLabel = archivPlainText($this->Title);
            if($oldLabel === '') {
                $oldLabel = '#'.(int)$old->Composition;
            }
            if($newLabel === '') {
                $newLabel = '#'.(int)$this->Composition;
            }
            $parts[] = sprintf(
                'Composition: (%d) %s &rArr; (%d) <b>%s</b>',
                (int)$old->Composition,
                htmlspecialchars($oldLabel, ENT_QUOTES, 'UTF-8'),
                (int)$this->Composition,
                htmlspecialchars($newLabel, ENT_QUOTES, 'UTF-8')
            );
        }
        logAppendChange($parts, 'Number', $old->CollectionNumber, $this->CollectionNumber);
        if(!$parts) {
            return '';
        }
        return sprintf('CollectionItem-ID: %d, ', (int)$this->Index).implode(', ', $parts);
    }

    public function delete() {
        $vars = $this->getVars();
        $sql = sprintf('DELETE FROM `%sCollectionItem` WHERE `Index` = "%d";',
        $GLOBALS['dbprefix'],
        $this->Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if($dbr && $vars !== '') {
            $logentry = new Log;
            $logentry->DBdelete($vars);
        }
    }

    public function fillJoins() {
        if(!$this->Title) {
            $sql = sprintf('SELECT * FROM `%sComposition` WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            $this->Composition
            );
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
            sqlerror();
            $row = mysqli_fetch_array($dbr);
            if($row) $this->Title = $row['Title'];
        }
        if(!$this->CollectionName) {
            $sql = sprintf('SELECT * FROM `%sCollection` WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            $this->Collections
            );
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
            sqlerror();
            $row = mysqli_fetch_array($dbr);
            if($row) $this->CollectionName = $row['Name'];
        }
    }
    
    public function fill_from_array($row) {
        foreach($row as $key => $val) {
                $this->_data[$key] = $val;
        }
        $this->fillJoins();
    }
    public function is_valid() {
        if(!$this->Collections) return false;
        if(!$this->Composition) return false;
        return true;
    }
    protected function insert() {
        // Prefer updating an existing (Collections, Composition) row instead of creating duplicates.
        $sqlFind = sprintf(
            'SELECT `Index` FROM `%sCollectionItem` WHERE `Collections` = "%d" AND `Composition` = "%d" ORDER BY `Index` ASC LIMIT 1;',
            $GLOBALS['dbprefix'],
            (int)$this->Collections,
            (int)$this->Composition
        );
        $found = mysqli_query($GLOBALS['conn'], $sqlFind);
        sqlerror();
        $row = ($found) ? mysqli_fetch_assoc($found) : null;
        if($row && (int)$row['Index'] > 0) {
            $this->_data['Index'] = (int)$row['Index'];
            return $this->update();
        }
        $sql = sprintf('INSERT INTO `%sCollectionItem` (`Collections`, `Composition`, `CollectionNumber`) VALUES ("%d", "%d", %s);',
        $GLOBALS['dbprefix'],
        $this->Collections,
        $this->Composition,
        mkNULLonNull($this->CollectionNumber)
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        $this->_data['Index'] = mysqli_insert_id($GLOBALS['conn']);
        return true;
    }
    protected function update() {
        $sql = sprintf('UPDATE `%sCollectionItem` SET `Collections` = "%d", `Composition` = "%d", `CollectionNumber` = %s WHERE `Index` = "%d";',
        $GLOBALS['dbprefix'],
        $this->Collections,
        $this->Composition,
        mkNULLonNull($this->CollectionNumber),
        $this->Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        return true;
    }
    public function load_by_id($Index) {
        $Index = (int) $Index;
        $sql = sprintf('SELECT * FROM `%sCollectionItem` WHERE `Index` = "%d";',
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

    public function printLine() {
        $num = $this->CollectionNumber !== null && $this->CollectionNumber !== ''
            ? (string)$this->CollectionNumber
            : '';
        $title = archivPlainText($this->Title);
        $search = trim(preg_replace('/\s+/', ' ', implode(' ', array($num, $title, (string)$this->Composition))));

        $classes = array('collection-row', 'list-row');
        $hover = isset($GLOBALS['optionsDB']['HoverEffect']) ? (string)$GLOBALS['optionsDB']['HoverEffect'] : '';
        if($hover !== '') {
            $classes[] = $hover;
        }

        $compId = (int)$this->Composition;
        $formId = 'collectionForm'.$this->Index;
        $openJs = $compId > 0
            ? 'document.getElementById(\''.$formId.'\').submit();'
            : '';

        $coverHtml = '';
        $recordingHtml = '';
        if($compId > 0) {
            $piece = new Composition;
            $piece->load_by_id($compId);
            if((int)$piece->Index > 0) {
                if($title === '') {
                    $title = archivPlainText($piece->Title);
                }
                $coverHtml = $piece->coverHtml('archiv-thumb piece-cover');
                $recordingHtml = $piece->recordingCellHtml(true);
            }
        }

        $str = '';
        if($compId > 0) {
            $str .= '<form id="'.archivEscHtml($formId).'" action="composition.php" method="POST" class="archiv-list-nav-form">'
                .'<input type="hidden" name="pieceID" value="'.$compId.'">'
                .'</form>';
        }
        $str .= '<div class="'.archivEscHtml(implode(' ', $classes)).'"'
            .' data-search="'.archivEscHtml($search).'"'
            .' data-sort-nr="'.archivEscHtml($num).'"'
            .' data-sort-title="'.archivEscHtml($title).'"';
        if($openJs !== '') {
            $str .= ' onclick="'.$openJs.'"'
                .' role="button" tabindex="0"'
                .' onkeydown="if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();'.$openJs.'}"';
        }
        $str .= '>';
        $str .= '<div class="collection-id"><div class="collection-nr">'.archivEscHtml($num !== '' ? $num : '—').'</div></div>';
        $str .= '<div class="collection-rail" aria-hidden="true"></div>';
        $str .= '<div class="collection-main">';
        if($coverHtml !== '') {
            $str .= $coverHtml;
        }
        $str .= '<div class="collection-text"><div class="collection-title">'.archivEscHtml($title !== '' ? $title : '—').'</div></div>';
        $str .= '</div>';
        $str .= $recordingHtml;
        $str .= '</div>';
        return $str;
    }
};
?>
