<?php
/**
 * Help guide sections (permission-filtered).
 * Keep this in sync when user-facing workflows change (see ticket workflow / makeVersion reminder).
 *
 * Expected vars: $optionsDB
 */

$sections = array();
$showAdmin = !empty($_SESSION['admin']);
$canEditConfig = hasPermission('perm_editConfig');
$canShowLog = hasPermission('perm_showLog');
$meldeUrl = isset($optionsDB['urlMeldeliste']) ? trim((string)$optionsDB['urlMeldeliste']) : '';
$masterPage = isset($optionsDB['MasterPage']) ? trim((string)$optionsDB['MasterPage']) : '';

$sections[] = array(
    'id' => 'einfuehrung',
    'title' => 'Einführung',
    'body' => '
<p>Das <b>Notenarchiv</b> verwaltet den Vereinsnotenbestand: Stücke, Stimmen, Sammlungen, Komponisten und Verlage. Login und Rechte kommen aus der Meldeliste (SSO).</p>
<p>Über die Navigation erreichst du die Bereiche, die für dich freigeschaltet sind: auf breiten Bildschirmen links mit Text, auf dem Smartphone unten als Leiste (weitere Einträge und Admin unter <b>Mehr</b>). Diese Hilfe zeigt nur Abschnitte, die zu deinen aktuellen Rechten passen.</p>
'
);

$sections[] = array(
    'id' => 'navigation',
    'title' => 'Navigation',
    'body' => '
<p>Auf dem Desktop steht die Navigation links (Icons mit Beschriftung). Auf dem Smartphone unten; unter <b>Mehr</b> findest du weitere Einträge, Admin und Ausloggen.</p>
<ul class="help-list">
<li><i class="fas fa-home"></i> <b>Stücke</b> – Katalog der Kompositionen (Suche, Filter, Detail)</li>
<li><i class="fas fa-folder-open"></i> <b>Sammlungen</b> – physische oder thematische Zusammenstellungen</li>
<li><i class="fas fa-feather"></i> <b>Komponisten</b> – Komponisten und Arrangeure</li>
<li><i class="fas fa-industry"></i> <b>Verlage</b> – Verlagsstammdaten</li>
'.($meldeUrl !== '' ? '<li><i class="fas fa-clipboard-list"></i> <b>Meldeliste</b> – Rückkehr zur Meldeliste (SSO)</li>' : '').'
<li>Logo oben rechts – öffnet die <b>Vereinshomepage</b> in einem neuen Tab</li>
<li><i class="fas fa-circle-question"></i> <b>Hilfe</b> – diese Seite inkl. Changelog</li>
'.($showAdmin ? '<li><i class="fas fa-wrench"></i> <b>Admin</b> – Verwaltung (Anlegen je Kategorie, Konfiguration, Updater, Log); Desktop unter Mehr, mobil ebenfalls unter Mehr</li>' : '').'
<li><i class="fas fa-sign-out-alt"></i> <b>Ausloggen</b> – Sitzung beenden</li>
</ul>
'
);

$sections[] = array(
    'id' => 'stuecke',
    'title' => 'Stücke',
    'body' => '
<p>Unter <b>Stücke</b> siehst du den Katalog. Über die Suchzeile filterst du nach Titel, Komponist oder weiteren Feldern; lange Listen werden beim Scrollen nachgeladen.</p>
<p>Ein Klick öffnet zuerst ein Detail-Modal; von dort gelangst du mit <b>Öffnen</b> zur Stückseite (Cover, Stimmsatz, Sammlungen)'.($showAdmin ? ' bzw. mit <b>Bearbeiten</b> zur Anlege-/Edit-Seite' : '').'.</p>
'.($showAdmin ? '<p>Admins legen neue Stücke über <b>Admin → Stück</b> bzw. Plus auf der Stückliste an. Sammlungen, Komponisten und Verlage haben jeweils eigene Anlege-Seiten.</p>' : '').'
'
);

$sections[] = array(
    'id' => 'sammlungen',
    'title' => 'Sammlungen',
    'body' => '
<p>Unter <b>Sammlungen</b> verwaltest du Zusammenstellungen (z.&nbsp;B. Ordner im Schrank oder thematische Listen). Stücke können einer oder mehreren Sammlungen zugeordnet sein; die Zuordnung pflegst du in der Stück-Detailansicht'.($showAdmin ? '; Admins legen Sammlungen über Plus bzw. <b>Admin → Sammlung</b> an und bearbeiten sie per Klick auf den Titel' : '').'.</p>
'
);

$sections[] = array(
    'id' => 'komponisten-verlage',
    'title' => 'Komponisten &amp; Verlage',
    'body' => '
<p><b>Komponisten</b> und <b>Verlage</b> sind Stammdaten für den Katalog. Anlegen geht über die jeweiligen Anlege-Seiten (<b>Admin → Komponist</b> / <b>Verlag</b> oder Plus in der Liste). Ein Klick in der Liste öffnet ein Detail-Modal; <b>Bearbeiten</b> führt auf die Anlege-Seite (dort auch Löschen und Foto). Stücke verweisen auf diese Einträge.</p>
'
);

