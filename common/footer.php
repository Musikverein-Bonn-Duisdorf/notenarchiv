<?php
if(!empty($GLOBALS['mlHeaderRendered'])) {
?>
</div><!-- .app-main -->
</div><!-- .app-shell -->
<?php
}
/* Close any dangling MotD/rawtext context BEFORE hosts/modals (ARCHIV-49). */
if(function_exists('archivFooterParseReset')) {
    archivFooterParseReset();
}
/* Stable hosts first — must survive MotD HTML breakage. */
if(!empty($GLOBALS['mlHeaderRendered'])) {
?>
<div id="ajaxModalHost" class="w3-modal" onclick="if(event.target===this)closeModal();">
  <div id="ajaxModalContent" class="w3-modal-content"></div>
</div>
<div id="appConfirmModal" class="w3-modal" role="dialog" aria-modal="true" aria-labelledby="appConfirmTitle" style="display:none;">
  <div class="w3-modal-content">
    <div class="profile-shell modal-shell confirm-delete-modal">
      <header class="profile-hero">
        <div class="profile-hero-text">
          <p class="profile-kicker" id="appConfirmKicker" style="display:none;"></p>
          <h2 class="profile-title" id="appConfirmTitle">Bestätigen</h2>
        </div>
        <div class="profile-hero-actions">
          <button type="button" class="modal-close w3-button" id="appConfirmClose" aria-label="Schließen">&times;</button>
        </div>
      </header>
      <div class="confirm-delete-body">
        <p class="profile-value" id="appConfirmMessage"></p>
        <div class="profile-actions profile-actions--confirm">
          <div class="profile-actions-primary">
            <button type="button" class="w3-btn profile-btn-primary w3-border w3-mobile" id="appConfirmOk">OK</button>
          </div>
          <button type="button" class="w3-btn w3-border w3-mobile" id="appConfirmCancel">Abbrechen</button>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
}
if(!empty($GLOBALS['mlDeferredPageModals'])) {
    echo $GLOBALS['mlDeferredPageModals'];
    unset($GLOBALS['mlDeferredPageModals']);
    if(function_exists('archivFooterParseReset')) {
        archivFooterParseReset();
    }
}
if(!empty($GLOBALS['archivMotdHtml'])) {
    echo $GLOBALS['archivMotdHtml'];
    unset($GLOBALS['archivMotdHtml']);
    if(function_exists('archivFooterParseReset')) {
        archivFooterParseReset();
    }
}
if(!empty($GLOBALS['mlDeferredToasts'])) {
    echo '<div class="app-toast-host" aria-live="polite">'
        .$GLOBALS['mlDeferredToasts']
        .'</div>';
    unset($GLOBALS['mlDeferredToasts']);
}
if(!empty($GLOBALS['mlHeaderRendered'])) {
?>
<script src="<?php echo assetUrl('js/listRowSearch.js'); ?>"></script>
<script src="<?php echo assetUrl('js/modal.js'); ?>"></script>
<script src="<?php echo assetUrl('js/appDialog.js'); ?>"></script>
<script src="<?php echo assetUrl('js/toast.js'); ?>"></script>
<?php
    if(!empty($GLOBALS['mlDeferredPageScripts']) && is_array($GLOBALS['mlDeferredPageScripts'])) {
        foreach($GLOBALS['mlDeferredPageScripts'] as $rel) {
            $rel = ltrim(str_replace('\\', '/', (string)$rel), '/');
            if($rel === '') {
                continue;
            }
            echo '<script src="'.assetUrl($rel).'"></script>'."\n";
        }
        unset($GLOBALS['mlDeferredPageScripts']);
    }
}
?>
  </body>
</html>
