<?php
$sql = array(
    'server' => "localhost",
    'user' => "MVD",
    'database' => "MVD_Archive",
    'password' => "1949 e.V.",
);

$dbprefix = "meldeliste_";

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

$googlemapsapi = "xxxx-xxxx-xxxx-xxxx";

global $mailconfig;
global $cronID;
global $dbprefix;
global $googlemapsapi;
?>
