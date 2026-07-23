<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
$_SESSION['page']='composers';
$_SESSION['adminpage']=false;
include "common/header.php";

if(isset($_POST['insert'])) {
    $n = new Composer;
    $n->fill_from_array($_POST);
    $n->save();
}
if(isset($_POST['update'])) {
    $n = new Composer;
    $n->load_by_id($_POST['Index']);
    $n->fill_from_array($_POST);
    $n->save();
}
if(isset($_POST['delete'])) {
    $n = new Composer;
    $n->load_by_id($_POST['Index']);
    $n->delete();
}
if($_SESSION['admin']) {
    $sql = sprintf('SELECT COUNT(`Index`) AS `Count` FROM `%sComposer`;',
    $GLOBALS['dbprefix']
    );
    $dbr = mysqli_query($conn, $sql);
    sqlerror();
    $row = mysqli_fetch_array($dbr);
    $nComposers = $row['Count'];

    $addBtn = '<button type="button" class="w3-button '.htmlspecialchars($GLOBALS['optionsDB']['colorBtnSubmit'], ENT_QUOTES, 'UTF-8').'" onclick="document.getElementById(\'inputModal\').style.display=\'block\'" title="Anlegen"><i class="fas fa-plus"></i></button>';
    adminListPageBegin('Komponisten', 'Komponisten und Arrangeure ('.$nComposers.')', array('actionsHtml' => $addBtn));
    adminListSearchField('Suchen…', array('onkeyup' => 'filterPieces()'));
?>
<script src="<?php echo assetUrl('js/filterPieces.js'); ?>"></script>
<?php
    ob_start();
?>
<div id="inputModal" class="w3-modal">
  <form class="w3-modal-content profile-shell modal-shell" action="" method="POST">
    <header class="profile-hero">
      <div class="profile-hero-text">
        <p class="profile-kicker">Komponisten</p>
        <h2 class="profile-title">Anlegen</h2>
      </div>
      <button type="button" class="modal-close w3-button" onclick="document.getElementById('inputModal').style.display='none'" aria-label="Schließen">&times;</button>
    </header>
    <div class="profile-grid">
      <div class="profile-field">
        <label class="profile-label">Vorname</label>
        <input name="FirstName" type="text" class="w3-input w3-border profile-control <?php echo $GLOBALS['optionsDB']['colorInputBackground']; ?>"/>
      </div>
      <div class="profile-field">
        <label class="profile-label">Nachname</label>
        <input name="LastName" type="text" class="w3-input w3-border profile-control <?php echo $GLOBALS['optionsDB']['colorInputBackground']; ?>"/>
      </div>
      <div class="profile-field">
        <button type="submit" name="insert" value="1" class="w3-button <?php echo $GLOBALS['optionsDB']['colorBtnSubmit']; ?>">Speichern</button>
      </div>
    </div>
  </form>
</div>
<?php
    deferPageModalHtml(ob_get_clean());
?>
<div id="Liste">
<?php
    $sql = sprintf('SELECT `Index` FROM `%sComposer` ORDER BY `LastName`, `FirstName`;',
    $GLOBALS['dbprefix']
    );
    $dbr = mysqli_query($conn, $sql);
    sqlerror();
    while($row = mysqli_fetch_array($dbr)) {
        $M = new Composer;
        $M->load_by_id($row['Index']);
        echo $M->printLine();
    }
?>
</div>
<?php
    adminListPageEnd();
}
else {
 ?>
<meta http-equiv="refresh" content="0; URL=index.php" />
<?php
}

 include "common/footer.php";
?>
