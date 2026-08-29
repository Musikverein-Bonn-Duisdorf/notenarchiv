<?php
/**
 * Composition detail modal (read-only). Expects: $piece, $showEditButton
 */
$title = archivPlainText($piece->Title);
$composer = archivPlainText($piece->ComposerName);
$arranger = archivPlainText($piece->ArrangerName);
$publisher = archivPlainText($piece->PublisherName);
$year = $piece->Year !== null && $piece->Year !== '' ? (string)$piece->Year : '';
$grade = $piece->Grade !== null && $piece->Grade !== '' ? (string)$piece->Grade : '';
$reg = $piece->RegistrationNumber !== null && $piece->RegistrationNumber !== ''
    ? (string)$piece->RegistrationNumber
    : '';
$time = archivPlainText($piece->PerformanceTime);
$id = (int)$piece->Index;
$btnEdit = isset($GLOBALS['optionsDB']['colorBtnEdit'])
    ? (string)$GLOBALS['optionsDB']['colorBtnEdit']
    : '';
$btnSubmit = isset($GLOBALS['optionsDB']['colorBtnSubmit'])
    ? (string)$GLOBALS['optionsDB']['colorBtnSubmit']
    : '';
$dash = function ($v) {
    $v = trim((string)$v);
    return $v !== '' ? archivEscHtml($v) : '—';
};
?>
<div class="profile-shell modal-shell composition-modal">
  <header class="profile-hero">
    <div class="profile-hero-thumb"><?php echo $piece->coverHtml('archiv-thumb archiv-thumb--detail piece-cover piece-cover--detail'); ?></div>
    <div class="profile-hero-text">
      <p class="profile-kicker">Stück</p>
      <h2 class="profile-title"><?php echo archivEscHtml($title !== '' ? $title : '—'); ?></h2>
    </div>
    <div class="profile-hero-actions">
      <?php echo $piece->recordingCellHtml(); ?>
      <div class="profile-actions">
        <div class="profile-actions-primary">
          <a class="w3-btn profile-btn-primary <?php echo archivEscHtml(!empty($showEditButton) ? $btnEdit : $btnSubmit); ?> w3-border w3-mobile" href="composition.php?id=<?php echo $id; ?>"><?php echo !empty($showEditButton) ? 'Bearbeiten' : 'Öffnen'; ?></a>
        </div>
      </div>
      <button type="button" class="modal-close w3-button" onclick="closeModal()" aria-label="Schließen">&times;</button>
    </div>
  </header>

  <div class="profile-grid profile-grid--2">
    <section class="profile-col" aria-labelledby="piece-modal-stamm">
      <h3 id="piece-modal-stamm" class="profile-col-title">Stammdaten</h3>
      <div class="profile-field">
        <span class="profile-label">Inventarnummer</span>
        <div class="profile-value"><?php echo $dash($reg); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Titel</span>
        <div class="profile-value"><?php echo $dash($title); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Komponist</span>
        <div class="profile-value"><?php echo $dash($composer); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Arrangeur</span>
        <div class="profile-value"><?php echo $dash($arranger); ?></div>
      </div>
    </section>
    <section class="profile-col" aria-labelledby="piece-modal-meta">
      <h3 id="piece-modal-meta" class="profile-col-title">Verlag &amp; Angaben</h3>
      <div class="profile-field">
        <span class="profile-label">Verlag</span>
        <div class="profile-value"><?php echo $piece->publisherLinkHtml(); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Jahr</span>
        <div class="profile-value"><?php echo $dash($year); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Schwierigkeit</span>
        <div class="profile-value"><?php echo $dash($grade); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Spielzeit</span>
        <div class="profile-value"><?php echo $dash($time); ?></div>
      </div>
    </section>
  </div>
</div>
