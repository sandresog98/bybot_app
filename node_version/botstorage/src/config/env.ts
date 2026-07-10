import dotenv from 'dotenv';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { z } from 'zod';

// Carga el .env raíz (node_version/.env) — el cwd al ejecutar tsx es el workspace.
const here = fileURLToPath(new URL('.', import.meta.url));
const projectRoot = resolve(here, '..', '..', '..');        // node_version/
dotenv.config({ path: resolve(projectRoot, '.env') });

const envSchema = z.object({
  BOTSTORAGE_HOST: z.string().default('127.0.0.1'),
  BOTSTORAGE_PORT: z.coerce.number().default(3002),
  BOTSTORAGE_LOG_LEVEL: z.string().default('debug'),
  BOTSTORAGE_INTERNAL_TOKEN: z.string().min(10),

  STORAGE_DRIVER: z.enum(['local', 'remote']).default('local'),
  STORAGE_LOCAL_DIR: z.string().default('uploads'),

  APP_ENV: z.enum(['development', 'production']).default('development'),
});

export const env = loadEnv();

function loadEnv() {
  const parsed = envSchema.safeParse(process.env);
  if (!parsed.success) {
    console.error('❌ [botstorage] Variables de entorno inválidas:');
    console.error(parsed.error.flatten().fieldErrors);
    process.exit(1);
  }
  return parsed.data;
}

export const isDev = env.APP_ENV !== 'production';