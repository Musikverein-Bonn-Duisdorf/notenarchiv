<!DOCTYPE html>
<html lang="de">
  <head>
      <?php
          if(!headers_sent()) {
              header('Content-Type: text/html; charset=utf-8');
          }
      ?>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <?php
          include_once 'include.php';
          $assetV = isset($GLOBALS['version']['Hash']) ? $GLOBALS['version']['Hash'] : '0';
          $cssUrl = function ($rel) use ($assetV) {
              $mtime = @filemtime(__DIR__ . '/../' . $rel);
              return htmlspecialchars($rel . '?' . $assetV . '-' . $mtime, ENT_QUOTES, 'UTF-8');
          };
      ?>
      <link rel="stylesheet" href="<?php echo $cssUrl('styles/w3.css'); ?>">
      <link rel="stylesheet" href="<?php echo $cssUrl('styles/w3-colors-highway.css'); ?>">
      <link rel="stylesheet" href="<?php echo $cssUrl('styles/w3-color-mvd.css'); ?>">
      <link rel="stylesheet" href="<?php echo $cssUrl('styles/custom.css'); ?>">
      <link rel="stylesheet" href="<?php echo $cssUrl('styles/fontawesome-free-6.4.2-web/css/fontawesome.css'); ?>">
      <link rel="stylesheet" href="<?php echo $cssUrl('styles/fontawesome-free-6.4.2-web/css/brands.css'); ?>">
      <link rel="stylesheet" href="<?php echo $cssUrl('styles/fontawesome-free-6.4.2-web/css/solid.css'); ?>">
      <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
      <?php echo renderConfigColorCss(); ?>
      <link rel="icon" href="<?php echo htmlspecialchars((string)$GLOBALS['optionsDB']['favicon'], ENT_QUOTES, 'UTF-8'); ?>" type="image/x-icon">
      <?php
        mysqli_select_db($GLOBALS['conn'], $sql['database']) or die(mysqli_error($conn));
        tryMeldeSsoLoginFromRequest();
        refreshSessionAdmin();
        if(!loggedIn()) {
              ?>
              <meta http-equiv="refresh" content="2; URL='login.php'" />
              <?php
              die("<div class=\"w3-panel ".$optionsDB['colorLogWarning']."\"><h2>Nicht eingeloggt...</h2></div>");
          }
          if(!empty($_SESSION['singleUsePW'])) {
              ?>
              <meta http-equiv="refresh" content="0; URL='changePW.php'" />
              <?php
              die("<div class=\"w3-panel ".$optionsDB['colorLogWarning']."\"><h2>Passwort &auml;ndern...</h2></div>");
          }
      ?>
      <title><?php echo htmlspecialchars((string)$optionsDB['WebSiteName'], ENT_QUOTES, 'UTF-8'); ?></title>
  </head>
  <body class="<?php echo htmlspecialchars((string)$GLOBALS['optionsDB']['colorBackground'], ENT_QUOTES, 'UTF-8'); ?> app-layout">
<?php
include "common/nav.php";
$GLOBALS['mlHeaderRendered'] = true;
?>
