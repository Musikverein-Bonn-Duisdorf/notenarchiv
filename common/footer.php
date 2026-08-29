<?php
if(!empty($GLOBALS['mlHeaderRendered'])) {
?>
</div><!-- .app-main -->
</div><!-- .app-shell -->
<?php
}
/* Close any dangling MotD/rawtext context before deferred markup (ARCHIV-49). */
if(function_exists('archivFooterParseReset')) {
    archivFooterParseReset();
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
