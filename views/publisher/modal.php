<?php
/**
 * Publisher detail modal (read-only). Expects: $publisher, $showEditButton
 */
$name = archivPlainText($publisher->Name);
$address = archivPlainText($publisher->Address);
$btnEdit = isset($GLOBALS['optionsDB']['colorBtnEdit'])
    ? (string)$GLOBALS['optionsDB']['colorBtnEdit']
    : '';
$id = (int)$publisher->Index;
?>
<div class="profile-shell modal-shell publisher-modal">
  <header class="profile-hero">
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

  <div class="profile-grid">
    <section class="profile-col" aria-labelledby="publisher-modal-angaben">
      <h3 id="publisher-modal-angaben" class="profile-col-title">Angaben</h3>
      <div class="profile-field">
        <span class="profile-label">Foto</span>
        <div class="profile-value"><?php echo archivEntityAvatarHtml('Publishers', $id, $publisher->avatarInitials(), 'entity-avatar entity-avatar--lg'); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Name</span>
        <div class="profile-value"><?php echo archivEscHtml($name !== '' ? $name : '—'); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Adresse</span>
        <div class="profile-value"><?php echo $address !== '' ? nl2br(archivEscHtml($address), false) : '—'; ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">ID</span>
        <div class="profile-value"><?php echo $id; ?></div>
      </div>
    </section>
  </div>
</div>
