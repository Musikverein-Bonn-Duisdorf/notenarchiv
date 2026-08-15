# Archiv MCP (ARCHIV-31)

Thin MCP server over the Notenarchiv JSON API (`api/`).

## Setup

```bash
cd mcp/archiv-api
npm install
```

Cursor MCP config (example):

```json
{
  "mcpServers": {
    "archiv-api": {
      "command": "node",
      "args": ["/absolute/path/to/notenarchiv/mcp/archiv-api/src/index.js"],
      "env": {
        "ARCHIV_API_BASE": "https://your-host/api",
        "ARCHIV_API_TOKEN": "paste-token-once"
      }
    }
  }
}
```

Do not commit tokens. Prefer issuing tokens via `scripts/issueApiToken.php` or `POST api/auth/token.php`.

## Tools

- `list_media_gaps` — composers without avatar / compositions without cover
- `list_composers` / `set_composer_avatar`
- `list_compositions` / `set_composition_cover`
- `list_scores` / `upload_score`
- `list_collections` / `set_collection_items`

See `docs/api.md` for the full REST surface.
