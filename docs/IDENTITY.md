# Identity-Vertrag (Notenarchiv)

## Tabellen

- **User:** `{identityPrefix}User` (Singular, nicht `Users`). Prod/Standard: `meldeliste_User`.
- **Instrument / Register:** `{identityPrefix}Instrument`, `{identityPrefix}Register` (Meldeliste-Stammdaten).
- **Permissions:** `{identityPrefix}Permissions` (optional für Rechte-Checks).
- **Archiv-Daten:** `{dbprefix}…` mit `$dbprefix = archiv_` (Composition, ScoreFile, …).

## Konfiguration

In `common/config.php`:

```php
$dbprefix = "archiv_";
$identityPrefix = "meldeliste_";
```

- `$identityPrefix` — getrennt von `$dbprefix`; Fallback in Code: `$GLOBALS['identityPrefix'] ?? $GLOBALS['dbprefix']`.
- Helper: `identityPrefix()` in `libs/helpers.php`.

## Code

- Login, Session, Admin: `helpers.php` / `user.php` lesen `{identityPrefix}User`.
- Legacy `{prefix}Users` wird nicht mehr beschrieben; Migration auf Singular-Tabelle.
- Admin-Feld (`Admin`) bleibt unverändert (`Admin = 1`).

## SSO (optional)

Einmal-Ticket von Meldeliste (`sso.php`) oder Shared-Cookie (Parent-Domain) — siehe `docs/PLATFORM.md`.
