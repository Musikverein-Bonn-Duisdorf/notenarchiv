<?php
/**
 * Read-only Melde Permissions for Archiv admin gates (ARCHIV-6).
 * Uses identityPrefix() — does not create Melde rows.
 */
class IdentityPermissions
{
    private $flags = array();

    private static $cache = array();

    /**
     * @param int $userId
     * @return IdentityPermissions
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
        $table = identityPrefix().'Permissions';
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `User` = %d LIMIT 1;',
            $table,
            $userId
        );
        $dbr = @mysqli_query($GLOBALS['conn'], $sql);
        if($dbr && ($row = mysqli_fetch_assoc($dbr))) {
            foreach($row as $key => $val) {
                if(strpos($key, 'perm_') === 0) {
                    $p->flags[$key] = (int)$val;
                }
            }
        }
        self::$cache[$userId] = $p;
        return $p;
    }

    /** @return bool */
    public function isAdmin() {
        foreach($this->flags as $val) {
            if((int)$val) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $perm
     * @return bool
     */
    public function getPermission($perm) {
        return !empty($this->flags[$perm]);
    }

    /** @internal tests */
    public static function clearCacheForTests() {
        self::$cache = array();
    }
}
