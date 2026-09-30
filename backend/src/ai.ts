import { config } from './config.js';
import { GeminiProvider } from './ai/gemini.js';
import { OpenAIProvider } from './ai/openai.js';
import type { AiResult, AnalyzeInput } from './ai/types.js';

export function analyzeFile(input: AnalyzeInput): Promise<AiResult> {
  const provider = config.AI_PROVIDER === 'gemini' ? new GeminiProvider() : new OpenAIProvider();
  return provider.analyze(input);
}