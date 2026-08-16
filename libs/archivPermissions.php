<?php
/**
 * Local Archiv permissions (ARCHIV-42), Melde-parity for read/write/rights.
 * Table: {dbprefix}Permissions — Melde User.Index in column User.
 */
class ArchivPermissions
{
    private $_data = array(
        'Index' => null,
        'User' => null,
        'perm_read' => 0,
        'perm_write' => 0,
        'perm_editPermissions' => 0,
    );

    /** @var array<int,ArchivPermissions> */
    private static $cache = array();

    /** @var bool|null */
    private static $tableReady = null;

    public function __get($key) {
        return isset($this->_data[$key]) ? $this->_data[$key] : null;
    }

    public function __isset($key) {
        return isset($this->_data[$key]);
    }

    public function __set($key, $val) {
        if($key === 'Index' || $key === 'User') {
            $this->_data[$key] = (int)$val;
            return;
        }
        if(strpos((string)$key, 'perm_') === 0) {
            $this->_data[$key] = ((int)$val) ? 1 : 0;
        }
    }

    /**
     * @return string[]
     */
    public static function permissionKeys() {
        return array('perm_read', 'perm_write', 'perm_editPermissions');
    }

    /**
     * @return array<string,array{short:string,label:string}>
     */
    public static function permissionLabels() {
        return array(
            'perm_read' => array('short' => 'Lesen', 'label' => 'Lesen'),
            'perm_write' => array('short' => 'Schreiben', 'label' => 'Schreiben'),
            'perm_editPermissions' => array('short' => 'Rechte', 'label' => 'Berechtigungen bearbeiten'),
        );
    }

    /**
     * Logical groups + Melde-parity accent colors for Nav/Hero/Matrix.
     * `nutzer` / `system` mirror Melde ids so shared chrome matches.
     * @return array<int,array{id:string,title:string,color:string,keys:string[]}>
     */
    public static function permissionGroups() {
        return array(
            array(
                'id' => 'archiv',
                'title' => 'Archiv',
                'color' => '#8D6E63',
                'keys' => array('perm_read', 'perm_write'),
            ),
            array(
                'id' => 'nutzer',
                'title' => 'Nutzer',
                'color' => '#42A5F5',
                'keys' => array('perm_editPermissions'),
            ),
            array(
                'id' => 'system',
                'title' => 'System',
                'color' => '#78909C',
                'keys' => array(),
            ),
        );
    }

    /**
     * @param string $groupId
     * @return string
     */
    public static function groupColor($groupId) {
        $groupId = (string)$groupId;
        foreach(self::permissionGroups() as $group) {
            if(isset($group['id']) && (string)$group['id'] === $groupId) {
                return isset($group['color']) ? (string)$group['color'] : '#78909C';
            }
        }
        return '#78909C';
    }

    /**
     * @param string $key
     * @return string
     */
    public static function groupIdForPermission($key) {
        $key = (string)$key;
        foreach(self::permissionGroups() as $group) {
            $keys = isset($group['keys']) && is_array($group['keys']) ? $group['keys'] : array();
            if(in_array($key, $keys, true)) {
                return isset($group['id']) ? (string)$group['id'] : 'system';
            }
        }
        return 'system';
    }

    public static function clearCache($userId = null) {
        if($userId === null) {
            self::$cache = array();
            return;
        }
        unset(self::$cache[(int)$userId]);
    }

    /** @return void */
    public static function clearCacheForTests() {
        self::$cache = array();
        self::$tableReady = null;
    }

    /**
     * Create {dbprefix}Permissions if missing (avoids white-screen before Schema repair).
     * @return bool
     */
    public static function ensureTableExists() {
        if(!isset($GLOBALS['conn'], $GLOBALS['dbprefix'])) {
            return false;
        }
        if(self::$tableReady === true) {
            return true;
        }
        $table = $GLOBALS['dbprefix'].'Permissions';
        try {
            $sql = sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `Index` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `User` INT NOT NULL,
                    `perm_read` INT NOT NULL DEFAULT 0,
                    `perm_write` INT NOT NULL DEFAULT 0,
                    `perm_editPermissions` INT NOT NULL DEFAULT 0,
                    UNIQUE KEY `User` (`User`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
                $table
            );
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
            self::$tableReady = (bool)$dbr;
            return self::$tableReady;
        }
        catch(Throwable $e) {
            self::$tableReady = false;
            return false;
        }
    }

    /**
     * @param string $sql
     * @return mysqli_result|bool|null
     */
    private static function querySafe($sql) {
        if(!isset($GLOBALS['conn'])) {
            return null;
        }
        try {
            return mysqli_query($GLOBALS['conn'], $sql);
        }
        catch(Throwable $e) {
            return null;
        }
    }

    public static function isEmptyTable() {
        if(!isset($GLOBALS['conn'], $GLOBALS['dbprefix'])) {
            return true;
        }
        if(!self::ensureTableExists()) {
            return true;
        }
        $sql = sprintf('SELECT COUNT(*) AS `c` FROM `%sPermissions`;', $GLOBALS['dbprefix']);
        $dbr = self::querySafe($sql);
        if(!$dbr) {
            return true;
        }
        $row = mysqli_fetch_assoc($dbr);
        return !(isset($row['c']) && (int)$row['c'] > 0);
    }

    /**
     * First successful Archiv login when table is empty → all local rights.
     * @param int $userId
     * @return bool true if bootstrap applied
     */
    public static function bootstrapFirstUserIfEmpty($userId) {
        $userId = (int)$userId;
        if($userId < 1) {
            return false;
        }
        if(!self::ensureTableExists()) {
            return false;
        }
        if(!self::isEmptyTable()) {
            return false;
        }
        $p = new self();
        $p->User = $userId;
        foreach(self::permissionKeys() as $key) {
            $p->$key = 1;
        }
        $ok = $p->save();
        self::clearCache($userId);
        return $ok;
    }

