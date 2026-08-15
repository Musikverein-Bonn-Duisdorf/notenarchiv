---
name: archiv-api
description: Use when working with Notenarchiv remote media, scores, collections, or missing thumbnails via the ARCHIV JSON API / MCP. Prefer MCP tools over inventing file paths.
---

# Archiv API (Cursor)

## When to use

- Fill missing composer avatars or composition covers (gap report).
- Upload/download score files for a composition voice.
- List or update collection (Mappe) membership.

## How

1. Configure the `archiv-api` MCP server (`mcp/archiv-api`) with `ARCHIV_API_BASE` and `ARCHIV_API_TOKEN`.
2. Call MCP tools (`list_media_gaps`, `set_composer_avatar`, …) — they map 1:1 to `api/*.php`.
3. Without MCP: use `docs/api.md` and `curl` with `Authorization: Bearer …`.

## Rules

- Admin Bearer tokens only; never commit tokens or put them in chat logs if avoidable.
- Do not invent a second persistence layer — API uses the same domain libs as the web UI.
- Full UI CRUD (config, all master-data fields, log UI) is out of scope; open a follow-up ticket.
