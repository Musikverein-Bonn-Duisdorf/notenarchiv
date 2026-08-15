<?php
/**
 * Composer detail modal. Expects: $composer, $showEditButton,
 * $composerPieceCount, $arrangerPieceCount, $composerPieces, $arrangerPieces
 */
$name = trim(archivPlainText($composer->FirstName).' '.archivPlainText($composer->LastName));
$btnEdit = isset($GLOBALS['optionsDB']['colorBtnEdit'])
    ? (string)$GLOBALS['optionsDB']['colorBtnEdit']
    : '';
$id = (int)$composer->Index;
$composerPieceCount = isset($composerPieceCount) ? (int)$composerPieceCount : 0;
$arrangerPieceCount = isset($arrangerPieceCount) ? (int)$arrangerPieceCount : 0;
$composerPieces = isset($composerPieces) && is_array($composerPieces) ? $composerPieces : array();
$arrangerPieces = isset($arrangerPieces) && is_array($arrangerPieces) ? $arrangerPieces : array();

$renderPieceList = function ($items, $total, $max = 20) {
    $shown = 0;
    foreach($items as $item) {
        if($shown >= $max) {
            echo '<div class="profile-field"><div class="profile-value">… und '.(int)($total - $shown).' weitere</div></div>';
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
};
?>
<div class="profile-shell modal-shell composer-modal">
  <header class="profile-hero">
    <div class="profile-hero-thumb" aria-hidden="true"><?php echo archivEntityAvatarHtml('Composers', $id, $composer->avatarInitials(), 'archiv-thumb archiv-thumb--detail entity-avatar entity-avatar--detail'); ?></div>
    <div class="profile-hero-text">
      <p class="profile-kicker">Komponist</p>
      <h2 class="profile-title"><?php echo archivEscHtml($name !== '' ? $name : '—'); ?></h2>
    </div>
    <div class="profile-hero-actions">
<?php if(!empty($showEditButton)) { ?>
      <form class="profile-actions-primary" action="new-composer.php" method="POST">
        <button class="w3-btn profile-btn-primary <?php echo archivEscHtml($btnEdit); ?> w3-border w3-mobile" type="submit" name="id" value="<?php echo $id; ?>">Bearbeiten</button>
      </form>
<?php } ?>
      <button type="button" class="modal-close w3-button" onclick="closeModal()" aria-label="Schließen">&times;</button>
    </div>
  </header>

  <div class="profile-grid profile-grid--2">
    <section class="profile-col" aria-labelledby="composer-modal-name">
      <h3 id="composer-modal-name" class="profile-col-title">Name</h3>
      <div class="profile-field">
        <span class="profile-label">Vorname</span>
        <div class="profile-value"><?php echo archivEscHtml(archivPlainText($composer->FirstName) !== '' ? archivPlainText($composer->FirstName) : '—'); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Nachname</span>
        <div class="profile-value"><?php echo archivEscHtml(archivPlainText($composer->LastName) !== '' ? archivPlainText($composer->LastName) : '—'); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Als Komponist</span>
        <div class="profile-value"><?php echo $composerPieceCount; ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Als Arrangeur</span>
        <div class="profile-value"><?php echo $arrangerPieceCount; ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">ID</span>
        <div class="profile-value"><?php echo $id; ?></div>
      </div>
    </section>
<?php if($composerPieceCount > 0) { ?>
    <section class="profile-col" aria-labelledby="composer-modal-as-composer">
      <h3 id="composer-modal-as-composer" class="profile-col-title">Stücke (Komponist)</h3>
      <?php $renderPieceList($composerPieces, $composerPieceCount); ?>
    </section>
<?php } ?>
<?php if($arrangerPieceCount > 0) { ?>
    <section class="profile-col" aria-labelledby="composer-modal-as-arranger">
      <h3 id="composer-modal-as-arranger" class="profile-col-title">Stücke (Arrangeur)</h3>
      <?php $renderPieceList($arrangerPieces, $arrangerPieceCount); ?>
    </section>
<?php } ?>
  </div>
</div>
