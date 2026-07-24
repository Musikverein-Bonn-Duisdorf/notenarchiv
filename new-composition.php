<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
$_SESSION['page'] = 'newcomposition';
$_SESSION['adminpage'] = true;
include 'common/header.php';

if(empty($_SESSION['admin'])) {
    echo '<meta http-equiv="refresh" content="0; URL=index.php" />';
    include 'common/footer.php';
    exit;
}

$inputBg = isset($GLOBALS['optionsDB']['colorInputBackground'])
    ? (string)$GLOBALS['optionsDB']['colorInputBackground']
    : '';
$btnSubmit = isset($GLOBALS['optionsDB']['colorBtnSubmit'])
    ? (string)$GLOBALS['optionsDB']['colorBtnSubmit']
    : '';

$piece = new Composition;
$isEdit = false;
if(isset($_POST['Index']) && (int)$_POST['Index'] > 0) {
    $piece->load_by_id((int)$_POST['Index']);
    $isEdit = true;
} elseif(isset($_GET['id']) && (int)$_GET['id'] > 0) {
    $piece->load_by_id((int)$_GET['id']);
    $isEdit = ((int)$piece->Index > 0);
} else {
    $piece->RegistrationNumber = nextArchiverNumber();
}

$pageTitle = $isEdit ? 'Bearbeiten' : 'Anlegen';
$backHref = $isEdit ? 'composition.php?id='.(int)$piece->Index : 'index.php';
$backLabel = $isEdit ? 'Zum Stück' : 'Zur Liste';
$backLink = '<a class="w3-button w3-border '.htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8').'" href="'.htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($backLabel, ENT_QUOTES, 'UTF-8').'</a>';
?>
<div class="w3-container w3-margin-bottom termin-page">
<div class="profile-shell termin-shell">
<form class="profile-form" action="index.php" method="POST">
  <input name="Index" type="hidden" value="<?php echo (int)$piece->Index; ?>">

  <header class="profile-hero">
    <div class="profile-hero-text">
      <p class="profile-kicker">Stück</p>
      <h2 class="profile-title"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
    </div>
    <div class="profile-hero-actions">
      <div class="profile-actions">
        <div class="profile-actions-primary">
          <input class="w3-btn profile-btn-primary <?php echo htmlspecialchars($btnSubmit, ENT_QUOTES, 'UTF-8'); ?> w3-border w3-mobile" type="submit" name="save" value="Speichern">
          <?php echo $backLink; ?>
        </div>
      </div>
    </div>
  </header>

  <div class="termin-grid">
    <section class="profile-col" aria-labelledby="edit-col-stamm">
      <h3 id="edit-col-stamm" class="profile-col-title">Stammdaten</h3>
      <div class="profile-field">
        <label class="profile-label" for="editTitle">Titel</label>
        <input id="editTitle" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" name="Title" type="text" value="<?php echo archivEscHtml($piece->Title); ?>" required>
      </div>
      <div class="profile-field">
        <label class="profile-label" for="editReg">Inventarnummer</label>
        <input id="editReg" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" name="RegistrationNumber" type="number" value="<?php echo archivEscHtml($piece->RegistrationNumber); ?>">
      </div>
      <div class="profile-field">
        <label class="profile-label" for="editComposer">Komponist</label>
        <select id="editComposer" name="Composer" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>">
          <?php echo ComposersOption($piece->Composer); ?>
        </select>
      </div>
      <div class="profile-field">
        <label class="profile-label" for="editArranger">Arrangeur</label>
        <select id="editArranger" name="Arranger" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>">
          <?php echo ComposersOption($piece->Arranger); ?>
        </select>
      </div>
    </section>

    <section class="profile-col" aria-labelledby="edit-col-meta">
      <h3 id="edit-col-meta" class="profile-col-title">Verlag &amp; Angaben</h3>
      <div class="profile-field">
        <label class="profile-label" for="editYear">Jahr</label>
        <input id="editYear" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" name="Year" type="number" value="<?php echo archivEscHtml($piece->Year); ?>">
      </div>
      <div class="profile-field">
        <label class="profile-label" for="editPublisher">Verlag</label>
        <select id="editPublisher" name="Publisher" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>">
          <?php echo PublishersOption($piece->Publisher); ?>
        </select>
      </div>
      <div class="profile-field">
        <label class="profile-label" for="editGrade">Schwierigkeit</label>
        <input id="editGrade" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" name="Grade" type="number" step="0.5" min="0" max="6" value="<?php echo archivEscHtml($piece->Grade); ?>">
      </div>
      <div class="profile-field">
        <label class="profile-label" for="editTime">Spielzeit</label>
        <input id="editTime" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" name="PerformanceTime" type="text" value="<?php echo archivEscHtml($piece->PerformanceTime); ?>" placeholder="00:00">
      </div>
    </section>
<?php if($isEdit) { ?>
    <section class="profile-col" aria-labelledby="edit-col-collections">
      <h3 id="edit-col-collections" class="profile-col-title">Sammlungen</h3>
      <?php
        echo archivCollectionChipsEditorHtml(
            'piece-edit-coll',
            'mail-recipient-chip--collection',
            'collectionsSpec',
            archivCollectionsCatalog(),
            $piece->getCollectionsChipSpec(),
            'Sammlung…'
        );
      ?>
    </section>
<?php } ?>
  </div>
</form>
</div>
</div>
<?php
include 'common/footer.php';
?>