$sections[] = array(
    'id' => 'stimmsatz',
    'title' => 'Stimmsatz',
    'body' => '
<p>Zum Stück gehört ein <b>Stimmsatz</b> (Instrumentenstimmen). Über <b>Stimmsatz drucken</b> erzeugst du eine druckfähige Übersicht für Proben und Auftritte.</p>
<p>In der Meldeliste können Musiker eine primäre Stimme und Fallback-Instrumente hinterlegen – das Archiv nutzt diese Zuordnung für die Stimmsatz-Darstellung, sofern konfiguriert.</p>
'
);

$sections[] = array(
    'id' => 'login-sso',
    'title' => 'Login &amp; Meldeliste',
    'body' => '
<p>Das Notenarchiv teilt die Benutzerkonten mit der Meldeliste. Typischer Einstieg: von der Meldeliste aus per SSO-Link (einmaliges Ticket) oder direktes Login mit denselben Zugangsdaten.</p>
'.($meldeUrl !== '' ? '<p>Über den Nav-Eintrag <b>Meldeliste</b> kehrst du zurück: <a href="'.htmlspecialchars($meldeUrl, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($meldeUrl, ENT_QUOTES, 'UTF-8').'</a></p>' : '').'
'.($masterPage !== '' ? '<p>Die Vereinshomepage erreichst du über das Logo oder: <a href="'.htmlspecialchars($masterPage, ENT_QUOTES, 'UTF-8').'" target="_blank" rel="noopener noreferrer">'.htmlspecialchars($masterPage, ENT_QUOTES, 'UTF-8').'</a></p>' : '').'
'
);

$sections[] = array(
    'id' => 'admin-verwaltung',
    'title' => 'Admin: Verwaltung',
    'visible' => $showAdmin,
    'body' => '
<ul class="help-list">
<li><b>Stück / Sammlung / Komponist / Verlag</b> – jeweils eigene Anlege-Seite (Melde-Stil); Stück/Komponist/Verlag: Liste → Detail-Modal → Bearbeiten; Sammlung weiter per Titel</li>
'.($canEditConfig ? '
<li><b>Konfiguration</b> – Farben/Farbschema, Site-Name, URLs, Feature-Schalter; Änderungen erscheinen im Log. Archiv-Parameter heißen in der Datenbank <code>Archiv*</code> (Anzeige in der Hilfe/UI oft unter dem kurzen Namen)</li>
<li><b>Backup</b> – ZIP mit Versionsinfo und SQL nur für Archiv-Tabellen (<code>archiv_*</code>), nicht Melde-Identity. Download im Browser, CLI <code>php cron.php CRONID backup</code>, remote nur mit eigenem <code>$backupToken</code> (≥32 Zeichen) über <code>cron.php?id=…&amp;cmd=backup</code>. Erfolgreiche Downloads erscheinen im Log als Info, Fehler als Error. PDFs unter <code>data/</code> gehören nicht ins ZIP</li>
<li><b>Updater</b> – Software-Update vom Remote und Datenbank-Prüfung/Reparatur; der Bericht listet nur Änderungen und Probleme</li>
' : '').'
'.($canShowLog ? '
<li><b>Log</b> – Anwendungsprotokoll (Suche, Live-Aktualisierung, Nachladen beim Scrollen)</li>
' : '').'
</ul>
'
);

$sections[] = array(
    'id' => 'kontakt',
    'title' => 'Kontakt',
    'body' => '
<p>Bei Fragen zu Rechten oder Zugang wende dich an die Meldeliste-Administration.</p>
<p>Die installierte Version ist im Changelog markiert (rechts bzw. darunter).</p>
'
);

$visible = array();
foreach($sections as $section) {
    if(isset($section['visible']) && !$section['visible']) {
        continue;
    }
    $visible[] = $section;
}
?>
<nav class="help-toc w3-card w3-padding w3-margin-bottom" aria-label="Inhalt">
  <h3 class="w3-margin-top">Inhalt</h3>
  <ol class="help-toc-list">
<?php foreach($visible as $section) { ?>
    <li><a href="#help-<?php echo htmlspecialchars($section['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo $section['title']; ?></a></li>
<?php } ?>
    <li class="w3-hide-large"><a href="#help-changelog">Changelog</a></li>
  </ol>
</nav>

<?php foreach($visible as $section) { ?>
<section class="help-section w3-margin-bottom" id="help-<?php echo htmlspecialchars($section['id'], ENT_QUOTES, 'UTF-8'); ?>">
  <h3><?php echo $section['title']; ?></h3>
  <div class="help-section-body">
    <?php echo $section['body']; ?>
  </div>
</section>
<?php } ?>
