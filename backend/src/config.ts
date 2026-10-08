import 'dotenv/config';
import { z } from 'zod';

const configSchema = z.object({
  DATABASE_URL: z.string().url(),
  JWT_SECRET: z.string().min(32),
  BACKEND_PORT: z.coerce.number().int().positive().default(3001),
  FRONTEND_ORIGIN: z.string().url().default('http://localhost:5173'),
  UPLOAD_DIR: z.string().default('uploads'),
  UPLOAD_MAX_MB: z.coerce.number().positive().max(100).default(25),
  IMAGE_MAX_PIXELS: z.coerce.number().int().positive().default(60_000_000),
  AI_MAX_INPUT_CHARS: z.coerce.number().int().positive().max(500_000).default(100_000),
  AI_REQUEST_TIMEOUT_MS: z.coerce.number().int().positive().max(600_000).default(60_000),
  ANALYSIS_POLL_MS: z.coerce.number().int().positive().default(2_000),
  AI_API_URL: z.string().url().optional().or(z.literal('')),
  AI_API_KEY: z.string().optional().or(z.literal('')),
  AI_MODEL: z.string().optional().or(z.literal('')),
  AI_PROVIDER: z.enum(['openai', 'gemini']).default('openai'),
  GEMINI_API_KEY: z.string().optional().or(z.literal('')),
  GEMINI_MODEL: z.string().optional().default('gemini-2.5-flash'),
  GEMINI_TEMPERATURE: z.coerce.number().min(0).max(2).default(0.1),
  GEMINI_MAX_TOKENS: z.coerce.number().int().positive().default(26000),
  GEMINI_THINKING_BUDGET: z.coerce.number().int().default(0),
  SMLMV: z.coerce.number().positive().default(1_750_905),
  UMBRAL_MINIMA_SMLMV: z.coerce.number().positive().default(40),
});

export const config = configSchema.parse(process.env);
