# Identity-Vertrag (Notenarchiv)

## Tabellen

- **User:** `{identityPrefix}User` (Singular, nicht `Users`). Prod/Standard: `meldeliste_User`.
- **Instrument / Register:** `{identityPrefix}Instrument`, `{identityPrefix}Register` (Meldeliste-Stammdaten).
- **Permissions:** `{identityPrefix}Permissions` — Admin-Gates im Archiv (ARCHIV-6) lesen read-only.
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
- Optional: Config `urlMeldeliste` — Nav-Rücklink zur Melde (leer = ausgeblendet).

## Login / Admin

- Login, Session: `helpers.php` liest `{identityPrefix}User`.
- **Session-Admin** (`$_SESSION['admin']`) = `User.Admin` **oder** irgendein Melde-`perm_*` (personal + Gruppen-`PermissionSpec`, `IdentityPermissions`).
- **Feine Gates:** `requirePermission('perm_editConfig')` (Config/Update), `requirePermission('perm_showLog')` (Log). `User.Admin` bypass.
- Gruppen: vereinfachte MemberSpec-Auswertung (`users[]`, Rollen `users`/`members`/`musicians`/`nonmembers`) — kein vollständiges Melde-`AudienceSpec`.
- Legacy `{prefix}Users` wird nicht mehr beschrieben.

## SSO (ARCHIV-7)

1. Melde: `sso.php?redirect=<Archiv-URL>` stellt Einmal-Ticket aus (MELD-111).
2. Archiv redeemed `?sso=` in `common/header.php` **vor** dem Auth-Redirect (Deep-Links behalten den Token).
3. Zusätzlich: Redeem in `login.php` für direkten Login-Landing.
4. Ops Melde: `urlNotenarchiv` (+ ggf. `ssoRedirectAllowlist`) setzen.

Shared-Cookie auf Parent-Domain ist optional später — nicht Voraussetzung.
