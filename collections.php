<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
$_SESSION['page']='collections';
$_SESSION['adminpage']=false;
include "common/header.php";

$addBtn = '';
if(!empty($_SESSION['admin'])) {
    $addBtn = '<a class="w3-button '.htmlspecialchars($GLOBALS['optionsDB']['colorBtnSubmit'], ENT_QUOTES, 'UTF-8').'" href="new-collection.php" title="Anlegen"><i class="fas fa-plus"></i></a>';
}
$chunk = listChunkCollections(0, 50, 'name', 'asc');
adminListPageBegin('Archiv', 'Sammlungen', array('actionsHtml' => $addBtn));
adminListSearchField('Sammlung, Stück…', array('onkeyup' => 'filterPieces()'));
?>
<div id="listHeader" class="inv-sort-bar">
  <div class="inv-sort-bar-sorts" role="toolbar" aria-label="Sortierung">
    <button type="button" class="inv-sort-chip list-sort" data-sort="name" data-type="string">Name</button>
    <button type="button" class="inv-sort-chip list-sort" data-sort="index" data-type="number">ID</button>
  </div>
</div>
<div id="Liste">
<?php echo $chunk['html']; ?>
<?php echo listChunkRenderSentinel('collections', $chunk['nextCursor'], $chunk['hasMore'], 'filterPieces', ' data-sort="name" data-dir="asc"'); ?>
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
