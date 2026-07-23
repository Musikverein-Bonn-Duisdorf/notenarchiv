<?php
/**
 * Default configuration parameters for new installations / updates.
 * Used by SchemaManager and update.php — do not duplicate elsewhere.
 * Color defaults mirror Meldeliste brand kit (libs/colorschemes.php).
 */
function getConfigDefaults() {
    return array(
        array(
            'Parameter' => 'colorLogDefault',
            'Value' => '#FDFFFC',
            'Type' => 'color',
            'Description' => 'Farbe von DEFAULT Logeintr&auml;gen',
        ),
        array(
            'Parameter' => 'colorLogFatal',
            'Value' => '#F44336',
            'Type' => 'color',
            'Description' => 'Farbe von FATAL Logeintr&auml;gen',
        ),
        array(
            'Parameter' => 'colorLogError',
            'Value' => '#FFC300',
            'Type' => 'color',
            'Description' => 'Farbe von ERROR Logeintr&auml;gen',
        ),
        array(
            'Parameter' => 'colorLogWarning',
            'Value' => '#FFC300',
            'Type' => 'color',
            'Description' => 'Farbe von WARNING Logeintr&auml;gen',
        ),
        array(
            'Parameter' => 'colorLogDBDelete',
            'Value' => '#FDFFFC',
            'Type' => 'color',
            'Description' => 'Farbe von DB DELETE Logeintr&auml;gen',
        ),
        array(
            'Parameter' => 'colorLogDBInsert',
            'Value' => '#FDFFFC',
            'Type' => 'color',
            'Description' => 'Farbe von DB INSERT Logeintr&auml;gen',
        ),
        array(
            'Parameter' => 'colorLogDBUpdate',
            'Value' => '#FDFFFC',
            'Type' => 'color',
            'Description' => 'Farbe von DB UPDATE Logeintr&auml;gen',
        ),
        array(
            'Parameter' => 'colorLogEmail',
            'Value' => '#FDFFFC',
            'Type' => 'color',
            'Description' => 'Farbe von EMAIL Logeintr&auml;gen',
        ),
        array(
            'Parameter' => 'colorLogInfo',
            'Value' => '#FDFFFC',
            'Type' => 'color',
            'Description' => 'Farbe von INFO Logeintr&auml;gen',
        ),
        array(
            'Parameter' => 'colorBtnYes',
            'Value' => '#4CAF50',
            'Type' => 'color',
            'Description' => 'Farbe von Ja-Buttons',
        ),
        array(
            'Parameter' => 'colorBtnNo',
            'Value' => '#F44336',
            'Type' => 'color',
            'Description' => 'Farbe von Nein-Buttons',
        ),
        array(
            'Parameter' => 'colorBtnMaybe',
            'Value' => '#2196F3',
            'Type' => 'color',
            'Description' => 'Farbe von Vielleicht-Buttons',
        ),
        array(
            'Parameter' => 'colorDisabled',
            'Value' => '#9E9E9E',
            'Type' => 'color',
            'Description' => 'Farbe von deaktivierten Elementen',
        ),
        array(
            'Parameter' => 'colorBtnEdit',
            'Value' => '#009688',
            'Type' => 'color',
            'Description' => 'Farbe von Edit-Buttons',
        ),
        array(
            'Parameter' => 'HoverEffect',
            'Value' => 'w3-hover-gray',
            'Type' => 'string',
            'Description' => 'Stil des Hover-Effekts',
        ),
        array(
            'Parameter' => 'colorSuccess',
            'Value' => '#4CAF50',
            'Type' => 'color',
            'Description' => 'Farbe von Erfolgsmeldungen',
        ),
        array(
            'Parameter' => 'colorNav',
            'Value' => '#969696',
            'Type' => 'color',
            'Description' => 'Farbe der Navigationsleiste',
        ),
        array(
            'Parameter' => 'colorNavAdmin',
            'Value' => '#607D8B',
            'Type' => 'color',
            'Description' => 'Farbe der Admin-Navigationsleiste',
        ),
        array(
            'Parameter' => 'colorBackground',
            'Value' => '#FDFFFC',
            'Type' => 'color',
            'Description' => 'Hintergrundfarbe der Seite',
        ),
        array(
            'Parameter' => 'colorTitle',
            'Value' => '#FDF9E7',
            'Type' => 'color',
            'Description' => 'Farbe der Titelzeile',
        ),
        array(
            'Parameter' => 'colorTitleBar',
            'Value' => '#345A95',
            'Type' => 'color',
            'Description' => 'Farbe von Seiten-Titelleisten',
        ),
        array(
            'Parameter' => 'colorWarning',
            'Value' => '#FDF9E7',
            'Type' => 'color',
            'Description' => 'Farbe von Warnhinweisen',
        ),
        array(
            'Parameter' => 'colorBtnSubmit',
            'Value' => '#9C27B0',
            'Type' => 'color',
            'Description' => 'Farbe von Submit-Buttons',
        ),
        array(
            'Parameter' => 'colorBtnDelete',
            'Value' => '#F44336',
            'Type' => 'color',
            'Description' => 'Farbe von Löschen-Buttons',
        ),
        array(
            'Parameter' => 'colorInputBackground',
            'Value' => '#F1F1F1',
            'Type' => 'color',
            'Description' => 'Hintergrundfarbe von Eingabefeldern',
        ),
        array(
            'Parameter' => 'colorSchemeActive',
            'Value' => 'classic',
            'Type' => 'string',
            'Description' => 'Aktives Farbschema (classic, light, dark, gold, soft)',
        ),
        array(
            'Parameter' => 'colorSchemes',
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
            'Parameter' => 'showBranchBannerAlways',
            'Value' => '0',
            'Type' => 'bool',
            'Description' => 'Branch-Banner auch auf master anzeigen',
        ),
        array(
            'Parameter' => 'WebSiteName',
            'Value' => 'Notenarchiv - Musikverein Bonn-Duisdorf',
            'Type' => 'string',
            'Description' => 'Name der Website (Titel)',
        ),
        array(
            'Parameter' => 'WebSiteNameShort',
            'Value' => 'Notenarchiv - MVD',
            'Type' => 'string',
            'Description' => 'Kurzer Website-Name (mobil)',
        ),
        array(
            'Parameter' => 'WebSiteURL',
            'Value' => 'index.php',
            'Type' => 'string',
            'Description' => 'Basis-URL / Startseite des Notenarchivs',
        ),
        array(
            'Parameter' => 'MasterPage',
            'Value' => 'https://www.musikverein-bonn-duisdorf.de',
            'Type' => 'string',
            'Description' => 'URL der Vereinshomepage',
        ),
        array(
            'Parameter' => 'favicon',
            'Value' => 'imgs/Logo.png',
            'Type' => 'string',
            'Description' => 'Favicon-URL oder Pfad',
        ),
    );
}
?>
