# Vereinsplattform — Modulgrenzen (Notenarchiv)

Verkaufbare / installierbare Module:

| Modul | Repo | DB-Prefix (Ziel) | Identity |
|-------|------|------------------|----------|
| Meldeliste | meldeliste | `meldeliste_` (später `melde_`) | Owner von `User` + `Permissions` + SSO-Issuer |
| Notenarchiv | notenarchiv | `archiv_` | liest Melde-`User` / Permissions; SSO-Redeem |
| Mitgliederverwaltung | mitgliederverwaltung | `mit_` | später Mitgliedschafts-Hub; liest Melde-Login |

**Kanonische** Plattform-Quelle (Ownership-Matrix A/B/C, Phasenreihenfolge): Meldeliste `docs/PLATFORM.md` (MELD-157). Diese Datei hält nur Archiv-spezifische Hinweise.

## Betriebsmodelle

- **Self-Host** und **gehostet**: gleiche Artefakte; Unterschied nur Betrieb/Config.
- Single-Tenant zuerst (eine Installation = ein Verein).
- **Eine gemeinsame MySQL**; Domänen nur über Prefix getrennt.

## Constraints

- Keine Cross-App-PHP-Includes; Integration über gemeinsame MySQL-DB + SSO.
- White-Label: Vereinsname/URLs/Branding in Config, nicht hardcoded.
- Feature-Flags / Lizenz-Hook später andockbar (`modules.enabled` o. Ä.).
- Melde-Eingriffe minimal (SSO-Hook, UserVoice).

## Ops-Grenzen (ARCHIV-16)

Sibling-App: **eigene** Config- und Update-Fläche; **kein** Melde-Admin-Host.

| Thema | Regel |
|-------|--------|
| Config | `config-menu.php` schreibt nur `{dbprefix}config` (`archiv_config`); UX-Muster wie Melde ok, keine Melde-Config |
| Schema / Update | `update.php` + `SchemaManager` nur für `archiv_*` |
| Backup | **Keine** Backup-UI im Archiv — kein Port von Melde-`backup.php` / `updater.php`, kein Include/Iframe |
| Backup praktisch | Hosting/mysqldump der gemeinsamen DB (+ optional Dateien unter `data/`) |

Siehe Melde Ownership-Matrix Abschnitte A/B (MELD-157).
