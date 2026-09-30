type Price = { input: number; output: number };

const PRICES: Record<string, Price> = {
  'gemini-2.5-flash': { input: 0.30, output: 2.50 },
  'gemini-2.0-flash': { input: 0.10, output: 0.40 },
  'gpt-4o-mini': { input: 0.15, output: 0.60 },
};

const DEFAULT_PRICE: Price = { input: 1.00, output: 2.00 };

export function estimateCostUsd(model: string | undefined, inputTokens?: number, outputTokens?: number): number | null {
  if (inputTokens === undefined && outputTokens === undefined) return null;
  const price = (model && PRICES[model]) || DEFAULT_PRICE;
  const cost = ((inputTokens ?? 0) / 1_000_000) * price.input + ((outputTokens ?? 0) / 1_000_000) * price.output;
  return Number(cost.toFixed(6));
}