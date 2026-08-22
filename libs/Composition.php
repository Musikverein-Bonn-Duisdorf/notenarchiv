<?php
class Composition
{
    private $_data = array(
        'Index' => null,
        'RegistrationNumber' => null,
        'Title' => null,
        'Composer' => null,
        'Arranger' => null,
        'Publisher' => null,
        'Year' => null,
        'PerformanceTime' => null,
        'Grade' => null,
        'FilePath' => null,
        'Website' => null,
        'ComposerName' => null,
        'ArrangerName' => null,
        'PublisherName' => null,
        'PublisherWebsite' => null,
    );
    public function __get($key) {
        switch($key) {
	    case 'Index':
	    case 'RegistrationNumber':
	    case 'Title':
        case 'Composer':
        case 'ComposerName':
	    case 'Arranger':
        case 'ArrangerName':
	    case 'Publisher':
	    case 'PublisherName':
	    case 'PublisherWebsite':
	    case 'Year':
	    case 'Grade':
	    case 'PerformanceTime':
        case 'FilePath':
        case 'Website':
            return $this->_data[$key];
            break;
        default:
            break;
        }
    }
    public function __set($key, $val) {
        switch($key) {
	    case 'Index':
	    case 'RegistrationNumber':
        case 'Composer':
	    case 'Arranger':
	    case 'Publisher':
	    case 'Year':
            $this->_data[$key] = (int)$val;
            break;
	    case 'Title':
	    case 'ComposerName':
	    case 'ArrangerName':
	    case 'PublisherName':
            $this->_data[$key] = htmlentities(trim($val));
            break;
	    case 'Grade':
            $this->_data[$key] = (double)$val;
            break;
	    case 'PerformanceTime':
	    case 'FilePath':
	    case 'Website':
	    case 'PublisherWebsite':
            $this->_data[$key] = is_string($val) ? trim($val) : $val;
            break;
        default:
            break;
        }	
    }

    public function fillJoins() {
        $composers = array();
        $arrangers = array();
        if((int)$this->Index > 0 && function_exists('archivLoadCompositionPersons')) {
            $composers = archivLoadCompositionPersons((int)$this->Index, 'composer');
            $arrangers = archivLoadCompositionPersons((int)$this->Index, 'arranger');
        }
        if($composers) {
            $this->ComposerName = archivCompositionPersonNames($composers);
            if(!(int)$this->Composer && !empty($composers[0]['id'])) {
                $this->_data['Composer'] = (int)$composers[0]['id'];
            }
        } elseif(!$this->ComposerName && (int)$this->Composer > 0) {
            $sql = sprintf('SELECT * FROM `%sComposer` WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            $this->Composer
            );
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
            sqlerror();
            $row = mysqli_fetch_array($dbr);
            if($row) $this->ComposerName = $row['FirstName']." ".$row['LastName'];
        }
        if($arrangers) {
            $this->ArrangerName = archivCompositionPersonNames($arrangers);
            if(!(int)$this->Arranger && !empty($arrangers[0]['id'])) {
                $this->_data['Arranger'] = (int)$arrangers[0]['id'];
            }
        } elseif(!$this->ArrangerName && (int)$this->Arranger > 0) {
            $sql = sprintf('SELECT * FROM `%sComposer` WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            $this->Arranger
            );
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
            sqlerror();
            $row = mysqli_fetch_array($dbr);
            if($row) $this->ArrangerName = $row['FirstName']." ".$row['LastName'];
        }
        if(!$this->PublisherName) {
            $sql = sprintf('SELECT * FROM `%sPublisher` WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            $this->Publisher
            );
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
            sqlerror();
            $row = mysqli_fetch_array($dbr);
            if($row) {
                $this->PublisherName = $row['Name'];
                $this->PublisherWebsite = isset($row['Website']) ? (string)$row['Website'] : '';
            }
        } elseif($this->PublisherWebsite === null || $this->PublisherWebsite === '') {
            $sql = sprintf('SELECT `Website` FROM `%sPublisher` WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            (int)$this->Publisher
            );
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
            sqlerror();
            $row = $dbr ? mysqli_fetch_array($dbr) : null;
            if($row) {
                $this->PublisherWebsite = isset($row['Website']) ? (string)$row['Website'] : '';
            }
        }
    }

