# archiv_ Schema

| Tabelle | Zweck |
|---------|--------|
| Composer | Stammdaten Komponist |
| Publisher | Verlag |
| Collection / CollectionItem | Sammlungen |
| Composition | Werk inkl. RegistrationNumber (Inventar) |
| ScoreFile | PDF-Stimme (`FilePath` lokal/Upload oder Spiegel-Relativpfad, optional `NextcloudPath`, Instrument, `VoiceLabel`) |
| data/Notenarchiv/ | Lokaler PDF-Spiegel (gitignored); Details: [`SCORE-MIRROR.md`](SCORE-MIRROR.md) |
| PDF-Dateinamen | `{nn}_{Instrument}_{Stimme}.pdf` — siehe [`SCORE-MIRROR.md`](SCORE-MIRROR.md) |
| RehearsalPhase / RehearsalPiece | Probenphase + Repertoire |
| config / Log | App-Config + Audit |

Prefix: `archiv_` (bzw. `archiv-dev_`). Identity bleibt `meldeliste_User`.

Schema-Repair/`pruneObsoleteSchema` droppt nur `{dbprefix}*`-Tabellen (z. B. veraltetes `PrintJob`) — nie `meldeliste_*` oder `mit_*`.
