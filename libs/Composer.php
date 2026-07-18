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
        $str = "";
        $indent=1;
        $maindiv = new div;
        $maindiv->indent=$indent;
        $maindiv->class="w3-margin w3-row w3-border-bottom w3-border-black";
        $maindiv->onclick="document.getElementById('composer".$this->Index."').style.display='block'";
        $str=$str.$maindiv->open();

        $indent++;
        $row = new div;
        $row->indent=$indent;
        $row->col(1,3,3);
        $row->body=$this->FirstName." ".$this->LastName;
        $str=$str.$row->print();

        $str=$str.$maindiv->close();

        $indent--;
        $modal = new div;
        $modal->indent=$indent;
        $modal->id="composer".$this->Index;
        $modal->class="w3-modal";
        $str=$str.$modal->open();

        $indent++;
        $modalcontent = new div;
        $modalcontent->indent=$indent;
        $modalcontent->class="w3-modal-content";
        $modalcontent->tag="form";
        $modalcontent->action="";
        $modalcontent->method="POST";
        $str=$str.$modalcontent->open();

        $indent++;
        $header = new div;
        $header->indent=$indent;
        $header->tag="header";
        $header->class="w3-container w3-row";
        $header->class=$GLOBALS['optionsDB']['colorTitleBar'];
        $str=$str.$header->open();

        $indent++;
        $close = new div;
        $close->indent=$indent;
        $close->tag="span";
        $close->class="w3-button w3-display-topright";
        $close->onclick="document.getElementById('composer".$this->Index."').style.display='none'";
        $close->body="&times;";
        $str=$str.$close->print();
           
        $title = new div;
        $title->indent=$indent;
        $title->tag="h2";
        $title->body="bearbeiten";
        $str=$str.$title->print();
    
        $str=$str.$header->close();
        
        $indent--;
        $content = new div;
        $content->indent=$indent;
        $content->class="w3-container w3-row w3-padding";
        $str=$str.$content->open();

        $indent++;
        $row = new div;
        $row->indent=$indent;
        $row->col(2,6,6);
        $row->class="w3-row w3-padding";
        $row->body="<b>Vorname</b>";
        $str=$str.$row->print();

        $row = new div;
        $row->tag="input";
        $row->type="text";
        $row->indent=$indent;
        $row->col(2,6,6);
        $row->class="w3-button w3-row w3-padding";
        $row->class=$GLOBALS['optionsDB']['colorInputBackground'];
        $row->name="FirstName";
        $row->value=$this->FirstName;
        $str=$str.$row->print();
        $str=$str.$content->close();

        $str=$str.$content->open();
        $row = new div;
        $row->indent=$indent;
        $row->col(2,6,6);
        $row->class="w3-row w3-padding";
        $row->body="<b>Nachname</b>";
        $str=$str.$row->print();

        $row = new div;
        $row->tag="input";
        $row->type="text";
        $row->indent=$indent;
        $row->col(2,6,6);
        $row->class="w3-button w3-row w3-padding";
        $row->class=$GLOBALS['optionsDB']['colorInputBackground'];
        $row->name="LastName";
        $row->value=$this->LastName;
        $str=$str.$row->print();
        $str=$str.$content->close();

        $row = new div;
        $row->tag="input";
        $row->type="hidden";
        $row->indent=$indent;
        $row->name="Index";
        $row->value=$this->Index;
        $str=$str.$row->print();

        $str=$str.$content->open();
        $submit = new div;
        $submit->tag="input";
        $submit->type="submit";
        $submit->indent=$indent;
        $submit->col(2,6,6);
        $submit->class="w3-button w3-row w3-padding";
        $submit->class=$GLOBALS['optionsDB']['colorBtnSubmit'];
        $submit->name="update";
        $submit->value="speichern";
        $str=$str.$submit->print();

        $submit = new div;
        $submit->tag="input";
        $submit->type="submit";
        $submit->indent=$indent;
        $submit->col(2,6,6);
        $submit->class="w3-button w3-row w3-padding w3-margin-left";
        $submit->class=$GLOBALS['optionsDB']['colorBtnSubmit'];
        $submit->name="delete";
        $submit->value="l&ouml;schen";
        $str=$str.$submit->print();

        $str=$str.$content->close();
        $str=$str.$modalcontent->close();
        $str=$str.$modal->close();      
        return $str;
    }
};
?>