    /**
     * @param int $userId
     * @return ArchivPermissions
     */
    public static function loadForUser($userId) {
        $userId = (int)$userId;
        if(isset(self::$cache[$userId])) {
            return self::$cache[$userId];
        }
        $p = new self();
        if($userId < 1) {
            self::$cache[$userId] = $p;
            return $p;
        }
        self::ensureTableExists();
        $sql = sprintf(
            'SELECT * FROM `%sPermissions` WHERE `User` = %d LIMIT 1;',
            $GLOBALS['dbprefix'],
            $userId
        );
        $dbr = self::querySafe($sql);
        if($dbr && ($row = mysqli_fetch_assoc($dbr))) {
            $p->fill_from_array($row);
        } else {
            $p->User = $userId;
        }
        self::$cache[$userId] = $p;
        return $p;
    }

    public function fill_from_array($row) {
        foreach($row as $key => $val) {
            if(array_key_exists($key, $this->_data) || strpos((string)$key, 'perm_') === 0) {
                $this->_data[$key] = $val;
            }
        }
    }

    public function getPermission($perm) {
        $perm = (string)$perm;
        if($perm === 'perm_read') {
            return ((int)$this->perm_read === 1) || ((int)$this->perm_write === 1);
        }
        if(!in_array($perm, self::permissionKeys(), true)) {
            return false;
        }
        return (int)$this->$perm === 1;
    }

    public function hasAnyPermission() {
        foreach(self::permissionKeys() as $key) {
            if((int)$this->$key === 1) {
                return true;
            }
        }
        return false;
    }

    public function is_valid() {
        return (int)$this->User > 0;
    }

    public function save() {
        if(!$this->is_valid()) {
            return false;
        }
        if((int)$this->Index > 0) {
            $ok = $this->update();
        } else {
            $ok = $this->insert();
        }
        if($ok) {
            self::clearCache((int)$this->User);
            if(class_exists('Log', false)) {
                $logentry = new Log;
                $logentry->DBupdate($this->getVars());
            }
        }
        return $ok;
    }

    public function getVars() {
        $parts = array(sprintf('Archiv-Rechte User %d', (int)$this->User));
        $labels = self::permissionLabels();
        foreach(self::permissionKeys() as $key) {
            if(empty($this->$key)) {
                continue;
            }
            $short = isset($labels[$key]['short']) ? $labels[$key]['short'] : $key;
            $parts[] = $short;
        }
        return implode(', ', $parts);
    }

    protected function insert() {
        if(!self::ensureTableExists()) {
            return false;
        }
        $sql = sprintf(
            'INSERT INTO `%sPermissions` (`User`, `perm_read`, `perm_write`, `perm_editPermissions`) VALUES (%d, %d, %d, %d);',
            $GLOBALS['dbprefix'],
            (int)$this->User,
            ((int)$this->perm_read) ? 1 : 0,
            ((int)$this->perm_write) ? 1 : 0,
            ((int)$this->perm_editPermissions) ? 1 : 0
        );
        $dbr = self::querySafe($sql);
        if(!$dbr) {
            if(function_exists('sqlerror')) {
                sqlerror();
            }
            return false;
        }
        $this->_data['Index'] = (int)mysqli_insert_id($GLOBALS['conn']);
        return true;
    }

    protected function update() {
        if(!self::ensureTableExists()) {
            return false;
        }
        $sql = sprintf(
            'UPDATE `%sPermissions` SET `perm_read` = %d, `perm_write` = %d, `perm_editPermissions` = %d WHERE `Index` = %d;',
            $GLOBALS['dbprefix'],
            ((int)$this->perm_read) ? 1 : 0,
            ((int)$this->perm_write) ? 1 : 0,
            ((int)$this->perm_editPermissions) ? 1 : 0,
            (int)$this->Index
        );
        $dbr = self::querySafe($sql);
        if(!$dbr && function_exists('sqlerror')) {
            sqlerror();
        }
        return (bool)$dbr;
    }

    /**
     * Ensure a row exists for user (may be all zeros).
     * @param int $userId
     * @return ArchivPermissions
     */
    public static function ensureRow($userId) {
        $p = self::loadForUser($userId);
        if((int)$p->Index < 1) {
            $p->User = (int)$userId;
            $p->save();
            self::clearCache((int)$userId);
            return self::loadForUser($userId);
        }
        return $p;
    }

    /**
     * Toggle one permission (AJAX matrix).
     * @param int $userId
     * @param string $perm
     * @param int $value
     * @param int $sessionUserId
     * @return bool|string true or error code
     */
    public static function setPermission($userId, $perm, $value, $sessionUserId = 0) {
        $userId = (int)$userId;
        $sessionUserId = (int)$sessionUserId;
        $perm = (string)$perm;
        $value = $value ? 1 : 0;
        if($userId < 1 || !in_array($perm, self::permissionKeys(), true)) {
            return 'invalid';
        }
        if($sessionUserId === $userId && $perm === 'perm_editPermissions' && $value === 0) {
            return 'cannot_remove_own_edit';
        }
        $p = self::ensureRow($userId);
        $p->$perm = $value;
        if($perm === 'perm_write' && $value === 1) {
            $p->perm_read = 1;
        }
        if(!$p->save()) {
            return 'save_failed';
        }
        return true;
    }
}
?>
