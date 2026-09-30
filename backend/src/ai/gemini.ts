import { config } from '../config.js';
import { SYSTEM_PROMPT, type AIProvider, type AnalyzeInput } from './types.js';

const API_BASE = 'https://generativelanguage.googleapis.com/v1beta';

type GeminiPart = { text?: string; inlineData?: { mimeType: string; data: string } };

export class GeminiProvider implements AIProvider {
  private isBinary(mimeType: string): boolean {
    return mimeType === 'application/pdf' || mimeType.startsWith('image/');
  }

  async analyze(input: AnalyzeInput): Promise<object> {
    if (!config.GEMINI_API_KEY || !config.GEMINI_MODEL) {
      throw new Error('El análisis IA con Gemini no está configurado. Define GEMINI_API_KEY y GEMINI_MODEL.');
    }

    const parts: GeminiPart[] = this.isBinary(input.mimeType)
      ? [
          { inlineData: { mimeType: input.mimeType, data: input.content.toString('base64') } },
          { text: `Analiza el archivo adjunto "${input.name}" y responde únicamente JSON.` },
        ]
      : [{ text: `<archivo nombre="${input.name}">\n${input.content.toString('utf8').slice(0, config.AI_MAX_INPUT_CHARS)}\n</archivo>` }];

    const generationConfig: Record<string, unknown> = {
      temperature: config.GEMINI_TEMPERATURE,
      maxOutputTokens: config.GEMINI_MAX_TOKENS,
      responseMimeType: 'application/json',
    };
    const budget = config.GEMINI_THINKING_BUDGET;
    if (budget > 0) generationConfig.thinkingConfig = { thinkingBudget: budget };

    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), config.AI_REQUEST_TIMEOUT_MS);
    let response: Response;
    try {
      response = await fetch(`${API_BASE}/models/${config.GEMINI_MODEL}:generateContent?key=${config.GEMINI_API_KEY}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          systemInstruction: { parts: [{ text: SYSTEM_PROMPT }] },
          contents: [{ role: 'user', parts }],
          generationConfig,
        }),
        signal: controller.signal,
      });
    } finally { clearTimeout(timeout); }

    if (!response.ok) {
      let detail = '';
      try { const err = await response.json() as { error?: { message?: string } }; detail = err.error?.message ?? ''; } catch { /* ignore */ }
      throw new Error(`El proveedor Gemini respondió ${response.status}.${detail ? ` ${detail}` : ''}`);
    }

    const payload = await response.json() as { candidates?: Array<{ content?: { parts?: Array<{ text?: string }> } }> };
    const output = payload.candidates?.[0]?.content?.parts?.map(p => p.text ?? '').join('') ?? '';
    if (!output) throw new Error('El proveedor Gemini no entregó contenido.');
    try { return JSON.parse(output) as object; } catch { return { respuesta: output }; }
  }
}