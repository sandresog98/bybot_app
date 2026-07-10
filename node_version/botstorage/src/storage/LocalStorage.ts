import { mkdir, readFile, writeFile, unlink, stat } from 'node:fs/promises';
import { dirname, join, resolve as resolvePath, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { env } from '../config/env.js';
import { logger } from '../core/logger.js';
import type { Storage, StoreResult } from './Storage.js';

const here = fileURLToPath(new URL('.', import.meta.url));
// botstorage/src/storage → subir 3 niveles a node_version/
const projectRoot = resolvePath(here, '..', '..', '..');
const baseDir = resolvePath(projectRoot, env.STORAGE_LOCAL_DIR || 'uploads');

logger.info({ baseDir, driver: env.STORAGE_DRIVER }, 'storage local inicializado');

function full(key: string): string {
  const k = key.replace(/^\//, '');
  const full = join(baseDir, k);
  // path traversal: la ruta resuelta debe empezar con baseDir
  if (!full.startsWith(baseDir + sep) && full !== baseDir) {
    throw new Error('Path traversal detectado en LocalStorage');
  }
  return full;
}

export class LocalStorage implements Storage {
  async store(data: Buffer, storedName: string, mime: string): Promise<StoreResult> {
    const key = storedName.replace(/^\//, '');
    const path = full(key);
    await mkdir(dirname(path), { recursive: true });
    await writeFile(path, data);
    return { key, size: data.length, mime };
  }

  async read(key: string) {
    const path = full(key);
    const data = await readFile(path);
    const s = await stat(path);
    const mime = await this.mime(key) ?? 'application/octet-stream';
    return { data, size: s.size, mime };
  }

  async delete(key: string) {
    const path = full(key);
    await unlink(path);
  }

  async exists(key: string) {
    try {
      const s = await stat(full(key));
      return s.isFile();
    } catch {
      return false;
    }
  }

  async size(key: string) {
    try {
      return (await stat(full(key))).size;
    } catch {
      return null;
    }
  }

  async mime(key: string) {
    const path = full(key);
    const u = path.toLowerCase();
    if (u.endsWith('.pdf')) return 'application/pdf';
    if (u.endsWith('.jpg') || u.endsWith('.jpeg')) return 'image/jpeg';
    if (u.endsWith('.png')) return 'image/png';
    if (u.endsWith('.html') || u.endsWith('.htm')) return 'text/html';
    if (u.endsWith('.xlsx')) return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    if (u.endsWith('.xls')) return 'application/vnd.ms-excel';
    if (u.endsWith('.txt')) return 'text/plain';
    try {
      await stat(path);
      return 'application/octet-stream';
    } catch {
      return null;
    }
  }
}