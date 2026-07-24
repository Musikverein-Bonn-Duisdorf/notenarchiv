<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
$_SESSION['page']='publishers';
$_SESSION['adminpage']=false;
include "common/header.php";

if(isset($_POST['uploadAvatar'])) {
    $n = new Publisher;
    $n->load_by_id($_POST['Index']);
    if(!empty($_FILES['avatar'])) {
        $n->uploadAvatar($_FILES['avatar']);
    }
}
if(isset($_POST['deleteAvatar'])) {
    $n = new Publisher;
    $n->load_by_id($_POST['Index']);
    $n->deleteAvatar();
}
if(isset($_POST['update'])) {
    $n = new Publisher;
    $n->load_by_id($_POST['Index']);
    $n->fill_from_array($_POST);
    $n->save();
}
if(isset($_POST['delete'])) {
    $n = new Publisher;
    $n->load_by_id($_POST['Index']);
    $n->delete();
}
if($_SESSION['admin']) {
    $sql = sprintf('SELECT COUNT(`Index`) AS `Count` FROM `%sPublisher`;',
    $GLOBALS['dbprefix']
    );
    $dbr = mysqli_query($conn, $sql);
    sqlerror();
    $row = mysqli_fetch_array($dbr);
    $nPublishers = $row['Count'];

    $addBtn = '<a class="w3-button '.htmlspecialchars($GLOBALS['optionsDB']['colorBtnSubmit'], ENT_QUOTES, 'UTF-8').'" href="new-publisher.php" title="Anlegen"><i class="fas fa-plus"></i></a>';
    adminListPageBegin('Verlage', 'Verlage ('.$nPublishers.')', array('actionsHtml' => $addBtn));
    adminListSearchField('Verlag…', array('onkeyup' => 'filterPieces()'));
?>
<script src="<?php echo assetUrl('js/filterPieces.js'); ?>"></script>
<?php
    $modals = '';
    $listHtml = '';
    $sql = sprintf('SELECT `Index` FROM `%sPublisher` ORDER BY `Name`;',
    $GLOBALS['dbprefix']
    );
    $dbr = mysqli_query($conn, $sql);
    sqlerror();
    while($row = mysqli_fetch_array($dbr)) {
        $M = new Publisher;
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
