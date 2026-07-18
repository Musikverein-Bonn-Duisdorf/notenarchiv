<?php
/**
 * Legacy wrapper around ScoreFile (Part → VoiceLabel).
 */
class Part
{
    private $_data = array('Index' => null, 'Composition' => null, 'Instrument' => null, 'Part' => null, 'FilePath' => null, 'InstrumentName' => null);
    public function __get($key) {
        switch($key) {
	    case 'Index':
	    case 'Composition':
	    case 'Instrument':
        case 'InstrumentName':
        case 'Part':
        case 'FilePath':
            return $this->_data[$key];
            break;
        default:
            break;
        }
    }
    public function __set($key, $val) {
        switch($key) {
	    case 'Index':
	    case 'Composition':
        case 'Instrument':
	    case 'Part':
            $this->_data[$key] = (int)$val;
            break;
	    case 'FilePath':
        case 'InstrumentName':
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
            $row = mysqli_fetch_array($dbr);
            if($row) $this->InstrumentName = $row['Name'];
        }
    }
    
    public function save() {
        if(!$this->is_valid()) return false;
        $sf = new ScoreFile();
        if($this->Index > 0) {
            $sf->load_by_id($this->Index);
        }
        $sf->Composition = $this->Composition;
        $sf->Instrument = $this->Instrument;
        $sf->VoiceLabel = (string)$this->Part;
        $sf->FilePath = $this->FilePath;
        if($sf->save()) {
            if(!$this->Index) {
                $this->_data['Index'] = $sf->Index;
            }
            return true;
        }
        return false;
    }

    public function getVars() {
        return sprintf('ScoreFile Index=%d Composition=%d Instrument=%d Voice=%d',
            $this->Index, $this->Composition, $this->Instrument, $this->Part);
    }
        
    public function fill_from_array($row) {
        foreach($row as $key => $val) {
            if($key === 'VoiceLabel') {
                $this->_data['Part'] = (int)$val;
            }
            elseif(array_key_exists($key, $this->_data)) {
                $this->_data[$key] = $val;
            }
        }
    }
    public function is_valid() {
        if(!$this->Part) return false;
        if(!$this->Composition) return false;
        if(!$this->Instrument) return false;
        return true;
    }

    public function load_by_id($Index) {
        $Index = (int) $Index;
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
        $str = "";
        $maindiv = new div;
        $maindiv->class="w3-margin w3-row w3-border-bottom w3-border-black";
        $str=$str.$maindiv->open();

        $row = new div;
        $row->col(1,3,3);
        $row->body=$this->InstrumentName." ".$this->Part;
        $str=$str.$row->print();

        if($this->FilePath) {
            $row = new div;
            $row->col(1,3,3);
            $row->class="w3-center";
            $row->body="<a target=\"_blank\" href=\"".$this->getURL()."\" class=\"fas fa-download\"></a>";
            $str=$str.$row->print();

            $form = new div;
            $form->col(1,3,3);
            $form->tag="form";
            $form->action="";
            $form->method="POST";
            $form->enctype="multipart/form-data";
            $str=$str.$form->open();
            
            $hidden = new div;
            $hidden->tag="input";
            $hidden->type="hidden";
            $hidden->name="pIndex";
            $hidden->value=$this->Index;
            $str=$str.$hidden->print();

            $hidden = new div;
            $hidden->tag="input";
            $hidden->type="hidden";
            $hidden->name="Index";
            $hidden->value=$this->Composition;
            $str=$str.$hidden->print();

            $submit = new div;
            $submit->tag="button";
            $submit->type="submit";
            $submit->name="partdelete";
            $submit->value="delete";
            $submit->body="<i class=\"fas fa-trash\"></i>";
            $str=$str.$submit->print();

            $str=$str.$form->close();
        }
        else {
            $row = new div;
            $row->col(2,3,3);
            $row->class="w3-red w3-center";
            $row->body="<i class=\"fas fa-exclamation-circle\"></i> keine Stimme gefunden ";
            $str=$str.$row->print();

            $form = new div;
            $form->col(1,3,3);
            $form->tag="form";
            $form->action="";
            $form->method="POST";
            $form->enctype="multipart/form-data";
            $str=$str.$form->open();
            
            $hidden = new div;
            $hidden->tag="input";
            $hidden->type="hidden";
            $hidden->name="pIndex";
            $hidden->value=$this->Index;
            $str=$str.$hidden->print();

            $hidden = new div;
            $hidden->tag="input";
            $hidden->type="hidden";
            $hidden->name="Index";
            $hidden->value=$this->Composition;
            $str=$str.$hidden->print();

            $file = new div;
            $file->tag="input";
            $file->type="file";
            $file->name="part";
            $str=$str.$file->print();

            $submit = new div;
            $submit->tag="button";
            $submit->type="submit";
            $submit->name="partupload";
            $submit->value="upload";
            $submit->body="<i class=\"fas fa-upload\"></i>";
            $str=$str.$submit->print();

            $str=$str.$form->close();
        }
        
        $str=$str.$maindiv->close();
        return $str;
    }
    public function getURL() {
        $piece = new Composition;
        $piece->load_by_id($this->Composition);
        return $piece->FilePath.$this->FilePath;
    }

    public function deleteFile() {
        $piece = new Composition;
        $piece->load_by_id($this->Composition);
        unlink($piece->getFilePathPHP().$this->FilePath);
        $this->FilePath=null;
        $this->save();
    }

    public function upload($POST, $FILES) {
        $piece = new Composition;
        $piece->load_by_id($this->Composition);
        $target_dir = $piece->getFilePathPHP();
        $this->FilePath="part_".$this->Instrument."_".$this->Part.strrchr($FILES["part"]["name"], '.');
        $target_file = $piece->getFilePathPHP().$this->FilePath;
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
        if(isset($POST["submit"])) {
            if ($FILES["part"]["size"] > 50000000) {
                echo "Sorry, your file is too large.";
                $uploadOk = 0;
            }
            if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
            && $imageFileType != "gif" && $imageFileType != "pdf") {
                echo "Sorry, only JPG, JPEG, PNG, GIF & PDF files are allowed.";
                $uploadOk = 0;
            }
        }
        if ($uploadOk == 0) {
            echo "Sorry, your file was not uploaded.";
        } else {
            if (move_uploaded_file($FILES["part"]["tmp_name"], $target_file)) {
                $this->save();
            } else {
                echo "Sorry, there was an error uploading your file to ".$target_file;
            }
        }
    }
};
?>
