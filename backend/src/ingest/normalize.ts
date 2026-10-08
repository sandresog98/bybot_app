import { PDFDocument } from 'pdf-lib';
import sharp from 'sharp';
import { config } from '../config.js';

export type NormalizedFile = {
  buffer: Buffer;
  mimeType: string;
  extension: string;
  converted: boolean;
};

export type NormalizeInput = {
  originalName: string;
  declaredMime: string;
  detectedMime: string | null;
  content: Buffer;
};

export class UnsupportedFileError extends Error {
  statusCode = 422;
  constructor(detail?: string) {
    super(
      `Formato no soportado${detail ? ` (${detail})` : ''}. Aceptamos PDF, PNG, JPG, WEBP, TIFF, BMP, GIF, AVIF, TXT, CSV y JSON.` +
        ' Los formatos de imagen no analizables se convierten automáticamente al cargarlos.',
    );
    this.name = 'UnsupportedFileError';
  }
}

const EXTENSION_MIME: Record<string, string> = {
  pdf: 'application/pdf',
  png: 'image/png',
  jpg: 'image/jpeg',
  jpeg: 'image/jpeg',
  webp: 'image/webp',
  tif: 'image/tiff',
  tiff: 'image/tiff',
  bmp: 'image/bmp',
  gif: 'image/gif',
  avif: 'image/avif',
  txt: 'text/plain',
  csv: 'text/csv',
  json: 'application/json',
};

const PASSTHROUGH = new Set([
  'application/pdf',
  'image/png',
  'image/jpeg',
  'image/webp',
  'text/plain',
  'text/csv',
  'application/json',
]);

const IMAGE_CONVERT = new Set(['image/tiff', 'image/bmp', 'image/x-ms-bmp', 'image/gif', 'image/avif']);

const MIME_EXTENSION: Record<string, string> = {
  'application/pdf': 'pdf',
  'image/png': 'png',
  'image/jpeg': 'jpg',
  'image/webp': 'webp',
  'text/csv': 'csv',
  'application/json': 'json',
  'text/plain': 'txt',
};

function extensionOf(name: string): string {
  const dot = name.lastIndexOf('.');
  return dot >= 0 ? name.slice(dot + 1).toLowerCase() : '';
}

function resolveMime(input: NormalizeInput): string | null {
  if (input.detectedMime) return input.detectedMime;
  const byExtension = EXTENSION_MIME[extensionOf(input.originalName)];
  if (byExtension) return byExtension;
  const declared = input.declaredMime.toLowerCase();
  if (declared && declared !== 'application/octet-stream') return declared;
  return null;
}

async function convertImage(content: Buffer): Promise<NormalizedFile> {
  let pages = 1;
  try {
    const meta = await sharp(content, { limitInputPixels: config.IMAGE_MAX_PIXELS }).metadata();
    pages = meta.pages ?? 1;
  } catch {
    throw new UnsupportedFileError('imagen ilegible');
  }

  if (pages <= 1) {
    const buffer = await sharp(content, { limitInputPixels: config.IMAGE_MAX_PIXELS, page: 0 }).png().toBuffer();
    return { buffer, mimeType: 'image/png', extension: 'png', converted: true };
  }

  const pdf = await PDFDocument.create();
  for (let index = 0; index < pages; index += 1) {
    const png = await sharp(content, { limitInputPixels: config.IMAGE_MAX_PIXELS, page: index }).png().toBuffer();
    const image = await pdf.embedPng(png);
    const page = pdf.addPage([image.width, image.height]);
    page.drawImage(image, { x: 0, y: 0, width: image.width, height: image.height });
  }
  return { buffer: Buffer.from(await pdf.save()), mimeType: 'application/pdf', extension: 'pdf', converted: true };
}

export async function normalizeUpload(input: NormalizeInput): Promise<NormalizedFile> {
  const mime = resolveMime(input);
  if (!mime) throw new UnsupportedFileError('tipo desconocido');

  if (PASSTHROUGH.has(mime)) {
    const extension = MIME_EXTENSION[mime] ?? extensionOf(input.originalName) ?? 'bin';
    return { buffer: input.content, mimeType: mime, extension: extension || 'bin', converted: false };
  }

  if (IMAGE_CONVERT.has(mime)) return convertImage(input.content);

  throw new UnsupportedFileError(mime);
}
