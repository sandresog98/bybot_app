import { beforeEach, describe, expect, it, vi } from 'vitest';
import { analyzeFile } from '../ai.js';
import { config } from '../config.js';

describe('analyzeFile (proveedor Gemini)', () => {
  beforeEach(() => {
    vi.unstubAllGlobals();
    config.AI_PROVIDER = 'gemini';
    config.GEMINI_API_KEY = 'test-key';
    config.GEMINI_MODEL = 'gemini-2.5-flash';
  });

  it('envía un PDF inline y parsea el JSON devuelto', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ candidates: [{ content: { parts: [{ text: '{"resumen":"ok"}' }] } }] }),
    });
    vi.stubGlobal('fetch', fetchMock);

    const result = await analyzeFile({ name: 'extracto.pdf', mimeType: 'application/pdf', content: Buffer.from('%PDF-1.4') });

    expect(result.result).toEqual({ resumen: 'ok' });
    expect(result.usage?.inputTokens).toBeUndefined();
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toContain(':generateContent');
    expect(url).toContain('test-key');
    const body = JSON.parse(init.body as string);
    expect(body.contents[0].parts[0].inlineData.mimeType).toBe('application/pdf');
    expect(body.generationConfig.responseMimeType).toBe('application/json');
  });

  it('envía contenido de texto como parte de texto', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ candidates: [{ content: { parts: [{ text: '{"ok":1}' }] } }] }),
    });
    vi.stubGlobal('fetch', fetchMock);
    await analyzeFile({ name: 'datos.csv', mimeType: 'text/csv', content: Buffer.from('a,b') });
    const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    const body = JSON.parse(init.body as string);
    expect(body.contents[0].parts[0].text).toContain('datos.csv');
  });

  it('falla cuando Gemini no está configurado', async () => {
    config.GEMINI_API_KEY = '';
    await expect(analyzeFile({ name: 'a.txt', mimeType: 'text/plain', content: Buffer.from('x') }))
      .rejects.toThrow('Gemini');
  });

  it('lanza error si el proveedor responde con un código no-2xx', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 400, json: async () => ({ error: { message: 'bad request' } }) }));
    await expect(analyzeFile({ name: 'a.txt', mimeType: 'text/plain', content: Buffer.from('x') }))
      .rejects.toThrow('El proveedor Gemini respondió 400.');
  });
});