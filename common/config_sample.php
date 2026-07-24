<?php
$sql = array(
    'server' => "myserver.com",
    'user' => "username",
    'database' => "database",
    'password' => "password",
);

$dbprefix = "archiv_";           // Archiv-Tabellen: archiv_Composition, archiv_config, …
// Melde identity (User, Permissions, SSO, …) — MUST differ from $dbprefix.
// Same database as Melde is correct; only the table-name prefix differs.
// Dev example: $dbprefix = "archiv-dev_"; $identityPrefix = "meldeliste-dev_";
$identityPrefix = "meldeliste_";

$mailconfig = array(
    'server' => "smtp.myprovider.com",
    'user' => "email@myserver.com",
    'password' => "password",
    'port' => 587,
    'from' => "frommail@myserver.com",
    'fromName' => "My Organization",
    'secure' => "tls",
    'subjectprefix' => '[My Organization] ',
);

$conn = mysqli_connect($sql['server'], $sql['user'], $sql['password']) or die (mysqli_error($conn));
global $conn;
mysqli_select_db($GLOBALS['conn'], $sql['database']) or die(mysqli_error($conn));

$cronID = 'xxxx-xxxx-xxxx-xxxx';
// Opt-in HTTP remote backup token (min 32 chars). Empty = HTTP backup disabled.
// Generate: openssl rand -hex 32
// Never reuse $cronID for HTTP backup.
$backupToken = '';

$googlemapsapi = "xxxx-xxxx-xxxx-xxxx";

global $mailconfig;
global $cronID;
global $backupToken;
global $dbprefix;
global $identityPrefix;
global $googlemapsapi;
?>
