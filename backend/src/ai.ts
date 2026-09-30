import { config } from './config.js';
import { GeminiProvider } from './ai/gemini.js';
import { OpenAIProvider } from './ai/openai.js';
import { selectPrompt } from './ai/prompts.js';
import type { AiResult, AnalyzeInput } from './ai/types.js';

export function analyzeFile(input: AnalyzeInput, prompt?: string): Promise<AiResult> {
  const provider = config.AI_PROVIDER === 'gemini' ? new GeminiProvider() : new OpenAIProvider();
  return provider.analyze(input, prompt ?? selectPrompt(input.tipo, input.entidadCodigo));
}