#!/usr/bin/env node
/**
 * ARCHIV-31: thin MCP over Archiv REST API.
 * Env: ARCHIV_API_BASE (e.g. https://archiv.example/api), ARCHIV_API_TOKEN (Bearer).
 */
import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from '@modelcontextprotocol/sdk/types.js';
import { readFileSync } from 'node:fs';
import { Blob } from 'node:buffer';

const base = (process.env.ARCHIV_API_BASE || '').replace(/\/+$/, '');
const token = process.env.ARCHIV_API_TOKEN || '';

async function api(method, path, { json, form } = {}) {
  if (!base || !token) {
    throw new Error('Set ARCHIV_API_BASE and ARCHIV_API_TOKEN');
  }
  const url = base + path;
  const headers = { Authorization: 'Bearer ' + token };
  let body;
  if (form) {
    body = form;
  } else if (json !== undefined) {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(json);
  }
  const res = await fetch(url, { method, headers, body });
  const ct = res.headers.get('content-type') || '';
  if (ct.includes('application/json')) {
    const data = await res.json();
    if (!res.ok) {
      throw new Error(JSON.stringify(data));
    }
    return data;
  }
  if (!res.ok) {
    throw new Error('HTTP ' + res.status);
  }
  return { ok: true, status: res.status };
}

const tools = [
  {
    name: 'list_media_gaps',
    description: 'List composers without avatar and compositions without cover',
    inputSchema: {
      type: 'object',
      properties: {
        limit: { type: 'number' },
        scores: { type: 'boolean', description: 'Also list compositions without score files' },
      },
    },
  },
  {
    name: 'list_composers',
    description: 'Search/list composers',
    inputSchema: {
      type: 'object',
      properties: { q: { type: 'string' }, limit: { type: 'number' }, id: { type: 'number' } },
    },
  },
  {
    name: 'set_composer_avatar',
    description: 'Upload composer avatar from a local image path',
    inputSchema: {
      type: 'object',
      properties: {
        id: { type: 'number' },
        filePath: { type: 'string' },
      },
      required: ['id', 'filePath'],
    },
  },
  {
    name: 'list_compositions',
    description: 'Search/list compositions',
    inputSchema: {
      type: 'object',
      properties: { q: { type: 'string' }, limit: { type: 'number' }, id: { type: 'number' } },
    },
  },
  {
    name: 'set_composition_cover',
    description: 'Upload composition cover from a local image path',
    inputSchema: {
      type: 'object',
      properties: {
        id: { type: 'number' },
        filePath: { type: 'string' },
      },
      required: ['id', 'filePath'],
    },
  },
  {
    name: 'list_scores',
    description: 'List score files for a composition',
    inputSchema: {
      type: 'object',
      properties: { compositionId: { type: 'number' } },
      required: ['compositionId'],
    },
  },
  {
    name: 'upload_score',
    description: 'Upload a score file for a composition voice',
    inputSchema: {
      type: 'object',
      properties: {
        compositionId: { type: 'number' },
        instrumentId: { type: 'number' },
        voice: { type: 'string' },
        filePath: { type: 'string' },
      },
      required: ['compositionId', 'instrumentId', 'voice', 'filePath'],
    },
  },
  {
    name: 'list_collections',
    description: 'List collections (mappen)',
    inputSchema: {
      type: 'object',
      properties: { archived: { type: 'boolean' }, id: { type: 'number' } },
    },
  },
  {
    name: 'list_publishers',
    description: 'Search/list publishers (verlage)',
    inputSchema: {
      type: 'object',
      properties: { q: { type: 'string' }, limit: { type: 'number' }, id: { type: 'number' } },
    },
  },
  {
    name: 'upsert_publisher',
    description: 'Create or update a publisher (name, website, address)',
    inputSchema: {
      type: 'object',
      properties: {
        id: { type: 'number' },
        name: { type: 'string' },
        website: { type: 'string' },
        address: { type: 'string' },
      },
      required: ['name'],
    },
  },
  {
    name: 'set_publisher_avatar',
    description: 'Upload publisher logo/avatar from a local image path',
    inputSchema: {
      type: 'object',
      properties: {
        id: { type: 'number' },
        filePath: { type: 'string' },
      },
      required: ['id', 'filePath'],
    },
  },
  {
    name: 'set_collection_items',
    description: 'Replace collection item membership',
    inputSchema: {
      type: 'object',
      properties: {
        id: { type: 'number' },
        items: {
          type: 'array',
          items: {
            type: 'object',
            properties: { id: { type: 'number' }, number: { type: 'number' } },
          },
        },
      },
      required: ['id', 'items'],
    },
  },
];

