<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
$_SESSION['page'] = 'newpublisher';
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
    $n = new Publisher;
    $n->fill_from_array($_POST);
    $n->save();
    if((int)$n->Index > 0 && !empty($_FILES['avatar']['tmp_name'])) {
        $n->uploadAvatar($_FILES['avatar']);
    }
    header('Location: publishers.php');
    exit;
}

include 'common/header.php';

$inputBg = isset($GLOBALS['optionsDB']['colorInputBackground'])
    ? (string)$GLOBALS['optionsDB']['colorInputBackground']
    : '';
$btnSubmit = isset($GLOBALS['optionsDB']['colorBtnSubmit'])
    ? (string)$GLOBALS['optionsDB']['colorBtnSubmit']
    : '';
$backLink = '<a class="w3-button w3-border '.htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8').'" href="publishers.php">Zur Liste</a>';
?>
<div class="w3-container w3-margin-bottom termin-page">
<div class="profile-shell termin-shell">
<form class="profile-form" action="new-publisher.php" method="POST" enctype="multipart/form-data">
  <header class="profile-hero">
    <div class="profile-hero-text">
      <p class="profile-kicker">Verlag</p>
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
    <section class="profile-col" aria-labelledby="new-publisher-stamm">
      <h3 id="new-publisher-stamm" class="profile-col-title">Angaben</h3>
      <div class="profile-field">
        <label class="profile-label" for="publisherName">Name</label>
        <input id="publisherName" name="Name" type="text" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" required>
      </div>
      <div class="profile-field">
        <label class="profile-label" for="publisherAddress">Adresse</label>
        <textarea id="publisherAddress" name="Address" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" rows="3"></textarea>
      </div>
      <div class="profile-field">
        <label class="profile-label" for="publisherAvatar">Foto</label>
        <input id="publisherAvatar" type="file" name="avatar" accept=".png,.jpeg,.gif,.jpg,.webp,image/*" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>">
      </div>
    </section>
  </div>
</form>
</div>
</div>
<?php
include 'common/footer.php';
?>
