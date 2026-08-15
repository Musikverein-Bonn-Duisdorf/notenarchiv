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
    if((int)$piece->Index > 0 && isset($_POST['collectionsSpec'])) {
        archivSyncCollectionItemsForComposition(
            (int)$piece->Index,
            archivParseCollectionChipSpec($_POST['collectionsSpec'])
        );
    }
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
    $addBtn = '<a class="w3-button '.htmlspecialchars($GLOBALS['optionsDB']['colorBtnSubmit'], ENT_QUOTES, 'UTF-8').'" href="new-composition.php" title="Anlegen"><i class="fas fa-plus"></i></a>';
}
$chunk = listChunkCompositions(0, 50, 'nr', 'desc');
adminListPageBegin('Archiv', 'Stückliste ('.$nPieces.')', array('actionsHtml' => $addBtn));
adminListSearchField('Titel, Komponist, Verlag, Nr.…', array('onkeyup' => 'filterPieces()'));
?>
<div id="listHeader" class="inv-sort-bar">
  <div class="inv-sort-bar-sorts" role="toolbar" aria-label="Sortierung">
    <button type="button" class="inv-sort-chip list-sort" data-sort="nr" data-type="number">Nr.</button>
    <button type="button" class="inv-sort-chip list-sort" data-sort="title" data-type="string">Titel</button>
    <button type="button" class="inv-sort-chip list-sort" data-sort="composer" data-type="string">Komponist</button>
    <button type="button" class="inv-sort-chip list-sort" data-sort="publisher" data-type="string">Verlag</button>
    <button type="button" class="inv-sort-chip list-sort" data-sort="year" data-type="number">Jahr</button>
    <button type="button" class="inv-sort-chip list-sort" data-sort="grade" data-type="string">Stufe</button>
  </div>
</div>
<div id="Liste">
<?php echo $chunk['html']; ?>
<?php echo listChunkRenderSentinel('compositions', $chunk['nextCursor'], $chunk['hasMore'], 'filterPieces', ' data-sort="nr" data-dir="desc"'); ?>
</div>
<?php
adminListPageEnd();
?>
<script src="<?php echo assetUrl('js/filterPieces.js'); ?>"></script>
<script src="<?php echo assetUrl('js/sortList.js'); ?>"></script>
<script src="<?php echo assetUrl('js/infiniteScroll.js'); ?>"></script>
<script>
bindListSort({ headerId: 'listHeader', listId: 'Liste', mode: 'server', defaultKey: 'nr', defaultDir: 'desc', defaultType: 'number' });
</script>
<?php
include "common/footer.php";
?>
