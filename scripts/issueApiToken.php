#!/usr/bin/env php
<?php
/**
 * Issue an API Bearer token for an admin Melde user (ARCHIV-31).
 * Usage: php scripts/issueApiToken.php <login> [deviceLabel]
 */
if(PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}
$root = dirname(__DIR__);
chdir($root);
require $root.'/common/config.php';
require $root.'/common/include.php';
mysqli_select_db($GLOBALS['conn'], $sql['database']) or die(mysqli_error($GLOBALS['conn']));

$login = isset($argv[1]) ? trim((string)$argv[1]) : '';
$device = isset($argv[2]) ? (string)$argv[2] : 'cli';
if($login === '') {
    fwrite(STDERR, "Usage: php scripts/issueApiToken.php <login> [deviceLabel]\n");
    exit(1);
}

$sqlUser = sprintf(
    "SELECT * FROM `%sUser` WHERE `login` = '%s' AND (`Deleted` IS NULL OR `Deleted` != 1) LIMIT 1;",
    identityPrefix(),
    mysqli_real_escape_string($GLOBALS['conn'], $login)
);
$dbr = mysqli_query($GLOBALS['conn'], $sqlUser);
$row = $dbr ? mysqli_fetch_assoc($dbr) : null;
if(!$row) {
    fwrite(STDERR, "User not found\n");
    exit(1);
}
if(!computeAdminForUser((int)$row['Index'], !empty($row['Admin']))) {
    fwrite(STDERR, "User is not admin\n");
    exit(1);
}
$token = archivCreateAppToken((int)$row['Index'], $device);
if(!$token) {
    fwrite(STDERR, "Token create failed (run schema repair?)\n");
    exit(1);
}
echo $token."\n";
fwrite(STDERR, "Store this token securely; it will not be shown again.\n");
?>
