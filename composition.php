<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
$_SESSION['page'] = 'composition';

include_once 'common/include.php';
mysqli_select_db($GLOBALS['conn'], $sql['database']) or die(mysqli_error($GLOBALS['conn']));

$isAdmin = !empty($_SESSION['admin']);
$piece = new Composition;

// POST/Redirect/Get: must run before header.php (which already sends HTML).
if(isset($_POST['save']) && $isAdmin) {
    if(isset($_POST['Index']) && (int)$_POST['Index'] > 0) {
        $piece->load_by_id((int)$_POST['Index']);
    }
    $piece->fill_from_array($_POST);
    $piece->save();
    if((int)$piece->Index > 0 && isset($_POST['collectionsSpec'])) {
        $items = archivParseCollectionChipSpec($_POST['collectionsSpec']);
        if($items !== null) {
            archivSyncCollectionItemsForComposition((int)$piece->Index, $items);
        }
    }
    $redirId = (int)$piece->Index;
    redirectAfterPost($redirId > 0 ? ('composition.php?id='.$redirId) : 'composition.php');
}

include 'common/header.php';

$inputBg = isset($GLOBALS['optionsDB']['colorInputBackground'])
    ? (string)$GLOBALS['optionsDB']['colorInputBackground']
    : '';
$btnSubmit = isset($GLOBALS['optionsDB']['colorBtnSubmit'])
    ? (string)$GLOBALS['optionsDB']['colorBtnSubmit']
    : '';
$btnEdit = isset($GLOBALS['optionsDB']['colorBtnEdit'])
    ? (string)$GLOBALS['optionsDB']['colorBtnEdit']
    : '';
$isAdmin = !empty($_SESSION['admin']);

if(isset($_POST['delete']) && $isAdmin) {
    $piece->load_by_id((int)$_POST['Index']);
    $piece->deleteCover();
}
if(isset($_POST['syncCollections']) && isset($_POST['Composition']) && $isAdmin) {
    $piece->load_by_id((int)$_POST['Composition']);
    if((int)$piece->Index > 0 && isset($_POST['collectionsSpec'])) {
        $items = archivParseCollectionChipSpec($_POST['collectionsSpec']);
        if($items !== null) {
            archivSyncCollectionItemsForComposition((int)$piece->Index, $items);
        }
    }
}
if(isset($_POST['partupload']) && $isAdmin) {
    $piece->load_by_id((int)$_POST['Index']);
    $part = new Part;
    $part->load_by_id($_POST['pIndex']);
    $part->upload($_POST, $_FILES);
}
if(isset($_POST['partdelete']) && $isAdmin) {
    $piece->load_by_id((int)$_POST['Index']);
    $part = new Part;
    $part->load_by_id($_POST['pIndex']);
    $part->deleteFile();
}
if(isset($_POST['cover']) && $isAdmin) {
    $piece->load_by_id((int)$_POST['Index']);
    $target_file = $piece->getFilePathPHP().'cover'.strrchr($_FILES['coverImage']['name'], '.');
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    if(isset($_POST['submit'])) {
        $check = getimagesize($_FILES['coverImage']['tmp_name']);
        if($check === false) {
            $uploadOk = 0;
        }
        if($_FILES['coverImage']['size'] > 500000) {
            $uploadOk = 0;
        }
        if($imageFileType != 'jpg' && $imageFileType != 'png' && $imageFileType != 'jpeg' && $imageFileType != 'gif') {
            $uploadOk = 0;
        }
    }
    if($uploadOk != 0) {
        $piece->deleteCover(false);
        if(move_uploaded_file($_FILES['coverImage']['tmp_name'], $target_file)) {
            $piece->logCoverUpload($target_file);
        }
    }
}
if(isset($_POST['pieceID']) && $isAdmin) {
    $piece->load_by_id((int)$_POST['pieceID']);
    if(isset($_POST['instrument'])) {
        $part = new Part;
        $part->Instrument = $_POST['instrument'];
        $part->Composition = $_POST['pieceID'];
        $part->Part = $_POST['part'];
        $part->save();
    }
}

$id = 0;
if(isset($_GET['id'])) {
    $id = (int)$_GET['id'];
} elseif(isset($_POST['Index']) && (int)$_POST['Index'] > 0) {
    $id = (int)$_POST['Index'];
} elseif(isset($_POST['pieceID'])) {
    $id = (int)$_POST['pieceID'];
} elseif(isset($_POST['Composition'])) {
    $id = (int)$_POST['Composition'];
}

