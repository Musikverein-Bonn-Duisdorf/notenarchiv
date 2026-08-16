<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
$_SESSION['page']='publishers';
$_SESSION['adminpage']=false;
include "common/header.php";
requirePermission('perm_read');

$sql = sprintf('SELECT COUNT(`Index`) AS `Count` FROM `%sPublisher`;',
$GLOBALS['dbprefix']
);
$dbr = mysqli_query($conn, $sql);
sqlerror();
$row = mysqli_fetch_array($dbr);
$nPublishers = $row['Count'];

$addBtn = '';
if(!empty($_SESSION['admin'])) {
    $addBtn = '<a class="w3-button '.htmlspecialchars($GLOBALS['optionsDB']['colorBtnSubmit'], ENT_QUOTES, 'UTF-8').'" href="new-publisher.php" title="Anlegen"><i class="fas fa-plus"></i></a>';
}
$chunk = listChunkPublishers(0, 50, 'name', 'asc');
adminListPageBegin('Verlage', 'Verlage ('.$nPublishers.')', array('actionsHtml' => $addBtn));
adminListSearchField('Verlag…', array('onkeyup' => 'filterPieces()'));
?>
<div id="listHeader" class="inv-sort-bar">
  <div class="inv-sort-bar-sorts" role="toolbar" aria-label="Sortierung">
    <button type="button" class="inv-sort-chip list-sort" data-sort="name" data-type="string">Name</button>
    <button type="button" class="inv-sort-chip list-sort" data-sort="index" data-type="number">ID</button>
  </div>
</div>
<div id="Liste">
<?php echo $chunk['html']; ?>
<?php echo listChunkRenderSentinel('publishers', $chunk['nextCursor'], $chunk['hasMore'], 'filterPieces', ' data-sort="name" data-dir="asc"'); ?>
</div>
<?php
adminListPageEnd();
?>
<script src="<?php echo assetUrl('js/filterPieces.js'); ?>"></script>
<script src="<?php echo assetUrl('js/sortList.js'); ?>"></script>
<script src="<?php echo assetUrl('js/infiniteScroll.js'); ?>"></script>
<script>
bindListSort({ headerId: 'listHeader', listId: 'Liste', mode: 'server', defaultKey: 'name', defaultDir: 'asc', defaultType: 'string' });
</script>
<?php
include "common/footer.php";
?>
