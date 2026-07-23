<?php
class User
{
    private $_data = array('Index' => null, 'Nachname' => null, 'Vorname' => null, 'login' => null, 'Passhash' => null, 'activeLink' => null, 'Email' => null, 'Email2' => null, 'getMail' => null, 'Admin' => null, 'singleUsePW' => null, 'LastLogin' => null, 'Joined' => null, 'Deleted' => null, 'DeletedOn' => null);
    public function __get($key) {
        switch($key) {
	    case 'Index':
	    case 'Nachname':
	    case 'Vorname':
        case 'login':
	    case 'Passhash':
	    case 'activeLink':
	    case 'Email':
	    case 'Email2':
	    case 'getMail':
	    case 'Admin':
        case 'singleUsePW':
        case 'LastLogin':
        case 'Joined':
        case 'Deleted':
        case 'DeletedOn':
            return $this->_data[$key];
            break;
        default:
            break;
        }
    }
    public function __set($key, $val) {
        switch($key) {
	    case 'Index':
            $this->_data[$key] = (int)$val;
            break;
	    case 'Nachname':
            $this->_data[$key] = htmlentities(trim($val));
            break;
	    case 'Vorname':
            $this->_data[$key] = htmlentities(trim($val));
            break;
	    case 'Passhash':
            $this->_data[$key] = $val;
            break;
	    case 'activeLink':
            $this->_data[$key] = $val;
            break;
	    case 'getMail':
	    case 'Admin':
	    case 'singleUsePW':
	    case 'Deleted':
            $this->_data[$key] = (bool)$val;
            break;
	    case 'login':
	    case 'iName':
	    case 'Email':
	    case 'Email2':
	    case 'LastLogin':
	    case 'DeletedOn':
            $this->_data[$key] = trim($val);
            break;
        default:
            break;
        }	
    }
    public function getVars() {
        return sprintf("User-ID: %d, Vorname: %s, Nachname: %s, Login: %s, Email: %s, Email2: %s, Mailverteiler: %s, Admin: %s, LastLogin: %s",
        $this->Index,
        $this->Vorname,
        $this->Nachname,
        $this->login,
        $this->Email,
        $this->Email2,
        bool2string($this->getMail),
        bool2string($this->Admin),
        $this->LastLogin
        );
    }
    public function getShort() {
        if(strlen($this->Vorname) >=2) {
            $end=2;
            if(substr($this->Vorname,1,1)=="&") {
                $end = strpos($this->Vorname, ";");
            }
            $short1 = substr($this->Vorname,0,$end);
        }
        else {
            $short1 = $this->Vorname;
        }
        if(strlen($this->Nachname) >=2) {
            $narray = explode(" ", $this->Nachname);
            $end=2;
            if(substr($narray[sizeof($narray)-1],1,1)=="&") {
                $end = strpos($narray[sizeof($narray)-1], ";");
            }
            $short2 = substr($narray[sizeof($narray)-1],0,$end);
        }
        else {
            $short2 = $this->Nachname;
        }
        return $short1.$short2;
    }
    public function save() {
        if($this->activeLink == '') $this->generateLink();
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
    public function singleUsePW($val) {
        $sql = sprintf('UPDATE `%sUser` SET `singleUsePW` = %d WHERE `Index` = %d;',
        identityPrefix(),
        (bool)$val,
        $this->Index
        );
        mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if($_SESSION['userid'] == $this->Index) {
            $_SESSION['singleUsePW'] = (bool)$val;
        }
    }
    public function newmail() {
        $mail = new Usermail;
        $mail->singleUser($this->Index, $GLOBALS['optionsDB']['newMailSubject'], $GLOBALS['optionsDB']['newMailText']."\n".$GLOBALS['optionsDB']['MailGreetings']);
    }
    public function passwd($password) {
        if(!$this->login) {
            return false;
        }
        $arbPW = false;
        if($password == '') {
            $password = uniqid();
            $arbPW = true;
        }
        if($this->Index) {
            $this->Passhash = password_hash($password, PASSWORD_DEFAULT);
            $mail = new Usermail;
            if($arbPW) {
                $this->singleUsePW(1);
                $this->update();
                $mail->singleUser($this->Index, $GLOBALS['optionsDB']['SubjectPW'], "ein neues Passwort wurde erstellt. Beim n&auml;chsten Login wirst du aufgefordert, dieses zu &auml;ndern.\nDu kannst dich nun unter\n\n<a href=\"".$GLOBALS['optionsDB']['WebSiteURL']."\">".$GLOBALS['optionsDB']['WebSiteURL']."</a>\n\neinloggen.\nBenutzername: ".$this->login."\nPasswort: ".$password);
            }
            else {
                $this->generateLink();
                $this->singleUsePW(0);
                $this->update();
                $mail->singleUser($this->Index, $GLOBALS['optionsDB']['SubjectPW'], "dein neues Passwort wurde gespeichert. Damit ist auch der alte Login-Link ungültig. Bitte nutze ab sofort den Link unter dieser Email.\n\nBenutzername: ".$this->login);
            }
        }
    }
    public function is_valid() {
        if(!$this->Nachname) return false;
        if(!$this->Vorname) return false;
        return true;
    }
    protected function generateLink() {
        $this->activeLink = bin2hex(random_bytes(16));
    }
    public function getLink() {
        return $GLOBALS['optionsDB']['WebSiteURL']."/login.php?alink=".$this->activeLink;
    }
    protected function insert() {
        $sql = sprintf('INSERT INTO `%sUser` (`Nachname`, `Vorname`, `login`, `Passhash`, `activeLink`, `Email`, `Email2`, `getMail`, `Admin`) VALUES ("%s", "%s", "%s", "%s", "%s", "%s", "%s", "%d", "%d");',
        identityPrefix(),
        mysqli_real_escape_string($GLOBALS['conn'], $this->Nachname),
        mysqli_real_escape_string($GLOBALS['conn'], $this->Vorname),
        mysqli_real_escape_string($GLOBALS['conn'], $this->login),
        mysqli_real_escape_string($GLOBALS['conn'], $this->Passhash),
        mysqli_real_escape_string($GLOBALS['conn'], $this->activeLink),
        mysqli_real_escape_string($GLOBALS['conn'], $this->Email),
        mysqli_real_escape_string($GLOBALS['conn'], $this->Email2),
        $this->getMail,
        $this->Admin
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        $this->_data['Index'] = mysqli_insert_id($GLOBALS['conn']);
        return true;
    }
    protected function update() {
        $sql = sprintf('UPDATE `%sUser` SET `Nachname` = "%s", `Vorname` = "%s", `login` = "%s", `Passhash` = "%s", `activeLink` = "%s", `Email` = "%s", `Email2` = "%s", `getMail` = "%d", `Admin` = "%d" WHERE `Index` = "%d";',
        identityPrefix(),
        mysqli_real_escape_string($GLOBALS['conn'], $this->Nachname),
        mysqli_real_escape_string($GLOBALS['conn'], $this->Vorname),
        mysqli_real_escape_string($GLOBALS['conn'], $this->login),
        mysqli_real_escape_string($GLOBALS['conn'], $this->Passhash),
        mysqli_real_escape_string($GLOBALS['conn'], $this->activeLink),
        mysqli_real_escape_string($GLOBALS['conn'], $this->Email),
        mysqli_real_escape_string($GLOBALS['conn'], $this->Email2),
        $this->getMail,
        $this->Admin,
        $this->Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        return true;
    }
    public function getName() {
        return $this->Vorname." ".$this->Nachname;
    }
    public function delete() {
        if(!$this->Index) return false;
        $sql = sprintf('UPDATE `%sUser` SET `Deleted` = 1, `DeletedOn` = CURRENT_TIMESTAMP, `Vorname` = "gel&ouml;schter", `Nachname` = "Benutzer", `Email` = "", `Email2` = "", `login` = "", `Passhash` = "", `getMail` = 0 WHERE `Index` = "%d";',
        identityPrefix(),
        $this->Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr) return false;
        $logentry = new Log;
        $logentry->DBdelete($this->getVars());
        $this->_data['Index'] = null;
        return true;
    }
    public function fill_from_array($row) {
        foreach($row as $key => $val) {
                $this->_data[$key] = $val;
        }
    }
    public function load_by_id($Index) {
        $Index = (int) $Index;
        $sql = sprintf('SELECT * FROM `%sUser` WHERE `Index` = "%d";',
        identityPrefix(),
        $Index
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        $row = mysqli_fetch_array($dbr);
        if(is_array($row)) {
            $this->fill_from_array($row);
        }
    }
};
?>
