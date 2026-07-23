<?php
$navColor = htmlspecialchars((string)$optionsDB['colorNav'], ENT_QUOTES, 'UTF-8');
$navAdminColor = htmlspecialchars((string)$optionsDB['colorNavAdmin'], ENT_QUOTES, 'UTF-8');
$isAdminNav = !empty($_SESSION['admin']);
$uidNav = isset($_SESSION['userid']) ? (int)$_SESSION['userid'] : 0;
$canEditConfig = false;
$canShowLog = false;
if($uidNav > 0) {
    $sqlAd = sprintf("SELECT `Admin` FROM `%sUser` WHERE `Index` = %d LIMIT 1;", identityPrefix(), $uidNav);
    $dbrAd = @mysqli_query($GLOBALS['conn'], $sqlAd);
    $rowAd = ($dbrAd) ? mysqli_fetch_assoc($dbrAd) : null;
    $isUserAdmin = $rowAd && !empty($rowAd['Admin']);
    $permsNav = IdentityPermissions::loadForUser($uidNav);
    $canEditConfig = $isUserAdmin || $permsNav->getPermission('perm_editConfig');
    $canShowLog = $isUserAdmin || $permsNav->getPermission('perm_showLog');
}
$showAdminNav = $isAdminNav || $canEditConfig || $canShowLog;
$meldeUrl = isset($optionsDB['urlMeldeliste']) ? trim((string)$optionsDB['urlMeldeliste']) : '';
$masterPage = isset($optionsDB['MasterPage']) ? trim((string)$optionsDB['MasterPage']) : '';
$homeUrl = isset($GLOBALS['optionsDB']['WebSiteURL']) ? (string)$GLOBALS['optionsDB']['WebSiteURL'] : 'index.php';
?>
<div class="app-titlebar <?php echo htmlspecialchars((string)$optionsDB['colorTitle'], ENT_QUOTES, 'UTF-8'); ?>">
  <h1 class="app-titlebar-name w3-hide-small"><?php echo htmlspecialchars((string)$optionsDB['WebSiteName'], ENT_QUOTES, 'UTF-8'); ?></h1>
  <h1 class="app-titlebar-name w3-hide-large w3-hide-medium"><?php echo htmlspecialchars((string)$optionsDB['WebSiteNameShort'], ENT_QUOTES, 'UTF-8'); ?></h1>
  <?php if($masterPage !== '') { ?>
  <a class="app-titlebar-logo" href="<?php echo htmlspecialchars($masterPage, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" title="Vereinshomepage">
    <img src="imgs/Logo.png" alt="Vereinshomepage">
  </a>
  <?php } else { ?>
  <img class="app-titlebar-logo" src="imgs/Logo.png" alt="">
  <?php } ?>
  <p class="app-titlebar-user"><?php
      echo htmlspecialchars((string)$_SESSION['username'], ENT_QUOTES, 'UTF-8');
      if($showAdminNav) echo ' (Admin)';
  ?></p>
</div>
<div class="app-shell">
<nav class="app-nav <?php echo $navColor; ?>" aria-label="Hauptnavigation">
  <div class="app-nav-primary">
    <a class="app-nav-item <?php getPage('home', 'system'); ?>" href="<?php echo htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8'); ?>" title="Stücke">
      <i class="fas fa-home" aria-hidden="true"></i><span class="nav-label">Stücke</span>
    </a>
    <a class="app-nav-item <?php getPage('collections', 'system'); ?>" href="collections.php" title="Mappen">
      <i class="fas fa-folder-open" aria-hidden="true"></i><span class="nav-label">Mappen</span>
    </a>
    <a class="app-nav-item <?php getPage('composers', 'system'); ?>" href="composers.php" title="Komponisten">
      <i class="fas fa-feather" aria-hidden="true"></i><span class="nav-label">Komponisten</span>
    </a>
    <a class="app-nav-item <?php getPage('publishers', 'system'); ?>" href="publishers.php" title="Verlage">
      <i class="fas fa-industry" aria-hidden="true"></i><span class="nav-label">Verlage</span>
    </a>
<?php if($meldeUrl !== '') { ?>
    <a class="app-nav-item app-nav-item--secondary <?php echo htmlspecialchars(navGroupClass('system'), ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($meldeUrl, ENT_QUOTES, 'UTF-8'); ?>" title="Meldeliste">
      <i class="fas fa-clipboard-list" aria-hidden="true"></i><span class="nav-label">Meldeliste</span>
    </a>
<?php } ?>
    <a class="app-nav-item app-nav-item--secondary <?php getPage('help', 'system'); ?>" href="help.php" title="Hilfe">
      <i class="fas fa-circle-question" aria-hidden="true"></i><span class="nav-label">Hilfe</span>
    </a>
  </div>

  <div class="app-nav-more-wrap">
    <button type="button" class="app-nav-item app-nav-more-toggle <?php echo htmlspecialchars(navGroupClass('system'), ENT_QUOTES, 'UTF-8'); ?>" id="appNavMoreToggle" aria-expanded="false" aria-controls="appNavMorePanel" title="Mehr">
      <i class="fas fa-ellipsis-h" aria-hidden="true"></i><span class="nav-label">Mehr</span>
    </button>
    <div class="app-nav-more-backdrop" id="appNavMoreBackdrop" hidden></div>
    <div class="app-nav-more-panel <?php echo $navColor; ?>" id="appNavMorePanel" role="dialog" aria-label="Weitere Navigation">
      <div class="app-nav-more-head">
        <span>Mehr</span>
        <button type="button" class="app-nav-more-close" id="appNavMoreClose" aria-label="Schließen">&times;</button>
      </div>
      <div class="app-nav-more-body">
