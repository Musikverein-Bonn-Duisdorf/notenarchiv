<?php
/**
 * Expected database schema version for Notenarchiv only.
 * The integer lives in schema_version_number.php so it can be re-read after git pull.
 *
 * IMPORTANT: Do not name this getExpectedSchemaVersion() — Melde defines that too.
 * Sibling apps share one MySQL; colliding PHP function names poison the other app's
 * expected version when both codepaths load in one process.
 */
function archivGetExpectedSchemaVersion($forceReload = false) {
    static $cached = null;
    if($forceReload || $cached === null) {
        $cached = (int)include __DIR__.'/schema_version_number.php';
    }
    return $cached;
}
?>
