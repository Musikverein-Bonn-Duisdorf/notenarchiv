<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
$_SESSION['page']='collections';
$_SESSION['adminpage']=false;
include "common/header.php";

if(!empty($_SESSION['admin'])) {
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
    $addBtn = '<a class="w3-button '.htmlspecialchars($GLOBALS['optionsDB']['colorBtnSubmit'], ENT_QUOTES, 'UTF-8').'" href="new-collection.php" title="Anlegen"><i class="fas fa-plus"></i></a>';
}
adminListPageBegin('Archiv', 'Sammlungen', array('actionsHtml' => $addBtn));
adminListSearchField('Sammlung, Stück…', array('onkeyup' => 'filterPieces()'));
?>
<script src="<?php echo assetUrl('js/filterPieces.js'); ?>"></script>
<?php
$modals = '';
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
