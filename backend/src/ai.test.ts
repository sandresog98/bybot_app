import { beforeEach, describe, expect, it, vi } from 'vitest';
import { analyzeFile } from './ai.js';
import { config } from './config.js';

function setConfigured() {
  config.AI_API_URL = 'https://ai.invalid/v1/chat/completions';
  config.AI_API_KEY = 'test-key';
  config.AI_MODEL = 'test-model';
}
function setUnconfigured() {
  config.AI_API_URL = '';
  config.AI_API_KEY = '';
  config.AI_MODEL = '';
}

describe('analyzeFile', () => {
  beforeEach(() => {
    vi.unstubAllGlobals();
    config.AI_PROVIDER = 'openai';
  });

  it('falla cuando el análisis IA no está configurado', async () => {
    setUnconfigured();
    await expect(analyzeFile({ name: 'a.txt', mimeType: 'text/plain', content: Buffer.from('hola') }))
      .rejects.toThrow('no está configurado');
  });

  it('rechaza archivos que no son texto o JSON', async () => {
    setConfigured();
    await expect(analyzeFile({ name: 'a.pdf', mimeType: 'application/pdf', content: Buffer.from('%PDF') }))
      .rejects.toThrow('texto o JSON');
  });

  it('consume la API y parsea el JSON devuelto', async () => {
    setConfigured();
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ choices: [{ message: { content: '{"resumen":"ok","riesgos":[]}' } }] }),
    });
    vi.stubGlobal('fetch', fetchMock);

    const result = await analyzeFile({ name: 'datos.csv', mimeType: 'text/csv', content: Buffer.from('fila1,fila2') });

    expect(result).toEqual({ resumen: 'ok', riesgos: [] });
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe(config.AI_API_URL);
    const body = JSON.parse(init.body as string);
    expect(body.model).toBe('test-model');
    expect(body.messages[1].content).toContain('datos.csv');
  });

  it('lanza error si el proveedor responde con un código distinto de 2xx', async () => {
    setConfigured();
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 500 }));
    await expect(analyzeFile({ name: 'a.txt', mimeType: 'text/plain', content: Buffer.from('x') }))
      .rejects.toThrow('El proveedor IA respondió 500.');
  });
});