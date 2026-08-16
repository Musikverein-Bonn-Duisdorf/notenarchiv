<?php
/**
 * Help guide sections (permission-filtered).
 * Keep this in sync when user-facing workflows change (see ticket workflow / makeVersion reminder).
 *
 * Expected vars: $optionsDB
 */

$sections = array();
$showAdmin = !empty($_SESSION['admin']);
$canEditConfig = hasPermission('perm_write');
$canShowLog = hasPermission('perm_showLog');
$canEditPermissions = hasPermission('perm_editPermissions');
$showAdminHelp = $showAdmin || $canEditPermissions || $canShowLog;
$meldeUrl = isset($optionsDB['urlMeldeliste']) ? trim((string)$optionsDB['urlMeldeliste']) : '';
$mitUrl = isset($optionsDB['urlMitgliederverwaltung']) ? trim((string)$optionsDB['urlMitgliederverwaltung']) : '';
$showMitNav = ($mitUrl !== '' && hasMeldePlatformPermission('perm_accessMitgliederverwaltung'));
$masterPage = isset($optionsDB['MasterPage']) ? trim((string)$optionsDB['MasterPage']) : '';

$sections[] = array(
    'id' => 'einfuehrung',
    'title' => 'Einführung',
    'body' => '
<p>Das <b>Notenarchiv</b> verwaltet den Vereinsnotenbestand: Stücke, Stimmen, Sammlungen, Komponisten und Verlage. Die Konten kommen aus der Meldeliste; <b>Lesen/Schreiben/Log/Rechte</b> werden im Archiv lokal vergeben.</p>
<p>Über die Navigation erreichst du die Bereiche, die für dich freigeschaltet sind: auf breiten Bildschirmen links mit Text, auf Tablet und Smartphone unten als Leiste (weitere Einträge und Admin unter <b>Mehr</b>). Diese Hilfe zeigt nur Abschnitte, die zu deinen aktuellen Rechten passen.</p>
'
);

$sections[] = array(
    'id' => 'navigation',
    'title' => 'Navigation',
    'body' => '
<p>Auf dem Desktop steht die Navigation links (Icons mit Beschriftung). Auf schmalen Bildschirmen (Tablet und Smartphone) unten; unter <b>Mehr</b> findest du weitere Einträge, Admin und Ausloggen.</p>
<ul class="help-list">
<li><i class="fas fa-home"></i> <b>Stücke</b> – Katalog der Kompositionen (Suche, Filter, Detail)</li>
<li><i class="fas fa-folder-open"></i> <b>Sammlungen</b> – physische oder thematische Zusammenstellungen</li>
<li><i class="fas fa-feather"></i> <b>Komponisten</b> – Komponisten und Arrangeure</li>
<li><i class="fas fa-industry"></i> <b>Verlage</b> – Verlagsstammdaten</li>
'.($meldeUrl !== '' ? '<li><i class="fas fa-clipboard-list"></i> <b>Meldeliste</b> – Rückkehr zur Meldeliste</li>' : '').'
'.($showMitNav ? '<li><i class="fas fa-id-card"></i> <b>Mitglieder</b> – Mitgliederverwaltung (Melde-Recht)</li>' : '').'
<li>Logo oben rechts – öffnet die <b>Vereinshomepage</b> in einem neuen Tab</li>
<li><i class="fas fa-circle-question"></i> <b>Hilfe</b> – diese Seite inkl. Changelog</li>
'.($showAdminHelp ? '<li><i class="fas fa-wrench"></i> <b>Admin</b> – Verwaltung (Anlegen, Konfiguration, Updater, Log, Berechtigungen); Desktop und mobil unter Mehr</li>' : '').'
<li><i class="fas fa-sign-out-alt"></i> <b>Ausloggen</b> – Sitzung beenden</li>
</ul>
'
);

$sections[] = array(
    'id' => 'stuecke',
    'title' => 'Stücke',
    'body' => '
<p>Unter <b>Stücke</b> siehst du den Katalog. Über die Suchzeile filterst du nach Titel, Komponist oder weiteren Feldern; Sortier-Chips und Nachladen beim Scrollen wie in der Meldeliste.</p>
<p>Ein Klick öffnet zuerst ein Detail-Modal; von dort gelangst du mit <b>Öffnen</b> bzw. <b>Bearbeiten</b> zur Stückseite (Stammdaten, Cover, Stimmsatz, Sammlungen).</p>
'.($showAdmin ? '<p>Admins legen neue Stücke über <b>Admin → Stück</b> bzw. Plus auf der Stückliste an (<code>composition.php</code>). Sammlungszuordnung per Chips mit Speichern. Sammlungen, Komponisten und Verlage haben jeweils eigene Anlege-Seiten.</p>' : '').'
'
);

$sections[] = array(
    'id' => 'sammlungen',
    'title' => 'Sammlungen',
    'body' => '
<p>Unter <b>Sammlungen</b> verwaltest du Zusammenstellungen (z.&nbsp;B. Ordner im Schrank oder thematische Listen). Stücke können einer oder mehreren Sammlungen zugeordnet sein. Die Liste startet zugeklappt (Stückzahl am Titel); Aufklappen zeigt den Inhalt. Standard-Sortierung: neueste zuerst (ID). Archivierte Sammlungen sind standardmäßig ausgeblendet; der Filter <b>Archivierte</b> zeigt sie wieder. <b>Details</b> öffnet das Modal'.($showAdmin ? '; <b>Bearbeiten</b> führt zur Anlege-Seite (dort auch das Flag <b>Archiviert</b>). Dort, auf der Stückseite und im Sammlungen-Dialog am Stück pflegst du die Zuordnung per Chips (Nr = Reihenfolge). Anlegen über Plus bzw. <b>Admin → Sammlung</b>' : '').'.</p>
'
);

