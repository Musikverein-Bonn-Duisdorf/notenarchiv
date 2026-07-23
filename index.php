<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
$_SESSION['page']='home';
$_SESSION['adminpage']=false;
include "common/header.php";

if(isset($_POST['save'])) {
    $piece = new Composition;
    if($_POST['Index'] > 0) {
        $piece->load_by_id($_POST['Index']);
    }
    $piece->fill_from_array($_POST);
    $piece->save();
    $_POST['pieceID']=$piece->Index;
}

if(isset($_POST['Delete'])) {
    $piece = new Composition;
    if($_POST['Delete'] > 0) {
        $piece->load_by_id($_POST['Delete']);
        $piece->delete();
    }
}

$sql = sprintf('SELECT COUNT(`Index`) AS `Count` FROM `%sComposition`;',
$GLOBALS['dbprefix']
);
$dbr = mysqli_query($conn, $sql);
sqlerror();
$row = mysqli_fetch_array($dbr);
$nPieces = $row['Count'];

adminListPageBegin('Archiv', 'Stückliste ('.$nPieces.')');
adminListSearchField('Suche…', array('onkeyup' => 'filterPieces()'));
?>
<script src="<?php echo assetUrl('js/filterPieces.js'); ?>"></script>
<div class="w3-row w3-padding w3-border-bottom w3-border-black">
  <div class="w3-col l1 w3-center"><b>Archivnummer</b></div>
  <div class="w3-col l3 w3-center"><b>Titel</b></div>
  <div class="w3-col l2 w3-center"><b>Komponist</b></div>
  <div class="w3-col l2 w3-center"><b>Arrangeur</b></div>
  <div class="w3-col l2 w3-center"><b>Verlag</b></div>
  <div class="w3-col l1 w3-center"><b>Jahr</b></div>
  <div class="w3-col l1 w3-center"><b>Schwierigkeit</b></div>
</div>
<div id="Liste">
<?php
$sql = sprintf('SELECT `Index` FROM `%sComposition` ORDER BY `RegistrationNumber` DESC, `Title` ASC;',
$GLOBALS['dbprefix']
);
$dbr = mysqli_query($conn, $sql);
sqlerror();
while($row = mysqli_fetch_array($dbr)) {
    $M = new Composition;
    $M->load_by_id($row['Index']);
    echo $M->printLine();
}
?>
</div>
<?php
adminListPageEnd();
include "common/footer.php";
?>
