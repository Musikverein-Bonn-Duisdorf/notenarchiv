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
    if((int)$n->Index > 0 && !empty($_FILES['avatar']['tmp_name'])) {
        $n->uploadAvatar($_FILES['avatar']);
    }
    header('Location: composers.php');
    exit;
}

if(isset($_POST['update']) && isset($_POST['Index']) && (int)$_POST['Index'] > 0) {
    $n = new Composer;
    $n->load_by_id((int)$_POST['Index']);
    if((int)$n->Index > 0) {
        $n->fill_from_array($_POST);
        $n->save();
        if(!empty($_FILES['avatar']['tmp_name'])) {
            $n->uploadAvatar($_FILES['avatar']);
        }
    }
    header('Location: composers.php');
    exit;
}

if(isset($_POST['delete']) && isset($_POST['Index']) && (int)$_POST['Index'] > 0) {
    $n = new Composer;
    $n->load_by_id((int)$_POST['Index']);
    if((int)$n->Index > 0) {
        $n->delete();
    }
    header('Location: composers.php');
    exit;
}

if(isset($_POST['uploadAvatar']) && isset($_POST['Index']) && (int)$_POST['Index'] > 0) {
    $n = new Composer;
    $n->load_by_id((int)$_POST['Index']);
    if((int)$n->Index > 0 && !empty($_FILES['avatar'])) {
        $n->uploadAvatar($_FILES['avatar']);
    }
    header('Location: new-composer.php?id='.(int)$_POST['Index']);
    exit;
}

if(isset($_POST['deleteAvatar']) && isset($_POST['Index']) && (int)$_POST['Index'] > 0) {
    $n = new Composer;
    $n->load_by_id((int)$_POST['Index']);
    if((int)$n->Index > 0) {
        $n->deleteAvatar();
    }
    header('Location: new-composer.php?id='.(int)$_POST['Index']);
    exit;
}

$entity = new Composer;
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
$backLink = '<a class="w3-button w3-border '.htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8').'" href="composers.php">Zur Liste</a>';
$delName = trim(archivPlainText($entity->FirstName).' '.archivPlainText($entity->LastName));
?>
<div class="w3-container w3-margin-bottom termin-page">
<div class="profile-shell termin-shell">
  <header class="profile-hero">
    <div class="profile-hero-text">
      <p class="profile-kicker">Komponist</p>
      <h2 class="profile-title"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
    </div>
    <div class="profile-hero-actions">
      <div class="profile-actions">
        <div class="profile-actions-primary">
          <input class="w3-btn profile-btn-primary <?php echo htmlspecialchars($btnSubmit, ENT_QUOTES, 'UTF-8'); ?> w3-border w3-mobile" type="submit" form="archivEntityEditForm" name="<?php echo $isEdit ? 'update' : 'insert'; ?>" value="Speichern">
          <?php echo $backLink; ?>
<?php if($isEdit) {
    $delConfirm = ($delName !== '' ? 'Soll „'.$delName.'“ gelöscht werden?' : 'Soll dieser Eintrag gelöscht werden?');
    echo archivDeleteAction(
        'archivDelComposer'.(int)$entity->Index,
        $delConfirm,
        'w3-btn '.htmlspecialchars($btnDelete, ENT_QUOTES, 'UTF-8').' w3-border w3-mobile',
        '<input type="hidden" name="Index" value="'.(int)$entity->Index.'">'
            .'<input type="hidden" name="delete" value="1">',
        'new-composer.php'
    );
} ?>
        </div>
      </div>
    </div>
  </header>

<form id="archivEntityEditForm" class="profile-form" action="new-composer.php" method="POST" enctype="multipart/form-data">
<?php if($isEdit) { ?>
  <input type="hidden" name="Index" value="<?php echo (int)$entity->Index; ?>">
<?php } ?>
  <div class="termin-grid">
    <section class="profile-col" aria-labelledby="new-composer-stamm">
      <h3 id="new-composer-stamm" class="profile-col-title">Name</h3>
<?php if($isEdit) {
    echo archivEntityAvatarFormFields('Composers', (int)$entity->Index, $inputBg, $btnSubmit, $btnDelete, $entity->avatarInitials());
} else { ?>
      <div class="profile-field">
        <label class="profile-label" for="composerAvatar">Foto</label>
        <input id="composerAvatar" type="file" name="avatar" accept=".png,.jpeg,.gif,.jpg,.webp,image/*" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>">
      </div>
<?php } ?>
      <div class="profile-field">
        <label class="profile-label" for="composerFirst">Vorname</label>
        <input id="composerFirst" name="FirstName" type="text" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo $isEdit ? archivEscHtml($entity->FirstName) : ''; ?>">
      </div>
      <div class="profile-field">
        <label class="profile-label" for="composerLast">Nachname</label>
        <input id="composerLast" name="LastName" type="text" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo $isEdit ? archivEscHtml($entity->LastName) : ''; ?>" required>
      </div>
    </section>
<?php if($isEdit) {
    $asComposer = $entity->getCompositionSummariesForRole('Composer', 100);
    $asArranger = $entity->getCompositionSummariesForRole('Arranger', 100);
    $nComposer = $entity->countCompositionsAsComposer();
    $nArranger = $entity->countCompositionsAsArranger();
?>
    <section class="profile-col" aria-labelledby="composer-pieces-composer">
      <h3 id="composer-pieces-composer" class="profile-col-title">Stücke als Komponist (<?php echo (int)$nComposer; ?>)</h3>
<?php if(!$asComposer) { ?>
      <div class="profile-field"><div class="profile-value">—</div></div>
<?php } else {
    foreach($asComposer as $item) {
        $compId = (int)$item['id'];
        $reg = $item['registrationNumber'] !== null ? (string)$item['registrationNumber'] : '';
        $title = (string)$item['title'];
        echo '<div class="profile-field">';
        echo '<span class="profile-label">'.archivEscHtml($reg !== '' ? $reg : '—').'</span>';
        echo '<div class="profile-value"><a href="composition.php?id='.$compId.'">'.archivEscHtml($title !== '' ? $title : '—').'</a></div>';
        echo '</div>';
    }
} ?>
    </section>
    <section class="profile-col" aria-labelledby="composer-pieces-arranger">
      <h3 id="composer-pieces-arranger" class="profile-col-title">Stücke als Arrangeur (<?php echo (int)$nArranger; ?>)</h3>
<?php if(!$asArranger) { ?>
      <div class="profile-field"><div class="profile-value">—</div></div>
<?php } else {
    foreach($asArranger as $item) {
        $compId = (int)$item['id'];
        $reg = $item['registrationNumber'] !== null ? (string)$item['registrationNumber'] : '';
        $title = (string)$item['title'];
        echo '<div class="profile-field">';
        echo '<span class="profile-label">'.archivEscHtml($reg !== '' ? $reg : '—').'</span>';
        echo '<div class="profile-value"><a href="composition.php?id='.$compId.'">'.archivEscHtml($title !== '' ? $title : '—').'</a></div>';
        echo '</div>';
    }
} ?>
    </section>
<?php } ?>
  </div>
</form>
</div>
</div>
include 'common/footer.php';
?>
