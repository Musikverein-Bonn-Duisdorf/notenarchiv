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
    header('Location: composition.php?id='.(int)$piece->Index);
    exit;
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

$addBtn = '';
if(!empty($_SESSION['admin'])) {
    $addBtn = '<a class="w3-button '.htmlspecialchars($GLOBALS['optionsDB']['colorBtnSubmit'], ENT_QUOTES, 'UTF-8').'" href="anlegen.php" title="Anlegen"><i class="fas fa-plus"></i></a>';
}
adminListPageBegin('Archiv', 'Stückliste ('.$nPieces.')', array('actionsHtml' => $addBtn));
adminListSearchField('Titel, Komponist, Verlag, Nr.…', array('onkeyup' => 'filterPieces()'));
?>
<script src="<?php echo assetUrl('js/filterPieces.js'); ?>"></script>
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
