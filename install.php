<?php
/**
 * First-time database setup for Notenarchiv.
 * Open without login on fresh instances; otherwise requires Admin session.
 */
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();

$configFile = __DIR__.'/common/config.php';
if(!is_readable($configFile)) {
    http_response_code(500);
    echo "<!DOCTYPE html><html lang=\"de\"><head><meta charset=\"utf-8\"><title>Install</title>";
    echo "<link rel=\"stylesheet\" href=\"styles/w3.css\"></head><body class=\"w3-container\">";
    echo "<div class=\"w3-panel w3-red\"><h2>Konfiguration fehlt</h2>";
    echo "<p>Bitte <code>common/config_sample.php</code> nach <code>common/config.php</code> kopieren und die MySQL-Zugangsdaten eintragen.</p></div></body></html>";
    exit;
}

require_once $configFile;

if(!function_exists('sqlerror')) {
    function sqlerror() {
        if(!isset($GLOBALS['conn']) || !mysqli_errno($GLOBALS['conn'])) return;
        echo "<div class=\"w3-panel w3-red\"><b>SQL ERROR</b> "
            .htmlspecialchars(mysqli_errno($GLOBALS['conn']).": ".mysqli_error($GLOBALS['conn']))
            ."</div>\n";
    }
}

require_once __DIR__.'/libs/SQLtable.php';
require_once __DIR__.'/config/ConfigDefaults.php';
require_once __DIR__.'/libs/SchemaManager.php';

$manager = new SchemaManager();
$isAdminSession = !empty($_SESSION['userid']) && !empty($_SESSION['admin']);

if(!$isAdminSession && !empty($_SESSION['userid'])) {
    header('Location: login.php');
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$reportManager = null;
$allowedActions = array('check', 'create', 'repair');

if(in_array($action, $allowedActions, true)) {
    if($action === 'check') {
        $manager->check();
    }
    elseif($action === 'create') {
        $manager->create();
        $manager->repair();
    }
    else {
        $manager->repair();
    }
    $reportManager = $manager;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Notenarchiv – Installation</title>
  <link rel="stylesheet" href="styles/w3.css">
</head>
<body class="w3-light-grey">
<div class="w3-container w3-teal">
  <h1>Datenbank-Installation</h1>
</div>
<div class="w3-container w3-margin">
  <div class="w3-panel w3-pale-blue w3-padding">
    <p>Schema prüfen, anlegen oder reparieren (<code>archiv_</code>-Tabellen). Identity bleibt bei <code>meldeliste_User</code>.</p>
    <p>Voraussetzung: MySQL-Datenbank angelegt und <code>common/config.php</code> korrekt befüllt.</p>
  </div>

  <form method="post" class="w3-margin-bottom">
    <button class="w3-button w3-blue" type="submit" name="action" value="check">Nur prüfen</button>
    <button class="w3-button w3-green" type="submit" name="action" value="create">Datenbank anlegen</button>
    <button class="w3-button w3-orange" type="submit" name="action" value="repair">Datenbank reparieren</button>
  </form>

<?php
if($reportManager) {
    echo "<div class=\"w3-card w3-white w3-padding\">";
    echo "<h3>Ergebnis</h3>";
    echo "<pre>".htmlspecialchars($reportManager->formatReportText())."</pre>";
    if(($action === 'create' || $action === 'repair') && !$reportManager->hasErrors()) {
        echo "<div class=\"w3-panel w3-green\"><p><b>Schema bereit.</b> Weiter zum <a href=\"login.php\">Login</a>.</p></div>";
    }
    echo "</div>";
}
?>
</div>
</body>
</html>
