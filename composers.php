<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
$_SESSION['page']='composers';
$_SESSION['adminpage']=false;
include "common/header.php";

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

    $addBtn = '<a class="w3-button '.htmlspecialchars($GLOBALS['optionsDB']['colorBtnSubmit'], ENT_QUOTES, 'UTF-8').'" href="new-composer.php" title="Anlegen"><i class="fas fa-plus"></i></a>';
    adminListPageBegin('Komponisten', 'Komponisten und Arrangeure ('.$nComposers.')', array('actionsHtml' => $addBtn));
    adminListSearchField('Komponist…', array('onkeyup' => 'filterPieces()'));
?>
<script src="<?php echo assetUrl('js/filterPieces.js'); ?>"></script>
<?php
    $modals = '';
    $listHtml = '';
    $sql = sprintf('SELECT `Index` FROM `%sComposer` ORDER BY `LastName`, `FirstName`;',
    $GLOBALS['dbprefix']
    );
    $dbr = mysqli_query($conn, $sql);
    sqlerror();
    while($row = mysqli_fetch_array($dbr)) {
        $M = new Composer;
        $M->load_by_id($row['Index']);
        $listHtml .= $M->printLine();
        $modals .= $M->printEditModal();
    }
    deferPageModalHtml($modals);
?>
<div id="Liste">
<?php echo $listHtml; ?>
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
