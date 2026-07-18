<?php
session_start();
$_SESSION['page'] = 'print-stimmsatz';
$_SESSION['adminpage'] = true;
include "common/header.php";
requireAdmin();

$phaseId = isset($_REQUEST['phase']) ? (int)$_REQUEST['phase'] : 0;
$instrumentId = isset($_REQUEST['instrument']) ? (int)$_REQUEST['instrument'] : 0;
$voiceLabel = isset($_REQUEST['voice']) ? trim((string)$_REQUEST['voice']) : '';
$tryUserVoice = ($phaseId > 0 && $instrumentId === 0 && $voiceLabel === '' && isset($_SESSION['userid']));

$phases = RehearsalPhase::listAll(false);
$pieces = array();
$printNote = '';
$userVoiceHint = '';
if($phaseId && $instrumentId && $voiceLabel !== '') {
    $stimmsatz = new Stimmsatz($phaseId, $instrumentId, $voiceLabel);
    $pieces = $stimmsatz->resolvePieces();
    $printNote = $stimmsatz->getPrintModeNote();
}
elseif($tryUserVoice && Stimmsatz::userVoiceTableExists()) {
    $stimmsatz = new Stimmsatz($phaseId, 0, '');
    $resolved = $stimmsatz->resolvePiecesWithUserVoice((int)$_SESSION['userid']);
    if($resolved) {
        $pieces = $resolved['pieces'];
        $instrumentId = $resolved['instrumentId'];
        $voiceLabel = $resolved['voiceLabel'];
        $printNote = (new Stimmsatz($phaseId, $instrumentId, $voiceLabel))->getPrintModeNote();
        $userVoiceHint = $resolved['usedFallback']
            ? 'Fallback-Stimme aus Meldeliste UserVoice verwendet.'
            : 'Primär-Stimme aus Meldeliste UserVoice verwendet.';
    }
}
if($voiceLabel === '' && $instrumentId === 0 && isset($_SESSION['userid']) && Stimmsatz::userVoiceTableExists()) {
    $candidates = Stimmsatz::userVoiceCandidates((int)$_SESSION['userid']);
    if(!empty($candidates)) {
        $instrumentId = (int)$candidates[0]['instrument'];
        $voiceLabel = (string)$candidates[0]['voice'];
    }
}
if($voiceLabel === '') {
    $voiceLabel = '1';
}
?>
<div class="w3-container <?php echo $GLOBALS['optionsDB']['colorTitleBar']; ?>">
  <h2>Stimmsatz drucken</h2>
</div>
<div class="w3-container w3-padding">
  <form method="get" class="w3-row-padding">
    <div class="w3-col m4 s12">
      <label><b>Probenphase</b></label>
      <select class="w3-input w3-border" name="phase">
        <option value="0">— wählen —</option>
        <?php foreach($phases as $phase) { ?>
        <option value="<?php echo (int)$phase->Index; ?>"<?php if($phaseId === (int)$phase->Index) echo ' selected'; ?>>
          <?php echo htmlspecialchars($phase->Name); ?>
        </option>
        <?php } ?>
      </select>
    </div>
    <div class="w3-col m4 s12">
      <label><b>Instrument</b></label>
      <select class="w3-input w3-border" name="instrument">
        <option value="0">— wählen —</option>
        <?php echo instrumentsOptionNull($instrumentId); ?>
      </select>
    </div>
    <div class="w3-col m2 s12">
      <label><b>Stimme</b></label>
      <input class="w3-input w3-border" type="text" name="voice" value="<?php echo htmlspecialchars($voiceLabel); ?>" placeholder="z.B. 1">
    </div>
    <div class="w3-col m2 s12 w3-padding-large">
      <button class="w3-button <?php echo $GLOBALS['optionsDB']['colorBtnSubmit']; ?>" type="submit">Anzeigen</button>
    </div>
  </form>

  <?php if($userVoiceHint) { ?>
  <div class="w3-panel w3-pale-blue w3-margin-top">
    <p><?php echo htmlspecialchars($userVoiceHint); ?></p>
  </div>
  <?php } ?>

  <?php if($printNote) { ?>
  <div class="w3-panel w3-pale-yellow w3-margin-top">
    <p><?php echo htmlspecialchars($printNote); ?></p>
  </div>
  <?php } ?>

  <?php if($phaseId && empty($pieces)) { ?>
  <div class="w3-panel w3-pale-red">Keine Stimmen für diese Auswahl gefunden.</div>
  <?php } ?>

  <?php foreach($pieces as $entry) {
      $comp = $entry['composition'];
      $files = $entry['scoreFiles'];
  ?>
  <div class="w3-card w3-white w3-margin-top w3-padding">
    <h4><?php echo htmlspecialchars($comp->Title); ?> (Nr. <?php echo (int)$comp->RegistrationNumber; ?>)</h4>
    <ul class="w3-ul">
      <?php foreach($files as $sf) { ?>
      <li>
        <?php echo htmlspecialchars($sf->InstrumentName.' — Stimme '.$sf->VoiceLabel); ?>
        <?php if($sf->FilePath) { ?>
          — <a href="<?php echo htmlspecialchars($comp->FilePath.$sf->FilePath); ?>" target="_blank">PDF</a>
        <?php } elseif($sf->NextcloudPath) { ?>
          — Nextcloud: <?php echo htmlspecialchars($sf->NextcloudPath); ?>
        <?php } else { ?>
          — <span class="w3-text-red">keine Datei</span>
        <?php } ?>
      </li>
      <?php } ?>
    </ul>
    <p><em>Drucken: PDF einseitig ausgeben, Stimmen sortiert heften.</em></p>
  </div>
  <?php } ?>
</div>
<?php include "common/footer.php"; ?>
