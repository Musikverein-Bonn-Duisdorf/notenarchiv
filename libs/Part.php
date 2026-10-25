<?php
/**
 * Legacy wrapper around ScoreFile (Part → VoiceLabel).
 */
class Part
{
    private $_data = array(
        'Index' => null,
        'Composition' => null,
        'Instrument' => null,
        'Part' => null,
        'FilePath' => null,
        'InstrumentName' => null,
        'NextcloudPath' => null,
    );

    public function __get($key) {
        if(array_key_exists($key, $this->_data)) {
            return $this->_data[$key];
        }
        return null;
    }

    public function __set($key, $val) {
        switch($key) {
        case 'Index':
        case 'Composition':
        case 'Instrument':
            $this->_data[$key] = (int)$val;
            break;
        case 'Part':
        case 'FilePath':
        case 'InstrumentName':
        case 'NextcloudPath':
            $this->_data[$key] = $val;
            break;
        default:
            break;
        }
    }

    public function fillJoins() {
        if(!$this->InstrumentName) {
            $sql = sprintf('SELECT * FROM `%sInstrument` WHERE `Index` = %d;',
                identityPrefix(),
                $this->Instrument
            );
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
            sqlerror();
            $row = $dbr ? mysqli_fetch_array($dbr) : null;
            if($row) {
                $this->InstrumentName = $row['Name'];
            }
        }
    }

    public function save() {
        if(!$this->is_valid()) {
            return false;
        }
        $sf = new ScoreFile();
        if($this->Index > 0) {
            $sf->load_by_id($this->Index);
        }
        $sf->Composition = $this->Composition;
        $sf->Instrument = $this->Instrument;
        $sf->VoiceLabel = (string)$this->Part;
        $sf->FilePath = $this->FilePath;
        if($this->NextcloudPath !== null && $this->NextcloudPath !== '') {
            $sf->NextcloudPath = $this->NextcloudPath;
        }
        if($sf->save()) {
            if(!$this->Index) {
                $this->_data['Index'] = $sf->Index;
            }
            return true;
        }
        return false;
    }

    public function getVars() {
        return sprintf('ScoreFile Index=%d Composition=%d Instrument=%d Voice=%s',
            $this->Index, $this->Composition, $this->Instrument, (string)$this->Part);
    }

    public function fill_from_array($row) {
        foreach($row as $key => $val) {
            if($key === 'VoiceLabel') {
                $this->_data['Part'] = (string)$val;
            }
            elseif(array_key_exists($key, $this->_data)) {
                $this->_data[$key] = $val;
            }
        }
    }

    public function is_valid() {
        if($this->Part === null || $this->Part === '') {
            return false;
        }
        if(!$this->Composition) {
            return false;
        }
        if(!$this->Instrument) {
            return false;
        }
        return true;
    }

