<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
$_SESSION['page'] = 'newcollection';
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
    $n = new Collections;
    $n->fill_from_array($_POST);
    $n->save();
    header('Location: collections.php');
    exit;
}

if(isset($_POST['update']) && isset($_POST['Index']) && (int)$_POST['Index'] > 0) {
    $n = new Collections;
    $n->load_by_id((int)$_POST['Index']);
    if((int)$n->Index > 0) {
        $n->fill_from_array($_POST);
        $n->save();
    }
    header('Location: collections.php');
    exit;
}

if(isset($_POST['delete']) && isset($_POST['Index']) && (int)$_POST['Index'] > 0) {
    $n = new Collections;
    $n->load_by_id((int)$_POST['Index']);
    if((int)$n->Index > 0) {
        $n->delete();
    }
    header('Location: collections.php');
    exit;
}

$entity = new Collections;
$isEdit = false;
if(isset($_POST['id']) && (int)$_POST['id'] > 0) {
    $entity->load_by_id((int)$_POST['id']);
    $isEdit = ((int)$entity->Index > 0);
} elseif(isset($_GET['id']) && (int)$_GET['id'] > 0) {
    $entity->load_by_id((int)$_GET['id']);
    $isEdit = ((int)$entity->Index > 0);
}

include 'common/header.php';

$inputBg = isset($GLOBALS['optionsDB']['colorInputBackground'])
    ? (string)$GLOBALS['optionsDB']['colorInputBackground']
    : '';
$btnSubmit = isset($GLOBALS['optionsDB']['colorBtnSubmit'])
    ? (string)$GLOBALS['optionsDB']['colorBtnSubmit']
    : '';
$btnDelete = isset($GLOBALS['optionsDB']['colorBtnDelete'])
    ? (string)$GLOBALS['optionsDB']['colorBtnDelete']
    : 'w3-red';
$pageTitle = $isEdit ? 'Bearbeiten' : 'Anlegen';
$backLink = '<a class="w3-button w3-border '.htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8').'" href="collections.php">Zur Liste</a>';
$delName = archivPlainText($entity->Name);
?>
<div class="w3-container w3-margin-bottom termin-page">
<div class="profile-shell termin-shell">
<form class="profile-form" action="new-collection.php" method="POST">
<?php if($isEdit) { ?>
  <input type="hidden" name="Index" value="<?php echo (int)$entity->Index; ?>">
<?php } ?>
  <header class="profile-hero">
    <div class="profile-hero-text">
      <p class="profile-kicker">Sammlung</p>
      <h2 class="profile-title"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
    </div>
    <div class="profile-hero-actions">
      <div class="profile-actions">
        <div class="profile-actions-primary">
          <input class="w3-btn profile-btn-primary <?php echo htmlspecialchars($btnSubmit, ENT_QUOTES, 'UTF-8'); ?> w3-border w3-mobile" type="submit" name="<?php echo $isEdit ? 'update' : 'insert'; ?>" value="Speichern">
          <?php echo $backLink; ?>
        </div>
<?php if($isEdit) { ?>
        <details class="profile-actions-more">
          <summary>Weitere Aktionen</summary>
          <div class="profile-actions-secondary">
            <button type="button" class="w3-btn <?php echo htmlspecialchars($btnDelete, ENT_QUOTES, 'UTF-8'); ?> w3-border w3-mobile" onclick="document.getElementById('delmodal').style.display='block'">Löschen</button>
          </div>
        </details>
<?php } ?>
      </div>
    </div>
  </header>

  <div class="termin-grid">
    <section class="profile-col" aria-labelledby="new-collection-stamm">
      <h3 id="new-collection-stamm" class="profile-col-title">Angaben</h3>
      <div class="profile-field">
        <label class="profile-label" for="collectionName">Name</label>
        <input id="collectionName" name="Name" type="text" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo $isEdit ? archivEscHtml($entity->Name) : ''; ?>" required>
      </div>
    </section>
  </div>
</form>
</div>
</div>
<?php if($isEdit) {
    ob_start();
?>
<div id="delmodal" class="w3-modal" role="dialog" aria-modal="true" aria-labelledby="delmodalTitle" style="display:none;"
     onclick="if(event.target===this){ this.style.display='none'; }">
  <div class="w3-modal-content">
    <div class="profile-shell modal-shell confirm-delete-modal">
      <header class="profile-hero">
        <div class="profile-hero-text">
          <p class="profile-kicker">Sammlung</p>
          <h2 class="profile-title" id="delmodalTitle">Löschen</h2>
        </div>
        <div class="profile-hero-actions">
          <button type="button" class="modal-close w3-button" onclick="document.getElementById('delmodal').style.display='none'" aria-label="Schließen">&times;</button>
        </div>
      </header>
      <div class="confirm-delete-body">
        <p class="profile-value">Soll <b><?php echo archivEscHtml($delName !== '' ? $delName : 'diese Sammlung'); ?></b> gelöscht werden?</p>
        <div class="profile-actions profile-actions--confirm">
          <div class="profile-actions-primary">
            <form action="new-collection.php" method="POST">
              <input type="hidden" name="Index" value="<?php echo (int)$entity->Index; ?>">
              <button class="w3-btn profile-btn-primary <?php echo htmlspecialchars($btnDelete, ENT_QUOTES, 'UTF-8'); ?> w3-border w3-mobile" type="submit" name="delete" value="1">Löschen</button>
            </form>
          </div>
          <button type="button" class="w3-btn w3-border w3-mobile" onclick="document.getElementById('delmodal').style.display='none'">Abbrechen</button>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
    deferPageModalHtml(ob_get_clean());
}
include 'common/footer.php';
?>
