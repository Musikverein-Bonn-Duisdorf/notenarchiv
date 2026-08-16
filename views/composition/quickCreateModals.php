<?php
/**
 * ARCHIV-49: Quick-create modals for composition.php.
 * Structure matches #appConfirmModal (outer .w3-modal-content + inner .modal-shell).
 */
$inputBg = isset($GLOBALS['optionsDB']['colorInputBackground'])
    ? (string)$GLOBALS['optionsDB']['colorInputBackground']
    : '';
$btnSubmit = isset($GLOBALS['optionsDB']['colorBtnSubmit'])
    ? (string)$GLOBALS['optionsDB']['colorBtnSubmit']
    : '';
?>
<!-- archiv49-quick-create-modals -->
<div id="quickPersonModal" class="w3-modal" style="display:none;"
     onclick="if(event.target===this && !(window.ArchivQuickCreateIgnoreUntil && Date.now()<window.ArchivQuickCreateIgnoreUntil)){ this.style.display='none'; }">
  <div class="w3-modal-content">
    <div class="profile-shell modal-shell">
      <header class="profile-hero">
        <div class="profile-hero-text">
          <p class="profile-kicker">Anlegen</p>
          <h2 class="profile-title" id="quickPersonTitle">Komponist</h2>
        </div>
        <div class="profile-hero-actions">
          <button type="button" class="modal-close w3-button" onclick="document.getElementById('quickPersonModal').style.display='none'" aria-label="Schließen">&times;</button>
        </div>
      </header>
      <form id="quickPersonForm" class="profile-grid">
        <div class="profile-field">
          <label class="profile-label" for="quickPersonFirst">Vorname</label>
          <input id="quickPersonFirst" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" type="text" autocomplete="off">
        </div>
        <div class="profile-field">
          <label class="profile-label" for="quickPersonLast">Nachname</label>
          <input id="quickPersonLast" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" type="text" required autocomplete="off">
        </div>
        <p id="quickPersonError" class="profile-value quick-create-error" hidden></p>
        <div class="profile-field profile-actions">
          <button type="submit" class="w3-button <?php echo htmlspecialchars($btnSubmit, ENT_QUOTES, 'UTF-8'); ?>">Anlegen</button>
          <button type="button" class="w3-button w3-border" onclick="document.getElementById('quickPersonModal').style.display='none'">Abbrechen</button>
        </div>
      </form>
    </div>
  </div>
</div>
<div id="quickPublisherModal" class="w3-modal" style="display:none;"
     onclick="if(event.target===this && !(window.ArchivQuickCreateIgnoreUntil && Date.now()<window.ArchivQuickCreateIgnoreUntil)){ this.style.display='none'; }">
  <div class="w3-modal-content">
    <div class="profile-shell modal-shell">
      <header class="profile-hero">
        <div class="profile-hero-text">
          <p class="profile-kicker">Anlegen</p>
          <h2 class="profile-title">Verlag</h2>
        </div>
        <div class="profile-hero-actions">
          <button type="button" class="modal-close w3-button" onclick="document.getElementById('quickPublisherModal').style.display='none'" aria-label="Schließen">&times;</button>
        </div>
      </header>
      <form id="quickPublisherForm" class="profile-grid">
        <div class="profile-field">
          <label class="profile-label" for="quickPublisherName">Name</label>
          <input id="quickPublisherName" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" type="text" required autocomplete="off">
        </div>
        <p id="quickPublisherError" class="profile-value quick-create-error" hidden></p>
        <div class="profile-field profile-actions">
          <button type="submit" class="w3-button <?php echo htmlspecialchars($btnSubmit, ENT_QUOTES, 'UTF-8'); ?>">Anlegen</button>
          <button type="button" class="w3-button w3-border" onclick="document.getElementById('quickPublisherModal').style.display='none'">Abbrechen</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script src="<?php echo assetUrl('js/quickCreateEntity.js'); ?>"></script>
<script>
(function() {
  function lift(id) {
    var el = document.getElementById(id);
    if(el && el.parentNode !== document.body) {
      document.body.appendChild(el);
    }
    return el;
  }
  document.querySelectorAll('[data-quick-create="person"]').forEach(function(btn) {
    btn.addEventListener('click', function(ev) {
      ev.preventDefault();
      ev.stopPropagation();
      lift('quickPersonModal');
      if(window.ArchivQuickCreate && window.ArchivQuickCreate.openPerson) {
        window.ArchivQuickCreate.openPerson(btn);
        return;
      }
      window.ArchivQuickCreateIgnoreUntil = Date.now() + 500;
      var title = document.getElementById('quickPersonTitle');
      if(title) title.textContent = btn.getAttribute('data-kind') || 'Komponist';
      var m = lift('quickPersonModal');
      if(m) m.style.display = 'block';
    });
  });
  document.querySelectorAll('[data-quick-create="publisher"]').forEach(function(btn) {
    btn.addEventListener('click', function(ev) {
      ev.preventDefault();
      ev.stopPropagation();
      lift('quickPublisherModal');
      if(window.ArchivQuickCreate && window.ArchivQuickCreate.openPublisher) {
        window.ArchivQuickCreate.openPublisher();
        return;
      }
      window.ArchivQuickCreateIgnoreUntil = Date.now() + 500;
      var m = lift('quickPublisherModal');
      if(m) m.style.display = 'block';
    });
  });
})();
</script>
