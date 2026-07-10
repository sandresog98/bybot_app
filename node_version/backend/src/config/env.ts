import dotenv from 'dotenv';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { z } from 'zod';

// Carga el .env raíz (node_version/.env) — al ejecutar tsx desde el workspace,
// el cwd es <workspace> y `dotenv/config` no lo encontraría.
const here = fileURLToPath(new URL('.', import.meta.url));
const projectRoot = resolve(here, '..', '..', '..');        // node_version/
dotenv.config({ path: resolve(projectRoot, '.env') });

const envSchema = z.object({
  APP_NAME: z.string().default('ByBot App'),
  APP_ENV: z.enum(['development', 'production']).default('development'),
  APP_DEBUG: z
    .string()
    .transform((v) => v === 'true')
    .default('true'),
  APP_TIMEZONE: z.string().default('America/Bogota'),

  DB_HOST: z.string().default('127.0.0.1'),
  DB_PORT: z.coerce.number().default(3306),
  DB_NAME: z.string().default('bybot_consolidado'),
  DB_USER: z.string().default('root'),
  DB_PASS: z.string().default(''),
  DATABASE_URL: z.string().optional(),

  BACKEND_HOST: z.string().default('127.0.0.1'),
  BACKEND_PORT: z.coerce.number().default(3001),
  BACKEND_BASE_URL: z.string().default('http://localhost:3001'),
  BACKEND_LOG_LEVEL: z.string().default('debug'),
  BACKEND_JWT_SECRET: z.string().min(16),
  BACKEND_JWT_ACCESS_TTL: z.string().default('15m'),
  BACKEND_JWT_REFRESH_TTL: z.string().default('7d'),
  BACKEND_RATE_LIMIT: z.coerce.number().default(100),
  BACKEND_BOTSTORAGE_URL: z.string().url(),
  BACKEND_BOTSTORAGE_TOKEN: z.string().min(10),

  WORKER_PY_BIN: z.string().default('python3'),
  WORKER_TIMEOUT_SEG: z.coerce.number().default(120),

  UPLOAD_MAX_SIZE_IMAGE: z.coerce.number().default(5242880),
  UPLOAD_MAX_SIZE_PDF: z.coerce.number().default(10485760),
  UPLOAD_MAX_SIZE_HTML: z.coerce.number().default(2097152),
  UPLOAD_MAX_SIZE_EXCEL: z.coerce.number().default(10485760),
  UPLOAD_ALLOWED_MIMES: z.string().default(''),

  GEMINI_API_KEY: z.string().default(''),
  GEMINI_MODEL: z.string().default('gemini-1.5-flash'),
});

export type Env = z.infer<typeof envSchema>;

function loadEnv(): Env {
  const parsed = envSchema.safeParse(process.env);
  if (!parsed.success) {
    console.error('❌ Variables de entorno inválidas:');
    console.error(parsed.error.flatten().fieldErrors);
    process.exit(1);
  }
  return parsed.data;
}

export const env = loadEnv();

export const isProd = env.APP_ENV === 'production';
export const isDev = !isProd;