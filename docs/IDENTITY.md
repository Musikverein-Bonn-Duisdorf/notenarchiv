# Identity-Vertrag (Notenarchiv)

## Tabellen

- **User:** `{identityPrefix}User` (Singular, nicht `Users`). Prod/Standard: `meldeliste_User`.
- **Instrument / Register:** `{identityPrefix}Instrument`, `{identityPrefix}Register` (Meldeliste-Stammdaten).
- **Melde-Permissions:** `{identityPrefix}Permissions` — Plattform-Zugang read-only (`perm_accessNotenarchiv`, `perm_accessMitgliederverwaltung`, inkl. Gruppen-`PermissionSpec`).
- **Archiv-Permissions:** `{dbprefix}Permissions` — lokale Rechte `perm_read`, `perm_write`, `perm_showLog`, `perm_editPermissions` (ARCHIV-42/46).
- **SsoTicket:** `{identityPrefix}SsoTicket` — Melde stellt aus, Archiv redeemed nur.
- **Archiv-Daten:** `{dbprefix}…` mit `$dbprefix = archiv_` (Composition, ScoreFile, …).

Mitgliedschaftsstamm (Anschrift/Bank, Fördernde) liegt **nicht** hier — später MIT (`mit_Person`). Archiv schreibt nur `archiv_*`.

## Konfiguration

In `common/config.php`:

```php
$dbprefix = "archiv_";
$identityPrefix = "meldeliste_";
```

- `$identityPrefix` — getrennt von `$dbprefix`; Fallback: `$GLOBALS['identityPrefix'] ?? $GLOBALS['dbprefix']`.
- Helper: `identityPrefix()` in `libs/helpers.php`.
- Optional: Config `urlMeldeliste` — Nav-Rücklink zur Melde.
- Optional: Config `urlMitgliederverwaltung` — Nav-Link (sichtbar nur mit Melde-`perm_accessMitgliederverwaltung`; SSO über Melde wenn `urlMeldeliste` gesetzt).

## Login / Rechte

- Login, Session: `helpers.php` liest `{identityPrefix}User`.
- **Login-Voraussetzung:** Melde-`perm_accessNotenarchiv` (personal + Gruppe). Melde-`User.Admin` allein reicht nicht.
- **Erster Login:** wenn `{dbprefix}Permissions` leer ist, erhält dieser Nutzer alle lokalen Rechte.
- **Session-Admin** (`$_SESSION['admin']`) = lokales `perm_write`.
- **Feine Gates:** `perm_read` (Katalog), `perm_write` (Schreiben + Config/Backup/API), `perm_showLog` (Log), `perm_editPermissions` (Rechte-Matrix).
- `perm_read` gilt auch bei `perm_write`.
- Upgrade: bestehende `perm_write`-Zeilen erhalten einmalig `perm_showLog` (Schema 15 / `ensureTableExists`).
## SSO (ARCHIV-7)

1. Melde: `sso.php?redirect=<Archiv-URL>` stellt Einmal-Ticket aus (MELD-111); Melde prüft `perm_accessNotenarchiv`.
2. Archiv redeemed `?sso=` in `common/header.php` **vor** dem Auth-Redirect und prüft Zugang erneut.
3. Zusätzlich: Redeem in `login.php` für direkten Login-Landing.
4. Ops Melde: `urlNotenarchiv` (+ ggf. `ssoRedirectAllowlist`) setzen.

Shared-Cookie auf Parent-Domain ist optional später — nicht Voraussetzung.
