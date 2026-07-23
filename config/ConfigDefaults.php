<?php
/**
 * Default configuration parameters for new installations / updates.
 * Used by SchemaManager and update.php — do not duplicate elsewhere.
 */
function getConfigDefaults() {
    return array(
        array(
            'Parameter' => 'defaultCompositionCover',
            'Value' => 'data/default/cover.png',
            'Type' => 'string',
            'Description' => 'Default cover image for compositions without cover',
        ),
        array(
            'Parameter' => 'nextcloudBaseUrl',
            'Value' => '',
            'Type' => 'string',
            'Description' => 'Nextcloud WebDAV base URL (empty = disabled)',
        ),
        array(
            'Parameter' => 'urlMeldeliste',
            'Value' => '',
            'Type' => 'string',
            'Description' => 'Basis-URL Meldeliste (Nav-Rücklink; leer = ausgeblendet)',
        ),
    );
}
?>
