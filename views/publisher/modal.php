<?php
/**
 * Publisher detail modal (read-only). Expects: $publisher, $showEditButton, $pieceCount, $pieces
 * $pieces: list of array{id:int,registrationNumber:?int,title:string}
 */
$name = archivPlainText($publisher->Name);
$address = archivPlainText($publisher->Address);
$website = trim(archivPlainText($publisher->Website));
$btnEdit = isset($GLOBALS['optionsDB']['colorBtnEdit'])
    ? (string)$GLOBALS['optionsDB']['colorBtnEdit']
    : '';
$id = (int)$publisher->Index;
$pieceCount = isset($pieceCount) ? (int)$pieceCount : 0;
$pieces = isset($pieces) && is_array($pieces) ? $pieces : array();
?>
<div class="profile-shell modal-shell publisher-modal">
  <header class="profile-hero">
    <div class="profile-hero-thumb" aria-hidden="true"><?php echo archivEntityAvatarHtml('Publishers', $id, $publisher->avatarInitials(), 'archiv-thumb archiv-thumb--detail entity-avatar entity-avatar--detail'); ?></div>
    <div class="profile-hero-text">
      <p class="profile-kicker">Verlag</p>
      <h2 class="profile-title"><?php echo archivEscHtml($name !== '' ? $name : '—'); ?></h2>
    </div>
    <div class="profile-hero-actions">
<?php if(!empty($showEditButton)) { ?>
      <form class="profile-actions-primary" action="new-publisher.php" method="POST">
        <button class="w3-btn profile-btn-primary <?php echo archivEscHtml($btnEdit); ?> w3-border w3-mobile" type="submit" name="id" value="<?php echo $id; ?>">Bearbeiten</button>
      </form>
<?php } ?>
      <button type="button" class="modal-close w3-button" onclick="closeModal()" aria-label="Schließen">&times;</button>
    </div>
  </header>

  <div class="profile-grid profile-grid--2">
    <section class="profile-col" aria-labelledby="publisher-modal-angaben">
      <h3 id="publisher-modal-angaben" class="profile-col-title">Angaben</h3>
      <div class="profile-field">
        <span class="profile-label">Name</span>
        <div class="profile-value"><?php echo archivEscHtml($name !== '' ? $name : '—'); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Website</span>
        <div class="profile-value"><?php
          if($website !== '') {
              $href = $website;
              if(!preg_match('#^https?://#i', $href)) {
                  $href = 'https://'.$href;
              }
              echo '<a href="'.archivEscHtml($href).'" target="_blank" rel="noopener noreferrer">'.archivEscHtml($website).'</a>';
          } else {
              echo '—';
          }
        ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Adresse</span>
        <div class="profile-value"><?php echo $address !== '' ? nl2br(archivEscHtml($address), false) : '—'; ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Stücke</span>
        <div class="profile-value"><?php echo $pieceCount; ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">ID</span>
        <div class="profile-value"><?php echo $id; ?></div>
      </div>
    </section>
<?php if($pieceCount > 0) { ?>
    <section class="profile-col" aria-labelledby="publisher-modal-stuecke">
      <h3 id="publisher-modal-stuecke" class="profile-col-title">Stücke</h3>
<?php
    $shown = 0;
    foreach($pieces as $item) {
        if($shown >= 20) {
            echo '<div class="profile-field"><div class="profile-value">… und '.(int)($pieceCount - $shown).' weitere</div></div>';
            break;
        }
        $compId = isset($item['id']) ? (int)$item['id'] : 0;
        $reg = isset($item['registrationNumber']) && $item['registrationNumber'] !== null
            ? (string)$item['registrationNumber']
            : '';
        $title = isset($item['title']) ? (string)$item['title'] : '';
        echo '<div class="profile-field">';
        echo '<span class="profile-label">'.archivEscHtml($reg !== '' ? $reg : '—').'</span>';
        if($compId > 0) {
            echo '<div class="profile-value"><span class="entity-open" data-entity-type="composition" data-entity-id="'.$compId.'" role="button" tabindex="0">'.archivEscHtml($title !== '' ? $title : '—').'</span></div>';
        } else {
            echo '<div class="profile-value">'.archivEscHtml($title !== '' ? $title : '—').'</div>';
        }
        echo '</div>';
        $shown++;
    }
?>
    </section>
<?php } ?>
  </div>
</div>