$isCreate = false;
if($id > 0) {
    if((int)$piece->Index < 1) {
        $piece->load_by_id($id);
    }
    if((int)$piece->Index < 1) {
        echo '<meta http-equiv="refresh" content="0; URL=index.php" />';
        include 'common/footer.php';
        exit;
    }
    $_SESSION['adminpage'] = false;
} else {
    if(!$isAdmin) {
        echo '<meta http-equiv="refresh" content="0; URL=index.php" />';
        include 'common/footer.php';
        exit;
    }
    $isCreate = true;
    $_SESSION['adminpage'] = true;
    $piece->RegistrationNumber = nextArchiverNumber();
}

$pieceTitle = archivPlainText($piece->Title);
$pieceComposer = archivPlainText($piece->ComposerName);
$formTitle = $isCreate ? 'Anlegen' : ($isAdmin ? 'Bearbeiten' : $pieceTitle);

if(!$isCreate) {
    ob_start();
    ?>
<div id="delmodal" class="w3-modal">
  <div class="w3-modal-content profile-shell modal-shell">
    <header class="profile-hero">
      <div class="profile-hero-text">
        <p class="profile-kicker">Stück</p>
        <h2 class="profile-title">Löschen</h2>
      </div>
      <button type="button" class="modal-close w3-button" onclick="document.getElementById('delmodal').style.display='none'" aria-label="Schließen">&times;</button>
    </header>
    <div class="profile-grid">
      <div class="profile-field">
        <p class="profile-label"><?php echo archivEscHtml($pieceTitle); ?></p>
      </div>
      <div class="profile-field profile-actions">
        <form action="index.php" method="POST" style="display:inline;">
          <button type="submit" name="Delete" value="<?php echo (int)$piece->Index; ?>" class="w3-button <?php echo $GLOBALS['optionsDB']['colorBtnDelete']; ?>">Löschen</button>
        </form>
        <button type="button" class="w3-button w3-border" onclick="document.getElementById('delmodal').style.display='none'">Abbrechen</button>
      </div>
    </div>
  </div>
</div>
<?php if(!$isAdmin) { ?>
<div id="collmodal" class="w3-modal">
  <div class="w3-modal-content profile-shell modal-shell">
    <header class="profile-hero">
      <div class="profile-hero-text">
        <p class="profile-kicker">Stück</p>
        <h2 class="profile-title">Sammlungen</h2>
      </div>
      <button type="button" class="modal-close w3-button" onclick="document.getElementById('collmodal').style.display='none'" aria-label="Schließen">&times;</button>
    </header>
    <?php echo $piece->listCollections(); ?>
  </div>
</div>
<?php } ?>
    <?php
    deferPageModalHtml(ob_get_clean());
}
?>
<div class="w3-container w3-margin-bottom termin-page">
<div class="profile-shell termin-shell">
<?php if($isAdmin) { ?>
<form class="profile-form" action="composition.php<?php echo $isCreate ? '' : '?id='.(int)$piece->Index; ?>" method="POST">
  <input name="Index" type="hidden" value="<?php echo (int)$piece->Index; ?>">

  <header class="profile-hero">
    <div class="profile-hero-text">
      <p class="profile-kicker">Stück</p>
      <h2 class="profile-title"><?php echo htmlspecialchars($formTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
    </div>
    <div class="profile-hero-actions">
      <div class="profile-actions">
        <div class="profile-actions-primary">
          <input class="w3-btn profile-btn-primary <?php echo htmlspecialchars($btnSubmit, ENT_QUOTES, 'UTF-8'); ?> w3-border w3-mobile" type="submit" name="save" value="Speichern">
          <a class="w3-button w3-border <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" href="index.php">Zur Liste</a>
<?php if(!$isCreate) { ?>
          <button type="button" class="w3-button <?php echo htmlspecialchars($GLOBALS['optionsDB']['colorBtnDelete'], ENT_QUOTES, 'UTF-8'); ?>" onclick="document.getElementById('delmodal').style.display='block'">Löschen</button>
<?php } ?>
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
        <label class="profile-label" for="editWebsite">Produktseite</label>
        <input id="editWebsite" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" name="Website" type="url" value="<?php echo archivEscHtml($piece->Website); ?>" placeholder="https://…">
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
<?php if(!$isCreate) { ?>
    <section class="profile-col" aria-labelledby="edit-col-collections">
      <h3 id="edit-col-collections" class="profile-col-title">Sammlungen</h3>
      <?php
        $collSpec = $piece->getCollectionsChipSpec();
        $collIncludeIds = array();
        foreach($collSpec as $row) {
            $collIncludeIds[] = (int)$row['id'];
        }
        echo archivCollectionChipsEditorHtml(
            'piece-edit-coll',
            'mail-recipient-chip--collection',
            'collectionsSpec',
            archivCollectionsCatalog($collIncludeIds),
            $collSpec,
            'Sammlung…'
        );
      ?>
    </section>
<?php } ?>
  </div>
</form>
<?php } else { ?>
  <header class="profile-hero">
    <div class="profile-hero-thumb" aria-hidden="true"><?php echo $piece->coverHtml('archiv-thumb archiv-thumb--detail piece-cover piece-cover--detail'); ?></div>
    <div class="profile-hero-text">
      <p class="profile-kicker">Stück</p>
      <h2 class="profile-title"><?php echo archivEscHtml($pieceTitle !== '' ? $pieceTitle : '—'); ?></h2>
    </div>
    <div class="profile-hero-actions">
      <button type="button" class="w3-button <?php echo htmlspecialchars($btnEdit, ENT_QUOTES, 'UTF-8'); ?>" onclick="document.getElementById('collmodal').style.display='block'">Sammlungen</button>
      <a class="w3-button w3-border" href="index.php">Zur Liste</a>
    </div>
  </header>
  <div class="profile-grid profile-grid--2">
    <section class="profile-col">
      <h3 class="profile-col-title">Stammdaten</h3>
      <div class="profile-field"><span class="profile-label">Inventarnummer</span><div class="profile-value"><?php echo archivEscHtml($piece->RegistrationNumber); ?></div></div>
      <div class="profile-field"><span class="profile-label">Komponist</span><div class="profile-value"><?php echo archivEscHtml($pieceComposer); ?></div></div>
      <div class="profile-field"><span class="profile-label">Arrangeur</span><div class="profile-value"><?php echo archivEscHtml($piece->ArrangerName); ?></div></div>
    </section>
    <section class="profile-col">
      <h3 class="profile-col-title">Verlag &amp; Angaben</h3>
      <div class="profile-field"><span class="profile-label">Verlag</span><div class="profile-value"><?php echo $piece->publisherLinkHtml(); ?></div></div>
      <div class="profile-field"><span class="profile-label">Jahr</span><div class="profile-value"><?php echo archivEscHtml($piece->Year); ?></div></div>
      <div class="profile-field"><span class="profile-label">Schwierigkeit</span><div class="profile-value"><?php echo archivEscHtml($piece->Grade); ?></div></div>
      <div class="profile-field"><span class="profile-label">Spielzeit</span><div class="profile-value"><?php echo archivEscHtml($piece->PerformanceTime); ?></div></div>
    </section>
  </div>
<?php } ?>

<?php if(!$isCreate) { ?>
<?php if($isAdmin) { ?>
<div class="termin-grid w3-margin-top">
  <section class="profile-col" aria-labelledby="edit-col-cover">
    <h3 id="edit-col-cover" class="profile-col-title">Cover</h3>
    <div class="profile-field">
      <div class="profile-value"><?php echo $piece->coverHtml('archiv-thumb archiv-thumb--detail piece-cover piece-cover--detail'); ?></div>
    </div>
    <form action="composition.php?id=<?php echo (int)$piece->Index; ?>" method="POST" enctype="multipart/form-data">
      <input class="w3-input w3-border profile-control" type="file" accept=".png,.jpeg,.gif,.jpg" name="coverImage">
      <input type="hidden" name="Index" value="<?php echo (int)$piece->Index; ?>">
      <div class="profile-actions w3-margin-top">
        <input class="w3-button <?php echo htmlspecialchars($btnSubmit, ENT_QUOTES, 'UTF-8'); ?>" type="submit" value="upload" name="cover">
        <input class="w3-button <?php echo htmlspecialchars($btnSubmit, ENT_QUOTES, 'UTF-8'); ?>" type="submit" value="delete" name="delete">
      </div>
    </form>
  </section>
</div>
<?php } ?>
<div class="w3-container" style="padding-left:0;padding-right:0">
  <h3>Stimmverteilung</h3>
</div>
<?php if($isAdmin) { ?>
<div class="w3-row w3-margin">
  <form action="composition.php?id=<?php echo (int)$piece->Index; ?>" method="POST">
    <select name="instrument" class="w3-input w3-col l2">
      <?php echo instrumentsOption(); ?>
    </select>
    <input type="hidden" name="pieceID" value="<?php echo (int)$piece->Index; ?>">
    <input class="w3-input w3-col l1" type="number" name="part" value="1">
    <button class="w3-button w3-round w3-teal w3-col l1" type="submit"><i class="fas fa-plus"></i></button>
  </form>
</div>
<?php } ?>
<div>
  <?php echo $piece->listParts(); ?>
</div>
<?php } ?>
</div>
</div>
<?php
include 'common/footer.php';
?>
