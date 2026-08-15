<?php
/**
 * Identity user detail modal (read-only, for log entity chips).
 * Expects: $user (User), $showAdminBadge (bool)
 */
$name = trim((string)$user->getName());
$id = (int)$user->Index;
$login = trim((string)$user->login);
$email = trim((string)$user->Email);
$isAdmin = !empty($user->Admin);
?>
<div class="profile-shell modal-shell user-modal">
  <header class="profile-hero">
    <div class="profile-hero-text">
      <p class="profile-kicker">Benutzer</p>
      <h2 class="profile-title"><?php echo archivEscHtml($name !== '' ? $name : ('User #'.$id)); ?></h2>
    </div>
    <div class="profile-hero-actions">
      <button type="button" class="modal-close w3-button" onclick="closeModal()" aria-label="Schließen">&times;</button>
    </div>
  </header>

  <div class="profile-grid">
    <section class="profile-col" aria-labelledby="user-modal-stamm">
      <h3 id="user-modal-stamm" class="profile-col-title">Angaben</h3>
      <div class="profile-field">
        <span class="profile-label">ID</span>
        <div class="profile-value"><?php echo $id; ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Login</span>
        <div class="profile-value"><?php echo archivEscHtml($login !== '' ? $login : '—'); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">E-Mail</span>
        <div class="profile-value"><?php echo archivEscHtml($email !== '' ? $email : '—'); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Admin</span>
        <div class="profile-value"><?php echo !empty($showAdminBadge) && $isAdmin ? 'ja' : ($isAdmin ? 'ja' : 'nein'); ?></div>
      </div>
    </section>
  </div>
</div>
