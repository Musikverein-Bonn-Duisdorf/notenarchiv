<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
$_SESSION['page']='collections';
$_SESSION['adminpage']=false;
include "common/header.php";

if(!empty($_SESSION['admin'])) {
    if(isset($_POST['insert'])) {
        $n = new Collections;
        $n->fill_from_array($_POST);
        $n->save();
    }
    if(isset($_POST['update'])) {
        $n = new Collections;
        $n->load_by_id($_POST['Index']);
        $n->fill_from_array($_POST);
        $n->save();
    }
    if(isset($_POST['delete'])) {
        $n = new Collections;
        $n->load_by_id($_POST['Index']);
        $n->delete();
    }
}

$addBtn = '';
if(!empty($_SESSION['admin'])) {
    $addBtn = '<button type="button" class="w3-button '.htmlspecialchars($GLOBALS['optionsDB']['colorBtnSubmit'], ENT_QUOTES, 'UTF-8').'" onclick="document.getElementById(\'inputModal\').style.display=\'block\'" title="Anlegen"><i class="fas fa-plus"></i></button>';
}
adminListPageBegin('Archiv', 'Sammlungen', array('actionsHtml' => $addBtn));
adminListSearchField('Sammlung, Stück…', array('onkeyup' => 'filterPieces()'));
?>
<script src="<?php echo assetUrl('js/filterPieces.js'); ?>"></script>
<?php
$modals = '';
if(!empty($_SESSION['admin'])) {
    ob_start();
?>
<div id="inputModal" class="w3-modal">
  <form class="w3-modal-content profile-shell modal-shell" action="" method="POST">
    <header class="profile-hero">
      <div class="profile-hero-text">
        <p class="profile-kicker">Sammlungen</p>
        <h2 class="profile-title">Anlegen</h2>
      </div>
      <button type="button" class="modal-close w3-button" onclick="document.getElementById('inputModal').style.display='none'" aria-label="Schließen">&times;</button>
    </header>
    <div class="profile-grid">
      <div class="profile-field">
        <label class="profile-label">Name</label>
        <input name="Name" type="text" class="w3-input w3-border profile-control <?php echo $GLOBALS['optionsDB']['colorInputBackground']; ?>" required/>
      </div>
      <div class="profile-field">
        <button type="submit" name="insert" value="1" class="w3-button <?php echo $GLOBALS['optionsDB']['colorBtnSubmit']; ?>">Speichern</button>
      </div>
    </div>
  </form>
</div>
<?php
    $modals = ob_get_clean();
}
$listHtml = '';
$sql = sprintf('SELECT `Index` FROM `%sCollection` ORDER BY `Name`;',
$GLOBALS['dbprefix']
);
$dbr = mysqli_query($conn, $sql);
sqlerror();
while($row = mysqli_fetch_array($dbr)) {
    $M = new Collections;
    $M->load_by_id($row['Index']);
    $listHtml .= $M->printContent();
    if(!empty($_SESSION['admin'])) {
        $modals .= $M->printEditModal();
    }
}
if($modals !== '') {
    deferPageModalHtml($modals);
}
?>
<div id="Liste">
<?php echo $listHtml; ?>
</div>
<?php
adminListPageEnd();
include "common/footer.php";
?>
