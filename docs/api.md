# Archiv JSON API (ARCHIV-31)

User-scoped Bearer tokens for **admins**. Endpoints live under `api/` (no URL rewrite). Same domain libs as the web UI.

## Auth

1. **Issue token** (password login, admin only):

```bash
curl -sS -X POST "$BASE/api/auth/token.php" \
  -H 'Content-Type: application/json' \
  -d '{"login":"admin","password":"…","device":"cursor"}'
```

Response: `{ "ok": true, "token": "…", "user": { "id", "name" } }`. Store the token once; only the SHA-256 hash is kept in `archiv_AppTokens`.

2. **CLI issue** (no password in HTTP):

```bash
php scripts/issueApiToken.php <login> [deviceLabel]
```

3. **Use**:

```bash
curl -sS "$BASE/api/me.php" -H "Authorization: Bearer $TOKEN"
# Fallback if the host strips Authorization (ARCHIV-35):
curl -sS "$BASE/api/me.php" -H "X-Archiv-Token: $TOKEN"
```

4. **Revoke**:

```bash
curl -sS -X POST "$BASE/api/auth/revoke.php" \
  -H "Authorization: Bearer $TOKEN"
```

Non-admins receive `403`. Invalid/revoked tokens → `401`.

## Endpoints

| Method | Path | Notes |
|--------|------|--------|
| POST | `api/auth/token.php` | login → token |
| POST | `api/auth/revoke.php` | Bearer revoke |
| GET | `api/me.php` | current admin user |
| GET | `api/gaps/media.php` | `?limit=&scores=1` |
| GET/POST/PATCH | `api/composers.php` | `firstName`, `lastName` |
| POST | `api/composers/avatar.php` | multipart `id`, `avatar` |
| GET/POST/PATCH | `api/compositions.php` | `title`, `composerId`, `publisherId`, `website` (Produktseite), … |
| POST | `api/compositions/cover.php` | multipart `id`, `cover` |
| GET | `api/compositions/scores.php` | `?composition=` |
| POST | `api/compositions/scores.php` | multipart `composition`, `instrument`, `voice`, `file` |
| GET | `api/compositions/scores/download.php` | `?id=` file stream |
| DELETE | `api/compositions/scores.php` | `?id=` |
| GET/POST/PATCH/DELETE | `api/publishers.php` | Verlage; JSON `name`, `website`, `address` |
| POST | `api/publishers/avatar.php` | multipart `id`, `avatar` |
| GET/POST/PATCH/DELETE | `api/collections.php` | CRUD Mappe |
| PUT | `api/collections/items.php` | JSON `{id, items:[{id,number}]}` |

All write endpoints require Bearer admin auth except `auth/token.php`.

## Cursor MCP

See `mcp/archiv-api/` — set `ARCHIV_API_BASE` (…`/api`) and `ARCHIV_API_TOKEN`. Skill: `.cursor/skills/archiv-api/`.

## Schema

Table `{dbprefix}AppTokens` (schema version **10**). Run updater/repair after deploy.
