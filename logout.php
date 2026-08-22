<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
?>
<!DOCTYPE html>
<html lang="de">
  <head>
      <?php
          include 'common/include.php';
      ?>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <link rel="stylesheet" href="<?php echo assetUrl('styles/w3.css'); ?>">
      <link rel="stylesheet" href="<?php echo assetUrl('styles/w3-colors-highway.css'); ?>">
      <link rel="stylesheet" href="<?php echo assetUrl('styles/w3-color-mvd.css'); ?>">
      <link rel="stylesheet" href="<?php echo assetUrl('styles/custom.css'); ?>">
      <?php echo renderConfigColorCss(); ?>
      <link rel="icon" href="<?php echo htmlspecialchars((string)$GLOBALS['optionsDB']['favicon'], ENT_QUOTES, 'UTF-8'); ?>" type="image/x-icon">
      <title><?php echo htmlspecialchars((string)$optionsDB['WebSiteName'], ENT_QUOTES, 'UTF-8'); ?></title>
  </head>
  <body class="<?php echo htmlspecialchars((string)$GLOBALS['optionsDB']['colorBackground'], ENT_QUOTES, 'UTF-8'); ?> app-layout">
      <div class="app-titlebar <?php echo htmlspecialchars((string)$optionsDB['colorTitle'], ENT_QUOTES, 'UTF-8'); ?>">
        <h1 class="app-titlebar-name"><?php echo htmlspecialchars((string)$optionsDB['WebSiteName'], ENT_QUOTES, 'UTF-8'); ?></h1>
      </div>
<meta http-equiv="refresh" content="3; URL='login.php'" />
  <div class="w3-panel w3-mobile w3-center <?php echo $GLOBALS['optionsDB']['colorSuccess']; ?>"><h2>Logout erfolgreich.</h2></div>
<?php
    if(!empty($_SESSION['userid'])) {
        $logentry = new Log;
        $logentry->info("Logout.");
    }
session_destroy();
include "common/footer.php";
?>