<?php if($meldeUrl !== '') { ?>
        <a class="app-nav-item app-nav-more-only-mobile <?php echo htmlspecialchars(navGroupClass('system'), ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($meldeUrl, ENT_QUOTES, 'UTF-8'); ?>" title="Meldeliste">
          <i class="fas fa-clipboard-list" aria-hidden="true"></i><span class="nav-label">Meldeliste</span>
        </a>
<?php } ?>
        <a class="app-nav-item app-nav-more-only-mobile <?php getPage('help', 'system'); ?>" href="help.php" title="Hilfe">
          <i class="fas fa-circle-question" aria-hidden="true"></i><span class="nav-label">Hilfe</span>
        </a>
<?php if($showAdminNav) { ?>
        <div class="admin-nav app-nav-admin">
          <div class="app-nav-admin-title"><i class="fas fa-wrench" aria-hidden="true"></i><span class="nav-label">Admin</span></div>
          <div class="w3-bar-block <?php echo $navAdminColor; ?>">
            <div class="w3-dropdown-hover w3-mobile admin-nav-group<?php echo adminNavGroupActiveClass(array('newcomposition', 'config', 'log', 'update')); ?>">
              <button type="button" class="w3-button w3-mobile w3-block w3-left-align <?php echo htmlspecialchars(navGroupClass('system'), ENT_QUOTES, 'UTF-8'); ?>">Verwaltung <i class="fas fa-caret-right admin-nav-caret"></i></button>
              <div class="w3-dropdown-content w3-bar-block w3-card-4 <?php echo $navAdminColor; ?> w3-mobile">
                <a title="Stück anlegen" href="new-composition.php" class="w3-bar-item w3-button w3-mobile <?php getAdminPage('newcomposition'); ?>"><i class="fas fa-plus-circle"></i> Stück anlegen</a>
<?php if($canEditConfig) { ?>
                <a title="Konfiguration" href="config-menu.php" class="w3-bar-item w3-button w3-mobile <?php getAdminPage('config'); ?>"><i class="fas fa-cogs"></i> Konfiguration</a>
                <a title="Update" href="update.php" class="w3-bar-item w3-button w3-mobile <?php getAdminPage('update'); ?>"><i class="fas fa-code-branch"></i> Update</a>
<?php } ?>
<?php if($canShowLog) { ?>
                <a title="Log" href="log.php" class="w3-bar-item w3-button w3-mobile <?php getAdminPage('log'); ?>"><i class="fas fa-poll"></i> Log</a>
<?php } ?>
              </div>
            </div>
          </div>
        </div>
<?php } ?>
        <a class="app-nav-item <?php echo htmlspecialchars(navGroupClass('system'), ENT_QUOTES, 'UTF-8'); ?>" href="logout.php" title="Ausloggen">
          <i class="fas fa-sign-out-alt" aria-hidden="true"></i><span class="nav-label">Ausloggen</span>
        </a>
      </div>
    </div>
  </div>
</nav>
<div class="app-main">
<?php
$motdShort = isset($optionsDB['MessageOfTheDayShort']) ? trim((string)$optionsDB['MessageOfTheDayShort']) : '';
if($motdShort !== '') {
?>
<div class="w3-container w3-card w3-padding <?php echo htmlspecialchars((string)$GLOBALS['optionsDB']['colorWarning'], ENT_QUOTES, 'UTF-8'); ?>">
  <div class="w3-col l3 m3 s2 w3-center">
    <i class="fas fa-exclamation-triangle"></i>
  </div>
  <div class="w3-col l6 m6 s8 w3-center">
    <?php echo $motdShort; ?>
  </div>
  <div class="w3-col l3 m3 s2 w3-center">
    <i class="fas fa-exclamation-triangle"></i>
  </div>
</div>
<?php
    ob_start();
?>
<div id="MessageOfTheDay" class="w3-modal">
  <div class="w3-modal-content">
    <div class="w3-container">
      <?php echo isset($optionsDB['MessageOfTheDay']) ? $optionsDB['MessageOfTheDay'] : ''; ?>
      <div class="w3-center"><button class="w3-btn w3-blue w3-padding w3-center" onclick="document.getElementById('MessageOfTheDay').style.display='none'">Verstanden</button></div>
      <div class="w3-container">&nbsp;</div>
    </div>
  </div>
</div>
<?php
    if(!isset($_SESSION['MessageOfTheDay'])) {
        $_SESSION['MessageOfTheDay'] = true;
?>
<script>document.getElementById('MessageOfTheDay').style.display='block';</script>
<?php
    }
    deferPageModalHtml(ob_get_clean());
}
?>
<script src="<?php echo assetUrl('js/app-nav.js'); ?>"></script>
