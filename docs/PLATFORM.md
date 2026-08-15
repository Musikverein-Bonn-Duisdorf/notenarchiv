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
- **Eine gemeinsame MySQL**; Domänen nur über Prefix getrennt. Keine separate User-DB.

## Constraints

- Keine Cross-App-PHP-Includes; Integration über gemeinsame MySQL-DB + SSO.
- White-Label: Vereinsname/URLs/Branding in Config, nicht hardcoded.
- Feature-Flags / Lizenz-Hook später andockbar (`modules.enabled` o. Ä.).
- Melde-Eingriffe minimal (SSO-Hook, UserVoice).

## Reihenfolge

1. **Phase 1 (aktuell):** Notenarchiv an Melde andocken (ARCHIV-4: Identity → SSO → Permissions → Security).
2. **Phase 2:** Mitgliederverwaltung als Mitgliedschafts-Hub (`mit_Person`, Fördernde) — blockiert Archiv nicht.

## Ops-Grenzen (ARCHIV-16)

Sibling-App: **eigene** Config- und Update-Fläche; **kein** Melde-Admin-Host.

| Thema | Regel |
|-------|--------|
| Config | `config-menu.php` schreibt nur `{dbprefix}config` (`archiv_config`). Melde-kollidierende Keys immer als `Archiv*` speichern (`ArchivColorNav`, `ArchivWebSiteName`, …); `loadconfig()` / `archivResolveConfigParam()` aliasieren logische Namen (`colorNav`, `WebSiteURL`) für UI-Code. Melde behält bare Keys in `meldeliste_config` — kein `melde_*`-Config-Prefix nötig. |
| Schema / Update | Eigene `updater.php` + `SchemaManager` nur für `archiv_*`; Version in `ArchivSchemaVersion` (nicht Melde-`SchemaVersion`). `pruneObsoleteSchema` droppt nur `{dbprefix}*`-Tabellen/Spalten/Config — nie `meldeliste_*` / `mit_*`. |
| Prefix-Schutz | `SchemaManager` / Config-Writes verweigern wenn `$dbprefix === $identityPrefix` |
| Config-Slim | Keine Melde-RSVP-Farben (`BtnYes`/`Maybe`/`No`) und keine ungenutzten Log-Chip-Farben in `{dbprefix}config` — Log-UI nutzt CSS-Chips |
| Backup | Eigene Backup-UI (`backup.php` / `cron.php`) — nur `{dbprefix}%` (`archiv_*`). **Nie** Melde-Identity. Kein Include/Iframe von Melde-`backup.php`. |
| Backup praktisch | ZIP (`manifest.json` + `database.sql`) via UI/CLI/`$backupToken`; Dateien unter `data/` weiterhin Hosting/rsync |
| JSON-API (ARCHIV-31) | `api/` + Bearer-`AppTokens` (Admin); Medien/Noten/Mappen — siehe [`docs/api.md`](api.md); MCP `mcp/archiv-api/` |

Siehe Melde Ownership-Matrix Abschnitte A/B (MELD-157).

## Session / Security (ARCHIV-8)

- `libs/sessionBootstrap.php` → `archivConfigureSession()` (Secure/HttpOnly/SameSite=Lax, analog Melde).
- SSO-Login wird geloggt (`Login via Melde-SSO ticket.`).
- Auth: `validateLink` / `validateUser` escapen Inputs und lehnen ungültige Magic-Links ab; `activeLink` via `random_bytes`.
- CSRF auf `config-menu.php` Speichern (`csrf_token` / `csrf_verify`).
- Webroot: `.htaccess` (`.git`/`scripts`/config), `data/.htaccess` (kein PHP), branded `errors/`.

## UI-Shell (Melde-Parität)

Kanonisch: Melde [`docs/UI-SHELL.md`](../meldeliste/docs/UI-SHELL.md) — Archiv hält nur den Pointer [`docs/UI-SHELL.md`](UI-SHELL.md).

- Layout: `body.app-layout` → `.app-titlebar` → `.app-shell` → `nav.app-nav` → `.app-main` (siehe Melde).
- Assets: `styles/custom.css`, `styles/w3-color-mvd.css`, lokale Font Awesome 6, `js/app-nav.js` / `toast.js` / `modal.js`; alle CSS/JS über `assetUrl()`.
- Listen-Chrome: `adminListPageBegin` / `adminListSearchField` / `adminListPageEnd` in `libs/uiShell.php`.
- Katalog-Listen: Sortier-Chips (`#listHeader.inv-sort-bar`, `js/sortList.js`, `mode: 'server'`) + Infinite-Scroll (`js/infiniteScroll.js`, `getList.php`, `libs/listChunk.php` — eigene `type`s: `compositions`, `collections`, `composers`, `publishers`, `log`).
- Modals: `#ajaxModalHost` + `deferPageModalHtml()` außerhalb von `.app-main`.
- Farben: Melde-Parität über `config/ConfigDefaults.php` (`color*`, Hex), `libs/colorschemes.php`, Hex-Picker in `config-menu.php`.
