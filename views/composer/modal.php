<?php
/**
 * Composer detail modal (read-only). Expects: $composer, $showEditButton
 */
$name = trim(archivPlainText($composer->FirstName).' '.archivPlainText($composer->LastName));
$btnEdit = isset($GLOBALS['optionsDB']['colorBtnEdit'])
    ? (string)$GLOBALS['optionsDB']['colorBtnEdit']
    : '';
$id = (int)$composer->Index;
?>
<div class="profile-shell modal-shell composer-modal">
  <header class="profile-hero">
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

  <div class="profile-grid">
    <section class="profile-col" aria-labelledby="composer-modal-name">
      <h3 id="composer-modal-name" class="profile-col-title">Name</h3>
      <div class="profile-field">
        <span class="profile-label">Foto</span>
        <div class="profile-value"><?php echo archivEntityAvatarHtml('Composers', $id, $composer->avatarInitials(), 'entity-avatar entity-avatar--lg'); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Vorname</span>
        <div class="profile-value"><?php echo archivEscHtml(archivPlainText($composer->FirstName) !== '' ? archivPlainText($composer->FirstName) : '—'); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Nachname</span>
        <div class="profile-value"><?php echo archivEscHtml(archivPlainText($composer->LastName) !== '' ? archivPlainText($composer->LastName) : '—'); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">ID</span>
        <div class="profile-value"><?php echo $id; ?></div>
      </div>
    </section>
  </div>
</div>
