import { createHash, randomUUID } from 'node:crypto';
import { createWriteStream } from 'node:fs';
import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { basename, join } from 'node:path';
import { Transform, type Readable } from 'node:stream';
import { pipeline } from 'node:stream/promises';
import { config } from './config.js';

const root = join(process.cwd(), config.UPLOAD_DIR);
const safeName = (name: string) => basename(name).replace(/[^a-zA-Z0-9._-]/g, '_');
const keyFor = (originalName: string, extension?: string) => {
  const safe = safeName(originalName);
  const stem = safe.replace(/\.[^./\\]+$/, '') || 'archivo';
  return `${randomUUID()}-${extension ? `${stem}.${extension}` : safe}`;
};

export async function initializeStorage() {
  await mkdir(root, { recursive: true });
}

export async function saveUpload(originalName: string, source: Readable) {
  await initializeStorage();
  const storageKey = `${randomUUID()}-${basename(originalName).replace(/[^a-zA-Z0-9._-]/g, '_')}`;
  const hash = createHash('sha256'); let sizeBytes = 0; const header: Buffer[] = []; let headerLength = 0;
  const inspect = new Transform({ transform(chunk: Buffer, _encoding, callback) { const data = Buffer.from(chunk); hash.update(data); sizeBytes += data.length; if (headerLength < 4_100) { const portion = data.subarray(0, 4_100 - headerLength); header.push(portion); headerLength += portion.length; } callback(null, data); } });
  await pipeline(source, inspect, createWriteStream(join(root, storageKey), { flags: 'wx' }));
  return { storageKey, sha256: hash.digest('hex'), sizeBytes, header: Buffer.concat(header) };
}

export async function saveBuffer(originalName: string, buffer: Buffer, extension?: string) {
  await initializeStorage();
  const storageKey = keyFor(originalName, extension);
  await writeFile(join(root, storageKey), buffer, { flag: 'wx' });
  return { storageKey, sha256: createHash('sha256').update(buffer).digest('hex'), sizeBytes: buffer.length };
}

export function readStoredFile(key: string) { return readFile(join(root, basename(key))); }
export function removeStoredFile(key: string) { return rm(join(root, basename(key)), { force: true }); }
