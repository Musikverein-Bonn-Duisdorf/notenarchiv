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
$showArchived = isset($_GET['archived']) && (string)$_GET['archived'] === '1';
$chunk = listChunkCollections(0, 50, 'index', 'desc', $showArchived);
$extra = $showArchived ? ' data-extra="archived=1"' : '';
adminListPageBegin('Archiv', 'Sammlungen', array('actionsHtml' => $addBtn));
adminListSearchField('Sammlung, Stück…', array('onkeyup' => 'filterPieces()'));
?>
<div id="listHeader" class="inv-sort-bar">
  <div class="inv-sort-bar-filters">
    <button type="button" id="filterArchived" class="inv-sort-chip inv-filter-chip<?php echo $showArchived ? ' is-active' : ''; ?>" aria-pressed="<?php echo $showArchived ? 'true' : 'false'; ?>">Archivierte</button>
  </div>
  <div class="inv-sort-bar-sorts" role="toolbar" aria-label="Sortierung">
    <button type="button" class="inv-sort-chip list-sort" data-sort="index" data-type="number">ID</button>
    <button type="button" class="inv-sort-chip list-sort" data-sort="name" data-type="string">Name</button>
  </div>
</div>
<div id="Liste">
<?php echo $chunk['html']; ?>
<?php echo listChunkRenderSentinel('collections', $chunk['nextCursor'], $chunk['hasMore'], 'filterPieces', ' data-sort="index" data-dir="desc"'.$extra); ?>
</div>
<?php
adminListPageEnd();
?>
<script src="<?php echo assetUrl('js/filterPieces.js'); ?>"></script>
<script src="<?php echo assetUrl('js/sortList.js'); ?>"></script>
<script src="<?php echo assetUrl('js/infiniteScroll.js'); ?>"></script>
<script>
bindListSort({ headerId: 'listHeader', listId: 'Liste', mode: 'server', defaultKey: 'index', defaultDir: 'desc', defaultType: 'number' });
(function () {
  var chip = document.getElementById('filterArchived');
  if (!chip) return;
  function syncExtra() {
    var sentinel = document.getElementById('listSentinel');
    if (!sentinel) return;
    if (chip.classList.contains('is-active')) {
      sentinel.setAttribute('data-extra', 'archived=1');
    } else {
      sentinel.removeAttribute('data-extra');
    }
  }
  chip.addEventListener('click', function (e) {
    e.preventDefault();
    e.stopPropagation();
    var on = !chip.classList.contains('is-active');
    chip.classList.toggle('is-active', on);
    chip.setAttribute('aria-pressed', on ? 'true' : 'false');
    syncExtra();
    var url = new URL(window.location.href);
    if (on) {
      url.searchParams.set('archived', '1');
    } else {
      url.searchParams.delete('archived');
    }
    window.history.replaceState({}, '', url.pathname + url.search);
    var sentinel = document.getElementById('listSentinel');
    var sort = sentinel ? (sentinel.getAttribute('data-sort') || 'index') : 'index';
    var dir = sentinel ? (sentinel.getAttribute('data-dir') || 'desc') : 'desc';
    if (typeof listInfiniteReload === 'function') {
      listInfiniteReload(sort, dir);
    }
  });
})();
</script>
<?php
include "common/footer.php";
?>
