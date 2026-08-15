<?php
/**
 * Collection detail modal (read-only). Expects: $collection, $showEditButton, $itemCount, $items
 * $items: list of array{number:string,title:string,coverHtml?:string}
 */
$name = archivPlainText($collection->Name);
$id = (int)$collection->Index;
$btnEdit = isset($GLOBALS['optionsDB']['colorBtnEdit'])
    ? (string)$GLOBALS['optionsDB']['colorBtnEdit']
    : '';
$itemCount = isset($itemCount) ? (int)$itemCount : 0;
$items = isset($items) && is_array($items) ? $items : array();
?>
<div class="profile-shell modal-shell collection-modal">
  <header class="profile-hero">
    <div class="profile-hero-text">
      <p class="profile-kicker">Sammlung</p>
      <h2 class="profile-title"><?php echo archivEscHtml($name !== '' ? $name : '—'); ?></h2>
    </div>
    <div class="profile-hero-actions">
<?php if(!empty($showEditButton)) { ?>
      <form class="profile-actions-primary" action="new-collection.php" method="POST">
        <button class="w3-btn profile-btn-primary <?php echo archivEscHtml($btnEdit); ?> w3-border w3-mobile" type="submit" name="id" value="<?php echo $id; ?>">Bearbeiten</button>
      </form>
<?php } ?>
      <button type="button" class="modal-close w3-button" onclick="closeModal()" aria-label="Schließen">&times;</button>
    </div>
  </header>

  <div class="profile-grid">
    <section class="profile-col" aria-labelledby="collection-modal-angaben">
      <h3 id="collection-modal-angaben" class="profile-col-title">Angaben</h3>
      <div class="profile-field">
        <span class="profile-label">Name</span>
        <div class="profile-value"><?php echo archivEscHtml($name !== '' ? $name : '—'); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Archiviert</span>
        <div class="profile-value"><?php echo (int)$collection->Archived ? 'ja' : 'nein'; ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Stücke</span>
        <div class="profile-value"><?php echo $itemCount; ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">ID</span>
        <div class="profile-value"><?php echo $id; ?></div>
      </div>
    </section>
<?php if(count($items)) { ?>
    <section class="profile-col" aria-labelledby="collection-modal-inhalt">
      <h3 id="collection-modal-inhalt" class="profile-col-title">Inhalt</h3>
<?php
    $shown = 0;
    foreach($items as $item) {
        if($shown >= 12) {
            echo '<div class="profile-field"><div class="profile-value">… und '.(int)($itemCount - $shown).' weitere</div></div>';
            break;
        }
        $num = isset($item['number']) ? (string)$item['number'] : '';
        $title = isset($item['title']) ? (string)$item['title'] : '';
        $cover = isset($item['coverHtml']) ? (string)$item['coverHtml'] : '';
        echo '<div class="profile-field collection-modal-item">';
        echo '<span class="profile-label">'.archivEscHtml($num !== '' ? $num : '—').'</span>';
        echo '<div class="profile-value collection-modal-piece">';
        if($cover !== '') {
            echo $cover;
        }
        echo '<span class="collection-modal-piece-title">'.archivEscHtml($title !== '' ? $title : '—').'</span>';
        echo '</div>';
        echo '</div>';
        $shown++;
    }
?>
    </section>
<?php } ?>
  </div>
</div>
