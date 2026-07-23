# archiv_ Schema

| Tabelle | Zweck |
|---------|--------|
| Composer | Stammdaten Komponist |
| Publisher | Verlag |
| Collection / CollectionItem | Sammlungen |
| Composition | Werk inkl. RegistrationNumber (Inventar) |
| ScoreFile | PDF-Stimme (Nextcloud-Pfad, Instrument, voice_label) |
| RehearsalPhase / RehearsalPiece | Probenphase + Repertoire |
| config / Log | App-Config + Audit |

Prefix: `archiv_` (bzw. `archiv-dev_`). Identity bleibt `meldeliste_User`.

Schema-Repair/`pruneObsoleteSchema` droppt nur `{dbprefix}*`-Tabellen (z. B. veraltetes `PrintJob`) — nie `meldeliste_*` oder `mit_*`.
