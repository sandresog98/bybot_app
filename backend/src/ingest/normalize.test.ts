import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import sharp from 'sharp';
import { describe, expect, it } from 'vitest';
import { normalizeUpload, UnsupportedFileError } from './normalize.js';

const exampleTiff = join(process.cwd(), '..', '..', 'archivos_ejemplo', 'archivos', 'somec', 'formulario.tif');

describe('normalizeUpload', () => {
  it('deja pasar un PDF sin conversión', async () => {
    const content = Buffer.from('%PDF-1.4 contenido de prueba');
    const out = await normalizeUpload({ originalName: 'doc.pdf', declaredMime: 'application/pdf', detectedMime: 'application/pdf', content });
    expect(out.converted).toBe(false);
    expect(out.mimeType).toBe('application/pdf');
    expect(out.buffer).toBe(content);
  });

  it('deja pasar texto y deduce la extensión', async () => {
    const out = await normalizeUpload({ originalName: 'notas.txt', declaredMime: 'text/plain', detectedMime: null, content: Buffer.from('hola') });
    expect(out.converted).toBe(false);
    expect(out.mimeType).toBe('text/plain');
    expect(out.extension).toBe('txt');
  });

  it('convierte un TIFF de una página a PNG', async () => {
    const tiff = await sharp({ create: { width: 20, height: 10, channels: 3, background: { r: 255, g: 255, b: 255 } } }).tiff().toBuffer();
    const out = await normalizeUpload({ originalName: 'escaneo.tif', declaredMime: 'image/tiff', detectedMime: 'image/tiff', content: tiff });
    expect(out.converted).toBe(true);
    expect(out.mimeType).toBe('image/png');
    expect(out.extension).toBe('png');
    expect((await sharp(out.buffer).metadata()).format).toBe('png');
  });

  it('rechaza formatos no soportados', async () => {
    await expect(
      normalizeUpload({ originalName: 'programa.exe', declaredMime: 'application/octet-stream', detectedMime: null, content: Buffer.from('MZ\x90\0') }),
    ).rejects.toBeInstanceOf(UnsupportedFileError);
  });

  it.skipIf(!existsSync(exampleTiff))('convierte un TIFF de varias páginas a un PDF', async () => {
    const content = readFileSync(exampleTiff);
    const out = await normalizeUpload({ originalName: 'formulario.tif', declaredMime: 'image/tiff', detectedMime: 'image/tiff', content });
    expect(out.converted).toBe(true);
    expect(out.mimeType).toBe('application/pdf');
    expect(out.extension).toBe('pdf');
    expect(out.buffer.subarray(0, 4).toString()).toBe('%PDF');
  });
});