    public function load_by_id($Index) {
        $Index = (int)$Index;
        $sql = sprintf('SELECT * FROM `%sScoreFile` WHERE `Index` = "%d";',
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
        $str = '';
        $maindiv = new div;
        $maindiv->class = 'w3-margin w3-row w3-border-bottom w3-border-black';
        $str = $str.$maindiv->open();

        $label = trim((string)$this->InstrumentName.' '.(string)$this->Part);
        $row = new div;
        $row->col(1, 3, 3);
        $row->body = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $str = $str.$row->print();

        $piece = new Composition;
        $piece->load_by_id((int)$this->Composition);
        $sf = new ScoreFile;
        $sf->load_by_id((int)$this->Index);
        $abs = ($sf->Index > 0) ? ScoreMirror::resolveScorePath($sf, $piece) : null;
        $hasFile = ($abs !== null);

        if($hasFile) {
            $row = new div;
            $row->col(1, 2, 2);
            $row->class = 'w3-center';
            $src = (strpos(str_replace('\\', '/', (string)$this->FilePath), '/') !== false
                || ($this->NextcloudPath !== null && $this->NextcloudPath !== ''))
                ? 'Spiegel'
                : 'Lokal';
            $row->body = '<a target="_blank" rel="noopener" href="'
                .htmlspecialchars($this->getURL(), ENT_QUOTES, 'UTF-8')
                .'" class="fas fa-download" title="'.htmlspecialchars($src, ENT_QUOTES, 'UTF-8').'"></a>'
                .' <span class="w3-small">'.htmlspecialchars($src, ENT_QUOTES, 'UTF-8').'</span>';
            $str = $str.$row->print();

            $modalId = 'archivDelPart'.(int)$this->Index;
            $btnDel = isset($GLOBALS['optionsDB']['colorBtnDelete'])
                ? (string)$GLOBALS['optionsDB']['colorBtnDelete']
                : '';
            $str = $str.archivDeleteAction(
                $modalId,
                'Stimme wirklich löschen?',
                trim('w3-button '.$btnDel),
                '<input type="hidden" name="pIndex" value="'.(int)$this->Index.'">'
                    .'<input type="hidden" name="Index" value="'.(int)$this->Composition.'">'
                    .'<input type="hidden" name="partdelete" value="delete">',
                '',
                'Löschen'
            );
        }
        elseif($this->FilePath) {
            $row = new div;
            $row->col(2, 3, 3);
            $row->class = 'w3-amber w3-center';
            $row->body = '<i class="fas fa-exclamation-triangle"></i> Datei fehlt';
            $str = $str.$row->print();
        }
        else {
            $row = new div;
            $row->col(2, 3, 3);
            $row->class = 'w3-red w3-center';
            $row->body = '<i class="fas fa-exclamation-circle"></i> keine Stimme gefunden ';
            $str = $str.$row->print();

            $form = new div;
            $form->col(1, 3, 3);
            $form->tag = 'form';
            $form->action = '';
            $form->method = 'POST';
            $form->enctype = 'multipart/form-data';
            $str = $str.$form->open();

            $hidden = new div;
            $hidden->tag = 'input';
            $hidden->type = 'hidden';
            $hidden->name = 'pIndex';
            $hidden->value = $this->Index;
            $str = $str.$hidden->print();

            $hidden = new div;
            $hidden->tag = 'input';
            $hidden->type = 'hidden';
            $hidden->name = 'Index';
            $hidden->value = $this->Composition;
            $str = $str.$hidden->print();

            $file = new div;
            $file->tag = 'input';
            $file->type = 'file';
            $file->name = 'part';
            $str = $str.$file->print();

            $submit = new div;
            $submit->tag = 'button';
            $submit->type = 'submit';
            $submit->name = 'partupload';
            $submit->value = 'upload';
            $submit->body = '<i class="fas fa-upload"></i>';
            $str = $str.$submit->print();

            $str = $str.$form->close();
        }

        $str = $str.$maindiv->close();
        return $str;
    }

    public function getURL() {
        return 'score-file.php?id='.(int)$this->Index;
    }

    public function deleteFile() {
        $piece = new Composition;
        $piece->load_by_id($this->Composition);
        $sf = new ScoreFile;
        $sf->load_by_id((int)$this->Index);
        $path = ScoreMirror::resolveScorePath($sf, $piece);
        // Only unlink uploads under composition dir — never delete mirror originals
        if($path !== null && $piece->FilePath) {
            $compDir = rtrim(str_replace('\\', '/', $piece->getFilePathPHP()), '/').'/';
            $norm = str_replace('\\', '/', $path);
            if(strpos($norm, $compDir) === 0 && is_file($path)) {
                @unlink($path);
            }
        }
        $this->FilePath = null;
        $this->NextcloudPath = null;
        $this->save();
    }

    public function upload($POST, $FILES) {
        $piece = new Composition;
        $piece->load_by_id($this->Composition);
        $this->FilePath = 'part_'.$this->Instrument.'_'.$this->Part.strrchr($FILES['part']['name'], '.');
        $target_file = $piece->getFilePathPHP().$this->FilePath;
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        if(isset($POST['submit'])) {
            if($FILES['part']['size'] > 50000000) {
                echo 'Sorry, your file is too large.';
                $uploadOk = 0;
            }
            if($imageFileType != 'jpg' && $imageFileType != 'png' && $imageFileType != 'jpeg'
                && $imageFileType != 'gif' && $imageFileType != 'pdf') {
                echo 'Sorry, only JPG, JPEG, PNG, GIF & PDF files are allowed.';
                $uploadOk = 0;
            }
        }
        if($uploadOk == 0) {
            echo 'Sorry, your file was not uploaded.';
        }
        else {
            if(move_uploaded_file($FILES['part']['tmp_name'], $target_file)) {
                $this->save();
            }
            else {
                echo 'Sorry, there was an error uploading your file to '.$target_file;
            }
        }
    }
}
?>