const server = new Server(
  { name: 'archiv-api', version: '1.0.0' },
  { capabilities: { tools: {} } }
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({ tools }));

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const name = request.params.name;
  const args = request.params.arguments || {};
  try {
    let data;
    switch (name) {
      case 'list_media_gaps': {
        const qs = new URLSearchParams();
        if (args.limit) qs.set('limit', String(args.limit));
        if (args.scores) qs.set('scores', '1');
        data = await api('GET', '/gaps/media.php?' + qs.toString());
        break;
      }
      case 'list_composers': {
        const qs = new URLSearchParams();
        if (args.q) qs.set('q', args.q);
        if (args.limit) qs.set('limit', String(args.limit));
        if (args.id) qs.set('id', String(args.id));
        data = await api('GET', '/composers.php?' + qs.toString());
        break;
      }
      case 'set_composer_avatar': {
        const buf = readFileSync(args.filePath);
        const form = new FormData();
        form.set('id', String(args.id));
        form.set('avatar', new Blob([buf]), args.filePath.split('/').pop() || 'avatar.png');
        data = await api('POST', '/composers/avatar.php', { form });
        break;
      }
      case 'list_compositions': {
        const qs = new URLSearchParams();
        if (args.q) qs.set('q', args.q);
        if (args.limit) qs.set('limit', String(args.limit));
        if (args.id) qs.set('id', String(args.id));
        data = await api('GET', '/compositions.php?' + qs.toString());
        break;
      }
      case 'set_composition_cover': {
        const buf = readFileSync(args.filePath);
        const form = new FormData();
        form.set('id', String(args.id));
        form.set('cover', new Blob([buf]), args.filePath.split('/').pop() || 'cover.jpg');
        data = await api('POST', '/compositions/cover.php', { form });
        break;
      }
      case 'list_scores':
        data = await api(
          'GET',
          '/compositions/scores.php?composition=' + encodeURIComponent(String(args.compositionId))
        );
        break;
      case 'upload_score': {
        const buf = readFileSync(args.filePath);
        const form = new FormData();
        form.set('composition', String(args.compositionId));
        form.set('instrument', String(args.instrumentId));
        form.set('voice', String(args.voice));
        form.set('file', new Blob([buf]), args.filePath.split('/').pop() || 'part.pdf');
        data = await api('POST', '/compositions/scores.php', { form });
        break;
      }
      case 'list_collections': {
        const qs = new URLSearchParams();
        if (args.archived) qs.set('archived', '1');
        if (args.id) qs.set('id', String(args.id));
        data = await api('GET', '/collections.php?' + qs.toString());
        break;
      }
      case 'list_publishers': {
        const qs = new URLSearchParams();
        if (args.q) qs.set('q', args.q);
        if (args.limit) qs.set('limit', String(args.limit));
        if (args.id) qs.set('id', String(args.id));
        data = await api('GET', '/publishers.php?' + qs.toString());
        break;
      }
      case 'upsert_publisher':
        if (args.id) {
          data = await api('PATCH', '/publishers.php', {
            json: {
              id: args.id,
              name: args.name,
              website: args.website,
              address: args.address,
            },
          });
        } else {
          data = await api('POST', '/publishers.php', {
            json: {
              name: args.name,
              website: args.website,
              address: args.address,
            },
          });
        }
        break;
      case 'set_publisher_avatar': {
        const buf = readFileSync(args.filePath);
        const form = new FormData();
        form.set('id', String(args.id));
        form.set('avatar', new Blob([buf]), args.filePath.split('/').pop() || 'avatar.png');
        data = await api('POST', '/publishers/avatar.php', { form });
        break;
      }
      case 'set_collection_items':
        data = await api('PUT', '/collections/items.php', {
          json: { id: args.id, items: args.items },
        });
        break;
      default:
        throw new Error('Unknown tool: ' + name);
    }
    return {
      content: [{ type: 'text', text: JSON.stringify(data, null, 2) }],
    };
  } catch (err) {
    return {
      isError: true,
      content: [{ type: 'text', text: String(err && err.message ? err.message : err) }],
    };
  }
});

const transport = new StdioServerTransport();
await server.connect(transport);
