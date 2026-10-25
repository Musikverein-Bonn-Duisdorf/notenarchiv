# Noten-PDF-Spiegel (ARCHIV-9 / ARCHIV-11)

Stand: 2026-10 — lokale Spiegelkopie fertig benannt; Index in die DB noch offen.

## Quellen und Pfade

| Rolle | Pfad | Regel |
|-------|------|--------|
| Cloud-Mount (pCloud/Nextcloud) | `C:\Users\Manuel Schedler\Documents\MVD Cloud\Notenarchiv` | **nur lesen**, nie umbenennen/löschen |
| Lokaler App-Spiegel | `{repo}/data/Notenarchiv/` | gitignored (`data/*`); Arbeitskopie für Index/UI |
| Laptop-/Server-Kopie | `schedler@manuelschedler.de:/home/schedler/MVD/Notenarchiv` | Weiterarbeit außerhalb des Windows-Dev-PCs |

WebDAV ([ARCHIV-10](https://musikverein-bonn-duisdorf.atlassian.net/browse/ARCHIV-10)) ist nachrangig — Mount/Spiegel ersetzen ihn lokal.

## Dateikonvention

**Ordner:** `{RegistrationNumber:04d} - {Titel}`  
Beispiel: `0001 - LOOK AT THE WORLD`

**Datei:** `{nn}_{Instrument}_{Stimme}.pdf`

| Beispiel | Bedeutung |
|----------|-----------|
| `00_Score.pdf` | Partitur / Direktion |
| `01_Flute_1.pdf` | Flöte 1 |
| `01_Flute_1-2.pdf` | Flöte 1–2 kombiniert |
| `03_Clarinet_1.pdf` | Klarinette 1 |
| `06_EbClarinet_1.pdf` | Es-Klarinette |
| `12_Baritone_BC.pdf` | Bariton Bassschlüssel |
| `00_Score_Condensed.pdf` | Condensed-/Cue-Seite |
| `00_Score_Fragment.pdf` | Kurzer Score-Ausschnitt ohne Stimmenkopf |

Instrument-Tokens (Auswahl): `Piccolo`, `Flute`, `Oboe`, `EnglishHorn`, `Bassoon`, `EbClarinet`, `Clarinet`, `AltoClarinet`, `BassClarinet`, `SopranoSax`, `AltoSax`, `TenorSax`, `BariSax`, `Trumpet`, `Cornet`, `Flugelhorn`, `Horn`, `Trombone`, `Euphonium`, `Baritone`, `Tuba`, `Bass`, `Percussion`, `Timpani`, `Mallets`, `Piano`, `Guitar`, `Keyboard`, `Voice`, `Solo`, `Score`.

## Bestand (Spiegel)

- ca. **2520** PDFs in nummerierten Werk-Ordnern (+ `ZZZ_(noch) ohne Nummer` für Unnummerierte)
- Nach Normalisierung + Screening: **alle** Dateien konventionsbenannt (keine `_REVIEW_`-Reste)

## Scripts

| Script | Zweck |
|--------|--------|
| [`scripts/syncScoreMirror.ps1`](../scripts/syncScoreMirror.ps1) | Cloud-Mount → `data/Notenarchiv/` (robocopy, Quelle read-only) |
| [`scripts/normalizeScoreMirrorNames.py`](../scripts/normalizeScoreMirrorNames.py) | Heterogene Verlagsnamen → Konvention; Unklare → `_REVIEW_` |
| [`scripts/screenReviewScores.py`](../scripts/screenReviewScores.py) | `_REVIEW_` per Dateiname / PDF-Text / OCR zuordnen und umbenennen |
| [`scripts/indexLocalScoreMirror.php`](../scripts/indexLocalScoreMirror.php) | Spiegel → `archiv_ScoreFile` (RegistrationNumber + Parser) |

Reports (ebenfalls unter dem Spiegel, gitignored): `_normalize_report.json`, `_screen_report.json`.

### Screening-Methoden (ARCHIV-11)

1. **Dateiname** — DE/EN/NL-Muster, StMartin-`in_b`/`in_es`, Verlags-`Nn_Instrument`
2. **PDF-Textschicht** — wo vorhanden
3. **OCR** (RapidOCR) — Stimmenkopf oben links auf Scans (z. B. Spiritual Moments `doc…`)
4. **Manuelle Overrides** — wenige Restfälle nach OCR-Snippet (Solo-Trompete/Arban, Piano-Begleitung, Condensed-Cues)

## App-Anbindung

- Config: `scoreMirrorDirectory` = `data/Notenarchiv/` ([`config/ConfigDefaults.php`](../config/ConfigDefaults.php))
- Pfadauflösung: [`libs/ScoreMirror.php`](../libs/ScoreMirror.php)
- Dateiname → Token: [`libs/ScoreFilenameParser.php`](../libs/ScoreFilenameParser.php)
- Download UI: `score-file.php?id=` (Session, `perm_read`); API: `api/compositions/scores/download.php`
- Stimmverteilung: [`libs/Part.php`](../libs/Part.php) auf Stückseite
- Gaps: `api/gaps/media.php?scores=1` → u. a. `score_index_unmatched`

Löschen über UI/API entfernt **nur** Uploads unter `data/Compositions/{id}/`, nicht den Spiegel.

## Nächste Schritte

1. Auf dem Laptop/Server Spiegel unter `/home/schedler/MVD/Notenarchiv` nutzen (oder nach `data/Notenarchiv/` linken/kopieren).
2. `php scripts/indexLocalScoreMirror.php` gegen Dev-DB (Melde-`Instrument` + `Composition.RegistrationNumber` müssen passen).
3. Unmatched aus Index-Report nachziehen; Stimmsatz-Druck (ARCHIV-12+) später.

## Tickets

- Epic: [ARCHIV-9](https://musikverein-bonn-duisdorf.atlassian.net/browse/ARCHIV-9)
- Indizierung / Naming: [ARCHIV-11](https://musikverein-bonn-duisdorf.atlassian.net/browse/ARCHIV-11)
- WebDAV optional: [ARCHIV-10](https://musikverein-bonn-duisdorf.atlassian.net/browse/ARCHIV-10)
