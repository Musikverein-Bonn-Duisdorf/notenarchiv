<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
$_SESSION['page'] = 'newcomposer';
$_SESSION['adminpage'] = true;

include_once 'common/include.php';
mysqli_select_db($GLOBALS['conn'], $sql['database']) or die(mysqli_error($GLOBALS['conn']));

if(empty($_SESSION['admin'])) {
    include 'common/header.php';
    echo '<meta http-equiv="refresh" content="0; URL=index.php" />';
    include 'common/footer.php';
    exit;
}

if(isset($_POST['insert'])) {
    $n = new Composer;
    $n->fill_from_array($_POST);
    $n->save();
    header('Location: composers.php');
    exit;
}

include 'common/header.php';

$inputBg = isset($GLOBALS['optionsDB']['colorInputBackground'])
    ? (string)$GLOBALS['optionsDB']['colorInputBackground']
    : '';
$btnSubmit = isset($GLOBALS['optionsDB']['colorBtnSubmit'])
    ? (string)$GLOBALS['optionsDB']['colorBtnSubmit']
    : '';
$backLink = '<a class="w3-button w3-border '.htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8').'" href="composers.php">Zur Liste</a>';
?>
<div class="w3-container w3-margin-bottom termin-page">
<div class="profile-shell termin-shell">
<form class="profile-form" action="new-composer.php" method="POST">
  <header class="profile-hero">
    <div class="profile-hero-text">
      <p class="profile-kicker">Komponist</p>
      <h2 class="profile-title">Anlegen</h2>
    </div>
    <div class="profile-hero-actions">
      <div class="profile-actions">
        <div class="profile-actions-primary">
          <input class="w3-btn profile-btn-primary <?php echo htmlspecialchars($btnSubmit, ENT_QUOTES, 'UTF-8'); ?> w3-border w3-mobile" type="submit" name="insert" value="Speichern">
          <?php echo $backLink; ?>
        </div>
      </div>
    </div>
  </header>

  <div class="termin-grid">
    <section class="profile-col" aria-labelledby="new-composer-stamm">
      <h3 id="new-composer-stamm" class="profile-col-title">Name</h3>
      <div class="profile-field">
        <label class="profile-label" for="composerFirst">Vorname</label>
        <input id="composerFirst" name="FirstName" type="text" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div class="profile-field">
        <label class="profile-label" for="composerLast">Nachname</label>
        <input id="composerLast" name="LastName" type="text" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" required>
      </div>
    </section>
  </div>
</form>
</div>
</div>
<?php
include 'common/footer.php';
?>