    /**
     * @param string $role composer|arranger
     * @return int[]
     */
    public function getPersonIds($role) {
        if((int)$this->Index < 1 || !function_exists('archivLoadCompositionPersons')) {
            if($role === 'arranger') {
                return (int)$this->Arranger > 0 ? array((int)$this->Arranger) : array();
            }
            return (int)$this->Composer > 0 ? array((int)$this->Composer) : array();
        }
        $persons = archivLoadCompositionPersons((int)$this->Index, $role);
        if($persons) {
            $ids = array();
            foreach($persons as $row) {
                $ids[] = (int)$row['id'];
            }
            return $ids;
        }
        if($role === 'arranger') {
            return (int)$this->Arranger > 0 ? array((int)$this->Arranger) : array();
        }
        return (int)$this->Composer > 0 ? array((int)$this->Composer) : array();
    }

    /**
     * Chip spec for form editors.
     * @param string $role
     * @return array [{id,number},...]
     */
    public function getPersonChipSpec($role) {
        if((int)$this->Index > 0 && function_exists('archivLoadCompositionPersons')) {
            $persons = archivLoadCompositionPersons((int)$this->Index, $role);
            if($persons) {
                $out = array();
                foreach($persons as $row) {
                    $out[] = array('id' => (int)$row['id'], 'number' => (int)$row['number']);
                }
                return $out;
            }
        }
        $id = ($role === 'arranger') ? (int)$this->Arranger : (int)$this->Composer;
        return $id > 0 ? array(array('id' => $id, 'number' => 1)) : array();
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
            $this->makeFilePath();
            $logentry = new Log;
            $logentry->DBinsert($this->getVars());
        }
    }

    public function getVars() {
        $this->fillJoins();
        $parts = array();
        $parts[] = sprintf('Composition-ID: %d', (int)$this->Index);
        logAppendFilled($parts, 'RegistrationNumber', $this->RegistrationNumber, (string)(int)$this->RegistrationNumber, true);
        logAppendFilled($parts, 'Title', archivPlainText($this->Title));
        if((int)$this->Composer > 0) {
            $parts[] = sprintf(
                'Composer: (%d) <b>%s</b>',
                (int)$this->Composer,
                htmlspecialchars(archivPlainText($this->ComposerName), ENT_QUOTES, 'UTF-8')
            );
        }
        if((int)$this->Arranger > 0) {
            $parts[] = sprintf(
                'Arranger: (%d) <b>%s</b>',
                (int)$this->Arranger,
                htmlspecialchars(archivPlainText($this->ArrangerName), ENT_QUOTES, 'UTF-8')
            );
        }
        if((int)$this->Publisher > 0) {
            $parts[] = sprintf(
                'Publisher: (%d) <b>%s</b>',
                (int)$this->Publisher,
                htmlspecialchars(archivPlainText($this->PublisherName), ENT_QUOTES, 'UTF-8')
            );
        }
        logAppendFilled($parts, 'Year', $this->Year, (string)(int)$this->Year, true);
        logAppendFilled($parts, 'Grade', $this->Grade, (string)$this->Grade, true);
        logAppendFilled($parts, 'PerformanceTime', $this->PerformanceTime);
        logAppendFilled($parts, 'Website', $this->Website);
        return implode(', ', $parts);
    }

    public function getChanges() {
        $old = new Composition;
        $old->load_by_id($this->Index);
        $old->fillJoins();
        $this->fillJoins();
        $parts = array();
        logAppendChange($parts, 'RegistrationNumber', $old->RegistrationNumber, $this->RegistrationNumber);
        logAppendChange($parts, 'Title', archivPlainText($old->Title), archivPlainText($this->Title));
        if((int)$old->Composer !== (int)$this->Composer) {
            $parts[] = sprintf(
                'Composer: (%d) %s &rArr; (%d) <b>%s</b>',
                (int)$old->Composer,
                ((int)$old->Composer > 0)
                    ? htmlspecialchars(archivPlainText($old->ComposerName), ENT_QUOTES, 'UTF-8')
                    : '(leer)',
                (int)$this->Composer,
                ((int)$this->Composer > 0)
                    ? htmlspecialchars(archivPlainText($this->ComposerName), ENT_QUOTES, 'UTF-8')
                    : '(leer)'
            );
        }
        if((int)$old->Arranger !== (int)$this->Arranger) {
            $parts[] = sprintf(
                'Arranger: (%d) %s &rArr; (%d) <b>%s</b>',
                (int)$old->Arranger,
                ((int)$old->Arranger > 0)
                    ? htmlspecialchars(archivPlainText($old->ArrangerName), ENT_QUOTES, 'UTF-8')
                    : '(leer)',
                (int)$this->Arranger,
                ((int)$this->Arranger > 0)
                    ? htmlspecialchars(archivPlainText($this->ArrangerName), ENT_QUOTES, 'UTF-8')
                    : '(leer)'
            );
        }
        if((int)$old->Publisher !== (int)$this->Publisher) {
            $parts[] = sprintf(
                'Publisher: (%d) %s &rArr; (%d) <b>%s</b>',
                (int)$old->Publisher,
                ((int)$old->Publisher > 0)
                    ? htmlspecialchars(archivPlainText($old->PublisherName), ENT_QUOTES, 'UTF-8')
                    : '(leer)',
                (int)$this->Publisher,
                ((int)$this->Publisher > 0)
                    ? htmlspecialchars(archivPlainText($this->PublisherName), ENT_QUOTES, 'UTF-8')
                    : '(leer)'
            );
        }
        logAppendChange($parts, 'Year', $old->Year, $this->Year);
        logAppendChange($parts, 'Grade', $old->Grade, $this->Grade);
        logAppendChange($parts, 'PerformanceTime', $old->PerformanceTime, $this->PerformanceTime);
        logAppendChange($parts, 'FilePath', $old->FilePath, $this->FilePath);
        logAppendChange($parts, 'Website', $old->Website, $this->Website);
        if(!$parts) {
            return '';
        }
        return sprintf('Composition-ID: %d, ', (int)$this->Index).implode(', ', $parts);
    }
    
    public function makeFilePath() {
        if($this->Index) {
            $path = "data/Compositions/".$this->Index;
            if(!is_dir($path)) {
                mkdir($path, 0775);
            }
            $this->FilePath = $path."/";
            $this->update();
        }
    }

    public function getFilePathPHP() {
        return $GLOBALS['optionsDB']['dataDirectory'].$this->FilePath;
    }
    
    public function fill_from_array($row) {
        foreach($row as $key => $val) {
                $this->_data[$key] = $val;
        }
    }
    public function is_valid() {
        if(!$this->Title) return false;
        return true;
    }

    protected function checkFilePath() {
        return is_dir($this->getFilePathPHP());
    }
    
    protected function insert() {
        $sql = sprintf('INSERT INTO `%sComposition` (`RegistrationNumber`, `Title`, `Composer`, `Arranger`, `Publisher`, `Year`, `Grade`, `PerformanceTime`, `FilePath`, `Website`) VALUES (%s, "%s", %s, %s, %s, %s, "%f", "%s", "%s", "%s");',
        $GLOBALS['dbprefix'],
        mkNULLonNull($this->RegistrationNumber),
        mysqli_real_escape_string($GLOBALS['conn'], $this->Title),
        mkNULLonNull($this->Composer),
        mkNULLonNull($this->Arranger),
        mkNULLonNull($this->Publisher),
        mkNULLonNull($this->Year),
        $this->Grade,
        mysqli_real_escape_string($GLOBALS['conn'], (string)$this->PerformanceTime),
        mysqli_real_escape_string($GLOBALS['conn'], (string)$this->FilePath),
        mysqli_real_escape_string($GLOBALS['conn'], (string)$this->Website)
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;

        $sql = "SELECT LAST_INSERT_ID() AS `Index`;";
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        
        $row = mysqli_fetch_array($dbr);
        $this->Index=$row['Index'];
        return true;
    }
    protected function update() {
        $sql = sprintf('UPDATE `%sComposition` SET `RegistrationNumber` = %s, `Title` = "%s", `Composer` = %s, `Arranger` = %s, `Publisher` = %s, `Year` = %s, `Grade` = "%.1f", `PerformanceTime` = "%s", `FilePath` = "%s", `Website` = "%s" WHERE `Index` = "%d";',
        $GLOBALS['dbprefix'],
        mkNULLonNull($this->RegistrationNumber),
        mysqli_real_escape_string($GLOBALS['conn'], $this->Title),
        mkNULLonNull($this->Composer),
        mkNULLonNull($this->Arranger),
        mkNULLonNull($this->Publisher),
        mkNULLonNull($this->Year),
        $this->Grade,
        mysqli_real_escape_string($GLOBALS['conn'], (string)$this->PerformanceTime),
        mysqli_real_escape_string($GLOBALS['conn'], (string)$this->FilePath),
        mysqli_real_escape_string($GLOBALS['conn'], (string)$this->Website),
        $this->Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        return true;
    }
    public function load_by_id($Index) {
        $Index = (int) $Index;
        $sql = sprintf('SELECT * FROM `%sComposition` WHERE `Index` = "%d";',
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

    public function delete() {
        $id = (int)$this->Index;
        if($id < 1) {
            return false;
        }
        $vars = $this->getVars();
        $sql = sprintf('DELETE FROM `%sCollectionItem` WHERE `Composition` = "%d";',
        $GLOBALS['dbprefix'],
        $id
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) {
            return false;
        }

        $sql = sprintf('DELETE FROM `%sCompositionPerson` WHERE `Composition` = "%d";',
        $GLOBALS['dbprefix'],
        $id
        );
        mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();

        $sql = sprintf('DELETE FROM `%sScoreFile` WHERE `Composition` = "%d";',
        $GLOBALS['dbprefix'],
        $id
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) {
            return false;
        }

        $sql = sprintf('DELETE FROM `%sComposition` WHERE `Index` = "%d";',
        $GLOBALS['dbprefix'],
        $id
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) {
            return false;
        }
        if($vars !== '') {
            $logentry = new Log;
            $logentry->DBdelete($vars);
        }
        return true;
    }

    public function printLine() {
        $id = (int)$this->Index;
        $title = archivPlainText($this->Title);
        $composer = archivPlainText($this->ComposerName);
        $arranger = archivPlainText($this->ArrangerName);
        $publisher = archivPlainText($this->PublisherName);
        $year = $this->Year !== null && $this->Year !== '' ? (string)$this->Year : '';
        $grade = $this->Grade !== null && $this->Grade !== '' ? (string)$this->Grade : '';
        $reg = $this->RegistrationNumber !== null && $this->RegistrationNumber !== ''
            ? (string)$this->RegistrationNumber
            : '';

        $searchParts = array($reg, $title, $composer, $arranger, $publisher, $year, $grade);
        $search = trim(preg_replace('/\s+/', ' ', implode(' ', $searchParts)));

        $classes = array('piece-row', 'list-row');
        $hover = isset($GLOBALS['optionsDB']['HoverEffect']) ? (string)$GLOBALS['optionsDB']['HoverEffect'] : '';
        if($hover !== '') {
            $classes[] = $hover;
        }

        $openJs = 'openModal(\'composition\', '.$id.')';

        $str = '<div class="'.archivEscHtml(implode(' ', $classes)).'"'
            .' id="pieceID'.$id.'"'
            .' data-search="'.archivEscHtml($search).'"'
            .' data-sort-nr="'.archivEscHtml($reg).'"'
            .' data-sort-title="'.archivEscHtml($title).'"'
            .' data-sort-composer="'.archivEscHtml($composer).'"'
            .' data-sort-publisher="'.archivEscHtml($publisher).'"'
            .' data-sort-year="'.archivEscHtml($year).'"'
            .' data-sort-grade="'.archivEscHtml($grade).'"'
            .' onclick="'.$openJs.'"'
            .' role="button" tabindex="0"'
            .' onkeydown="if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();'.$openJs.';}">';
        $str .= '<div class="piece-id">';
        $str .= '<div class="piece-reg">'.archivEscHtml($reg !== '' ? $reg : '—').'</div>';
        if($grade !== '') {
            $str .= '<span class="piece-chip">'.archivEscHtml($grade).'</span>';
        }
        $str .= '</div>';
        $str .= '<div class="piece-rail" aria-hidden="true"></div>';
        $str .= '<div class="piece-main">';
        $str .= $this->coverHtml('archiv-thumb piece-cover');
        $str .= '<div class="piece-text">';
        $str .= '<div class="piece-title">'.archivEscHtml($title).'</div>';
        $str .= '<div class="piece-meta-line">';
        if($composer !== '') {
            $str .= '<span class="piece-meta-item"><span class="piece-meta-k">Komponist</span> '.archivEscHtml($composer).'</span>';
        }
        if($arranger !== '') {
            $str .= '<span class="piece-meta-item"><span class="piece-meta-k">Arrangeur</span> '.archivEscHtml($arranger).'</span>';
        }
        if($publisher !== '') {
            $str .= '<span class="piece-meta-item"><span class="piece-meta-k">Verlag</span> '.$this->publisherLinkHtml(true).'</span>';
        }
        if($year !== '') {
            $str .= '<span class="piece-meta-item"><span class="piece-meta-k">Jahr</span> '.archivEscHtml($year).'</span>';
        }
        $str .= '</div>';
        $str .= '</div></div>';
        $str .= '</div>';
        return $str;
    }

    /**
     * Absolute http(s) URL for a stored website value, or empty string.
     */
    public static function normalizeWebsiteUrl($url) {
        $url = trim(archivPlainText($url));
        if($url === '') {
            return '';
        }
        if(!preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }
        return $url;
    }

    /**
     * Best external publisher URL: piece product page, else publisher homepage.
     */
    public function publisherExternalUrl() {
        $product = self::normalizeWebsiteUrl($this->Website);
        if($product !== '') {
            return $product;
        }
        $this->fillJoins();
        return self::normalizeWebsiteUrl($this->PublisherWebsite);
    }

    /**
     * Linked publisher name (product page preferred), or plain text / em dash.
     *
     * @param bool $stopPropagation for links inside clickable list rows
     */
    public function publisherLinkHtml($stopPropagation = false) {
        $this->fillJoins();
        $name = archivPlainText($this->PublisherName);
        $href = $this->publisherExternalUrl();
        if($name === '' && $href === '') {
            return '—';
        }
        $label = $name !== '' ? $name : $href;
        if($href === '') {
            return archivEscHtml($label);
        }
        $extra = $stopPropagation
            ? ' onclick="event.stopPropagation();" onkeydown="event.stopPropagation();"'
            : '';
        return '<a href="'.archivEscHtml($href).'" target="_blank" rel="noopener noreferrer"'.$extra.'>'.archivEscHtml($label).'</a>';
    }

    public function getModalHtml($showEditButton = false) {
        return render('composition/modal', array(
            'piece' => $this,
            'showEditButton' => (bool)$showEditButton,
        ));
    }

    /**
     * True when a piece-specific cover file exists (not the global default).
     */
    public function hasOwnCover() {
        $cover = $this->getCover();
        $default = isset($GLOBALS['optionsDB']['defaultCompositionCover'])
            ? (string)$GLOBALS['optionsDB']['defaultCompositionCover']
            : '';
        return $cover !== '' && $cover !== $default;
    }

    /**
     * Up to two initials from the title (skips common articles).
     */
    public function coverInitials() {
        $title = trim(archivPlainText($this->Title));
        if($title === '') {
            return '—';
        }
        $skip = array(
            'der', 'die', 'das', 'den', 'dem', 'des',
            'ein', 'eine', 'einen', 'einem', 'einer',
            'the', 'a', 'an', 'le', 'la', 'les',
            'of', 'und', 'and', 'im', 'in', 'am', 'zu', 'zur', 'zum',
        );
        $words = preg_split('/[\s\-–—]+/u', $title, -1, PREG_SPLIT_NO_EMPTY);
        if(!is_array($words)) {
            $words = array($title);
        }
        $letters = array();
        $firstWord = '';
        foreach($words as $w) {
            $plain = preg_replace('/[^\p{L}\p{N}]+/u', '', (string)$w);
            if($plain === null || $plain === '') {
                continue;
            }
            if(in_array(mb_strtolower($plain, 'UTF-8'), $skip, true)) {
                continue;
            }
            if($firstWord === '') {
                $firstWord = $plain;
            }
            $letters[] = mb_strtoupper(mb_substr($plain, 0, 1, 'UTF-8'), 'UTF-8');
            if(count($letters) >= 2) {
                break;
            }
        }
        if(count($letters) >= 2) {
            return implode('', $letters);
        }
        if($firstWord !== '') {
            return mb_strtoupper(mb_substr($firstWord, 0, 2, 'UTF-8'), 'UTF-8');
        }
        $compact = preg_replace('/\s+/u', '', $title);
        return mb_strtoupper(mb_substr((string)$compact, 0, 2, 'UTF-8'), 'UTF-8');
    }

    /**
     * Cover thumbnail HTML for lists/modals (image, or initials placeholder if none).
     */
    public function coverHtml($cssClass = 'piece-cover') {
        $cls = trim((string)$cssClass);
        if($cls === '') {
            $cls = 'piece-cover';
        }
        $clsEsc = archivEscHtml($cls);
        if(!$this->hasOwnCover()) {
            $ini = archivEscHtml($this->coverInitials());
            $seed = (string)(int)$this->Index.'|'.archivPlainText($this->Title);
            $hue = (int)(sprintf('%u', crc32($seed)) % 360);
            return '<span class="'.$clsEsc.' piece-cover--placeholder" style="background-color:hsl('.$hue.',42%,38%)" aria-hidden="true">'
                .'<span class="piece-cover-initials">'.($ini !== '' ? $ini : '—').'</span></span>';
        }
        $src = (string)$this->getCover();
        $fs = $src;
        $root = isset($GLOBALS['optionsDB']['dataDirectory']) ? (string)$GLOBALS['optionsDB']['dataDirectory'] : '';
        if($root !== '' && strpos($src, 'data/') === 0) {
            $fs = $root.$src;
        } elseif($this->FilePath && is_file($this->getFilePathPHP().basename($src))) {
            $fs = $this->getFilePathPHP().basename($src);
        }
        $mtime = is_file($fs) ? (string)@filemtime($fs) : (string)time();
        return '<span class="'.$clsEsc.'" aria-hidden="true"><img src="'.archivEscHtml($src).'?'.rawurlencode($mtime).'" alt=""></span>';
    }

    public function getCover() {
        if($this->FilePath) {
            if(is_file($this->getFilePathPHP()."cover.png")) return $this->FilePath."cover.png";
            if(is_file($this->getFilePathPHP()."cover.jpg")) return $this->FilePath."cover.jpg";
            if(is_file($this->getFilePathPHP()."cover.jpeg")) return $this->FilePath."cover.jpeg";
            if(is_file($this->getFilePathPHP()."cover.gif")) return $this->FilePath."cover.gif";
        }
        return $GLOBALS['optionsDB']['defaultCompositionCover'];
    }

    public function deleteCover($writeLog = true) {
        $cover = $this->getCover();
        $default = isset($GLOBALS['optionsDB']['defaultCompositionCover'])
            ? (string)$GLOBALS['optionsDB']['defaultCompositionCover']
            : '';
        if($cover === $default || $cover === '') {
            return;
        }
        $base = basename($cover);
        $path = $this->getFilePathPHP().$base;
        if(is_file($path)) {
            unlink($path);
        } elseif(is_file($cover)) {
            unlink($cover);
        }
        if($writeLog) {
            $logentry = new Log;
            $logentry->DBupdate(sprintf(
                'Composition-ID: %d, Cover: %s &rArr; <b>(gelöscht)</b>',
                (int)$this->Index,
                htmlspecialchars($base, ENT_QUOTES, 'UTF-8')
            ));
        }
    }

    public function logCoverUpload($fileName) {
        $logentry = new Log;
        $logentry->DBupdate(sprintf(
            'Composition-ID: %d, Cover: (leer) &rArr; <b>%s</b>',
            (int)$this->Index,
            htmlspecialchars(basename((string)$fileName), ENT_QUOTES, 'UTF-8')
        ));
    }
    
    public function listParts() {
        $sql = sprintf('SELECT `Index` FROM `%sScoreFile` INNER JOIN (SELECT `Index` AS `iIndex`, `CustomOrder` FROM `%sInstrument`) `%sInstrument` ON `iIndex` = `Instrument` WHERE `Composition` = "%d" ORDER BY `CustomOrder`, `VoiceLabel`;',
        $GLOBALS['dbprefix'],
        identityPrefix(),
        identityPrefix(),
        $this->Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $str = "";
        while($row = mysqli_fetch_array($dbr)) {
            $part = new Part;
            $part->load_by_id($row['Index']);
            $str=$str.$part->printLine();
        }
        return $str;
    }

    public function getCollectionsChipSpec() {
        $items = array();
        $id = (int)$this->Index;
        if($id < 1) {
            return $items;
        }
        $sql = sprintf(
            'SELECT `Collections`, `CollectionNumber` FROM `%sCollectionItem` WHERE `Composition` = "%d" ORDER BY `CollectionNumber` ASC, `Index` ASC;',
            $GLOBALS['dbprefix'],
            $id
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $seen = array();
        while($row = mysqli_fetch_array($dbr)) {
            $colId = (int)$row['Collections'];
            if($colId < 1 || isset($seen[$colId])) {
                continue;
            }
            $seen[$colId] = true;
            $items[] = array(
                'id' => $colId,
                'number' => (int)$row['CollectionNumber'],
            );
        }
        return $items;
    }

    public function listCollections() {
        $str = '<form class="profile-grid collection-chips-form" action="" method="POST">';
        $str .= '<input type="hidden" name="Composition" value="'.(int)$this->Index.'">';
        $str .= '<div class="profile-field">';
        $spec = $this->getCollectionsChipSpec();
        $includeIds = array();
        foreach($spec as $row) {
            $includeIds[] = (int)$row['id'];
        }
        $str .= archivCollectionChipsEditorHtml(
            'piece-coll',
            'mail-recipient-chip--collection',
            'collectionsSpec',
            archivCollectionsCatalog($includeIds),
            $spec,
            'Sammlung…'
        );
        $str .= '</div>';
        $btn = isset($GLOBALS['optionsDB']['colorBtnSubmit'])
            ? (string)$GLOBALS['optionsDB']['colorBtnSubmit']
            : '';
        $str .= '<div class="profile-field profile-actions">';
        $str .= '<button type="submit" name="syncCollections" value="1" class="w3-button '.archivEscHtml($btn).'">Speichern</button>';
        $str .= '</div></form>';
        return $str;
    }
};
?>
