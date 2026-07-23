<?php
/**
 * Default configuration parameters for new installations / updates.
 * Used by SchemaManager and update.php — do not duplicate elsewhere.
 * Color defaults mirror Meldeliste brand kit (libs/colorschemes.php).
 *
 * Melde-colliding keys are stored as Archiv* in archiv_config; loadconfig()
 * aliases them to logical names (colorNav, WebSiteURL, …) for UI code.
 */
function getConfigDefaults() {
    return array(
        array(
            'Parameter' => 'ArchivColorLogDefault',
            'Value' => '#FDFFFC',
            'Type' => 'color',
            'Description' => 'Farbe von DEFAULT Logeintr&auml;gen',
        ),
        array(
            'Parameter' => 'ArchivColorLogFatal',
            'Value' => '#F44336',
            'Type' => 'color',
            'Description' => 'Farbe von FATAL Logeintr&auml;gen',
        ),
        array(
            'Parameter' => 'ArchivColorLogError',
            'Value' => '#FFC300',
            'Type' => 'color',
            'Description' => 'Farbe von ERROR Logeintr&auml;gen',
        ),
        array(
            'Parameter' => 'ArchivColorLogWarning',
            'Value' => '#FFC300',
            'Type' => 'color',
            'Description' => 'Farbe von WARNING Logeintr&auml;gen',
        ),
        array(
            'Parameter' => 'ArchivColorLogDBDelete',
            'Value' => '#FDFFFC',
            'Type' => 'color',
            'Description' => 'Farbe von DB DELETE Logeintr&auml;gen',
        ),
        array(
            'Parameter' => 'ArchivColorLogDBInsert',
            'Value' => '#FDFFFC',
            'Type' => 'color',
            'Description' => 'Farbe von DB INSERT Logeintr&auml;gen',
        ),
        array(
            'Parameter' => 'ArchivColorLogDBUpdate',
            'Value' => '#FDFFFC',
            'Type' => 'color',
            'Description' => 'Farbe von DB UPDATE Logeintr&auml;gen',
        ),
        array(
            'Parameter' => 'ArchivColorLogEmail',
            'Value' => '#FDFFFC',
            'Type' => 'color',
            'Description' => 'Farbe von EMAIL Logeintr&auml;gen',
        ),
        array(
            'Parameter' => 'ArchivColorLogInfo',
            'Value' => '#FDFFFC',
            'Type' => 'color',
            'Description' => 'Farbe von INFO Logeintr&auml;gen',
        ),
        array(
            'Parameter' => 'ArchivColorBtnYes',
            'Value' => '#4CAF50',
            'Type' => 'color',
            'Description' => 'Farbe von Ja-Buttons',
        ),
        array(
            'Parameter' => 'ArchivColorBtnNo',
            'Value' => '#F44336',
            'Type' => 'color',
            'Description' => 'Farbe von Nein-Buttons',
        ),
        array(
            'Parameter' => 'ArchivColorBtnMaybe',
            'Value' => '#2196F3',
            'Type' => 'color',
            'Description' => 'Farbe von Vielleicht-Buttons',
        ),
        array(
            'Parameter' => 'ArchivColorDisabled',
            'Value' => '#9E9E9E',
            'Type' => 'color',
            'Description' => 'Farbe von deaktivierten Elementen',
        ),
        array(
            'Parameter' => 'ArchivColorBtnEdit',
            'Value' => '#009688',
            'Type' => 'color',
            'Description' => 'Farbe von Edit-Buttons',
        ),
        array(
            'Parameter' => 'ArchivHoverEffect',
            'Value' => 'w3-hover-gray',
            'Type' => 'string',
            'Description' => 'Stil des Hover-Effekts',
        ),
        array(
            'Parameter' => 'ArchivColorSuccess',
            'Value' => '#4CAF50',
            'Type' => 'color',
            'Description' => 'Farbe von Erfolgsmeldungen',
        ),
        array(
            'Parameter' => 'ArchivColorNav',
            'Value' => '#969696',
            'Type' => 'color',
            'Description' => 'Farbe der Navigationsleiste',
        ),
        array(
            'Parameter' => 'ArchivColorNavAdmin',
            'Value' => '#607D8B',
            'Type' => 'color',
            'Description' => 'Farbe der Admin-Navigationsleiste',
        ),
        array(
            'Parameter' => 'ArchivColorBackground',
            'Value' => '#FDFFFC',
            'Type' => 'color',
            'Description' => 'Hintergrundfarbe der Seite',
        ),
        array(
            'Parameter' => 'ArchivColorTitle',
            'Value' => '#FDF9E7',
            'Type' => 'color',
            'Description' => 'Farbe der Titelzeile',
        ),
        array(
            'Parameter' => 'ArchivColorTitleBar',
            'Value' => '#345A95',
            'Type' => 'color',
            'Description' => 'Farbe von Seiten-Titelleisten',
        ),
        array(
            'Parameter' => 'ArchivColorWarning',
            'Value' => '#FDF9E7',
            'Type' => 'color',
            'Description' => 'Farbe von Warnhinweisen',
        ),
        array(
            'Parameter' => 'ArchivColorBtnSubmit',
            'Value' => '#9C27B0',
            'Type' => 'color',
            'Description' => 'Farbe von Submit-Buttons',
        ),
        array(
            'Parameter' => 'ArchivColorBtnDelete',
            'Value' => '#F44336',
            'Type' => 'color',
            'Description' => 'Farbe von Löschen-Buttons',
        ),
        array(
            'Parameter' => 'ArchivColorInputBackground',
            'Value' => '#F1F1F1',
            'Type' => 'color',
            'Description' => 'Hintergrundfarbe von Eingabefeldern',
        ),
        array(
            'Parameter' => 'ArchivColorSchemeActive',
            'Value' => 'classic',
            'Type' => 'string',
            'Description' => 'Aktives Farbschema (classic, light, dark, gold, soft)',
        ),
        array(
            'Parameter' => 'ArchivColorSchemes',
            'Value' => '',
            'Type' => 'internal',
            'Description' => 'Gespeicherte Farbschemata (JSON, intern)',
        ),
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
        array(
            'Parameter' => 'ArchivShowBranchBannerAlways',
            'Value' => '0',
            'Type' => 'bool',
            'Description' => 'Branch-Banner auch auf master anzeigen',
        ),
        array(
            'Parameter' => 'ArchivWebSiteName',
            'Value' => 'Notenarchiv - Musikverein Bonn-Duisdorf',
            'Type' => 'string',
            'Description' => 'Name der Website (Titel) — Archiv, nicht Melde',
        ),
        array(
            'Parameter' => 'ArchivWebSiteNameShort',
            'Value' => 'Notenarchiv - MVD',
            'Type' => 'string',
            'Description' => 'Kurzer Website-Name (mobil) — Archiv, nicht Melde',
        ),
        array(
            'Parameter' => 'ArchivWebSiteURL',
            'Value' => 'index.php',
            'Type' => 'string',
            'Description' => 'Basis-URL / Startseite des Notenarchivs — nicht Melde WebSiteURL',
        ),
        array(
            'Parameter' => 'ArchivMasterPage',
            'Value' => 'https://www.musikverein-bonn-duisdorf.de',
            'Type' => 'string',
            'Description' => 'URL der Vereinshomepage (Archiv)',
        ),
        array(
            'Parameter' => 'ArchivFavicon',
            'Value' => 'imgs/Logo.png',
            'Type' => 'string',
            'Description' => 'Favicon-URL oder Pfad (Archiv)',
        ),
    );
}
?>
