import { rm } from 'node:fs/promises';
import { Readable } from 'node:stream';
import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { initializeStorage, readStoredFile, removeStoredFile, saveUpload } from './storage.js';
import { config } from './config.js';
import { join } from 'node:path';

const root = join(process.cwd(), config.UPLOAD_DIR);

describe('storage', () => {
  beforeAll(async () => { await initializeStorage(); });
  afterAll(async () => { await rm(root, { recursive: true, force: true }); });

  it('guarda por streaming calculando hash y tamaño, y permite leer/eliminar', async () => {
    const source = Readable.from([Buffer.from('hola mundo '), Buffer.from('final')]);
    const saved = await saveUpload('documento.txt', source);

    expect(saved.sha256).toHaveLength(64);
    expect(saved.sizeBytes).toBe(16);
    expect(saved.storageKey).toContain('documento.txt');

    const content = await readStoredFile(saved.storageKey);
    expect(content.toString()).toBe('hola mundo final');

    await removeStoredFile(saved.storageKey);
    await expect(readStoredFile(saved.storageKey)).rejects.toThrow();
  });

  it('sanea nombres y neutraliza intentos de path traversal', async () => {
    const saved = await saveUpload('../.ruta/archivo?.txt', Readable.from([Buffer.from('x')]));
    expect(saved.storageKey).not.toContain('..');
    expect(saved.storageKey).not.toContain('/');
    await removeStoredFile(saved.storageKey);

    await expect(readStoredFile('/etc/passwd')).rejects.toThrow();
  });
});