$sections[] = array(
    'id' => 'komponisten-verlage',
    'title' => 'Komponisten &amp; Verlage',
    'body' => '
<p><b>Komponisten</b> und <b>Verlage</b> sind Stammdaten für den Katalog (Sortierung und Nachladen wie bei den Stücken). Anlegen geht über die jeweiligen Anlege-Seiten (<b>Admin → Komponist</b> / <b>Verlag</b> oder Plus in der Liste). Verlage können Website und Logo haben. Stücke können eine <b>Produktseite</b> (konkrete Verlags-URL) haben; der Verlagsname verlinkt dorthin bzw. auf die Verlags-Website. Ein Klick in der Liste öffnet ein Detail-Modal; <b>Bearbeiten</b> führt auf die Anlege-Seite (dort auch Löschen und Foto).</p>
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
    'title' => 'Login &amp; Rechte',
    'body' => '
<p>Zugang zum Archiv setzt in der Meldeliste das Recht <b>Notenarchiv</b> voraus (Nav/SSO oder Passwort-Login). Katalog-<b>Lesen</b>, <b>Schreiben</b> (inkl. Config/Backup/API), <b>Logfile lesen</b> und <b>Berechtigungen</b> werden lokal im Archiv vergeben. Der erste Nutzer, der sich jemals anmeldet, erhält automatisch alle lokalen Rechte und kann andere freischalten.</p>
'.($meldeUrl !== '' ? '<p>Über den Nav-Eintrag <b>Meldeliste</b> kehrst du zurück: <a href="'.htmlspecialchars($meldeUrl, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($meldeUrl, ENT_QUOTES, 'UTF-8').'</a></p>' : '').'
'.($showMitNav ? '<p>Mit Melde-Recht <b>Mitgliederverwaltung</b> erscheint der Nav-Link <b>Mitglieder</b>'.($meldeUrl !== '' ? ' (SSO über die Meldeliste)' : '').'.</p>' : '').'
'.($masterPage !== '' ? '<p>Die Vereinshomepage erreichst du über das Logo oder: <a href="'.htmlspecialchars($masterPage, ENT_QUOTES, 'UTF-8').'" target="_blank" rel="noopener noreferrer">'.htmlspecialchars($masterPage, ENT_QUOTES, 'UTF-8').'</a></p>' : '').'
'
);

$sections[] = array(
    'id' => 'admin-verwaltung',
    'title' => 'Admin: Verwaltung',
    'visible' => $showAdminHelp,
    'body' => '
<ul class="help-list">
'.($showAdmin ? '<li><b>Stück</b> – Anlegen/Bearbeiten auf der Stückseite (<code>composition.php</code>); Liste → Detail-Modal → Bearbeiten/Öffnen. <b>Sammlung / Komponist / Verlag</b> – jeweils eigene Anlege-Seite</li>' : '').'
'.($canEditConfig ? '
<li><b>Konfiguration</b> – Farben/Farbschema, Site-Name, URLs (Melde/Mitglieder), Feature-Schalter; Änderungen erscheinen im Log. Archiv-Parameter heißen in der Datenbank <code>Archiv*</code></li>
<li><b>Backup</b> – ZIP mit Versionsinfo und SQL nur für Archiv-Tabellen (<code>archiv_*</code>), nicht Melde-Identity. Download im Browser, CLI <code>php cron.php CRONID backup</code>, remote nur mit eigenem <code>$backupToken</code> (≥32 Zeichen) über <code>cron.php?id=…&amp;cmd=backup</code>. Erfolgreiche Downloads erscheinen im Log als Info, Fehler als Error. PDFs unter <code>data/</code> gehören nicht ins ZIP</li>
<li><b>API-Token</b> – JSON-API unter <code>api/</code> (Medien, Noten, Mappen) mit persönlichem Bearer-Token (Schreiben-Recht). Ausgabe einmalig per Login-Endpunkt oder CLI <code>php scripts/issueApiToken.php</code>; Tokens nicht in Repos speichern. Details: <code>docs/api.md</code></li>
<li><b>Updater</b> – Software-Update vom Remote und Datenbank-Prüfung/Reparatur; der Bericht listet nur Änderungen und Probleme</li>
' : '').'
'.($canShowLog ? '
<li><b>Log</b> – Anwendungsprotokoll (Server-Suche, Live-Aktualisierung, Nachladen beim Scrollen; Einträge können zu Stücken/Personen verlinken)</li>
' : '').'
'.($canEditPermissions ? '
<li><b>Berechtigungen</b> – lokale Matrix Rechte / Lesen / Schreiben / Logfile lesen für Nutzer mit Melde-Zugang Notenarchiv</li>
' : '').'
</ul>
'
);

$sections[] = array(
    'id' => 'kontakt',
    'title' => 'Kontakt',
    'body' => '
<p>Bei Fragen zum Melde-Zugang (Notenarchiv/Mitglieder) wende dich an die Meldeliste-Administration; lokale Archiv-Rechte vergibt, wer hier <b>Berechtigungen</b> hat.</p>
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
