import { config } from '../config.js';
import { SYSTEM_PROMPT, type AIProvider, type AnalyzeInput } from './types.js';

export class OpenAIProvider implements AIProvider {
  async analyze(input: AnalyzeInput): Promise<object> {
    if (!config.AI_API_URL || !config.AI_API_KEY || !config.AI_MODEL) {
      throw new Error('El análisis IA no está configurado. Define AI_API_URL, AI_API_KEY y AI_MODEL.');
    }
    const textMime = input.mimeType.startsWith('text/') || input.mimeType === 'application/json';
    if (!textMime) throw new Error('Esta implementación inicial analiza archivos de texto o JSON. Agrega un extractor específico para PDF e imágenes.');
    const content = input.content.toString('utf8').slice(0, config.AI_MAX_INPUT_CHARS);
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), config.AI_REQUEST_TIMEOUT_MS);
    let response: Response;
    try {
      response = await fetch(config.AI_API_URL, {
        method: 'POST',
        headers: { Authorization: `Bearer ${config.AI_API_KEY}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({
          model: config.AI_MODEL,
          messages: [
            { role: 'system', content: SYSTEM_PROMPT },
            { role: 'user', content: `<archivo nombre="${input.name}">\n${content}\n</archivo>` },
          ],
          response_format: { type: 'json_object' },
        }),
        signal: controller.signal,
      });
    } finally { clearTimeout(timeout); }
    if (!response.ok) throw new Error(`El proveedor IA respondió ${response.status}.`);
    const payload = await response.json() as { choices?: Array<{ message?: { content?: string } }> };
    const output = payload.choices?.[0]?.message?.content;
    if (!output) throw new Error('El proveedor IA no entregó contenido.');
    try { return JSON.parse(output) as object; } catch { return { respuesta: output }; }
  }
}