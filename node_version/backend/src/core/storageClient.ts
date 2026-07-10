import { request } from 'undici';
import { env } from '../config/env.js';

const baseUrl = env.BACKEND_BOTSTORAGE_URL.replace(/\/$/, '');
const internalToken = env.BACKEND_BOTSTORAGE_TOKEN;

async function call(path: string, init: Parameters<typeof request>[1] = {}) {
  const headers = { ...((init.headers as Record<string, string>) ?? {}) };
  headers['X-Internal-Token'] = internalToken;
  return request(baseUrl + path, { ...init, headers });
}

/**
 * Sube bytes a botstorage. Devuelve la key de storage.
 */
export async function store(file: { filename: string; mimetype: string; size: number; data: Buffer }, storedName: string) {
  const r = await call('/internal/store', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/octet-stream',
      'X-File-Name': encodeURIComponent(storedName),
      'X-File-Mime': file.mimetype,
      'X-File-Size': String(file.size),
    },
    body: file.data,
  });
  if (r.statusCode !== 200) {
    const txt = await r.body.text();
    throw new Error(`botstorage store HTTP ${r.statusCode}: ${txt}`);
  }
  return (await r.body.json()) as { key: string; size: number; mime: string };
}

/**
 * Lee bytes (stream) desde botstorage.
 */
export async function read(key: string) {
  const r = await call('/internal/read/' + encodeURIComponent(key), { method: 'GET' });
  if (r.statusCode !== 200) {
    const txt = await r.body.text();
    throw new Error(`botstorage read HTTP ${r.statusCode}: ${txt}`);
  }
  return r.body;
}

export async function remove(key: string) {
  const r = await call('/internal/delete/' + encodeURIComponent(key), { method: 'DELETE' });
  if (r.statusCode !== 200 && r.statusCode !== 204) {
    const txt = await r.body.text();
    throw new Error(`botstorage delete HTTP ${r.statusCode}: ${txt}`);
  }
  return true;
}