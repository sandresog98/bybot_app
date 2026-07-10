import pino from 'pino';
import { env, isDev } from '../config/env.js';

const level = (
  {
    trace: 'trace',
    debug: 'debug',
    info: 'info',
    warn: 'warn',
    error: 'error',
    fatal: 'fatal',
  } as const
)[env.BACKEND_LOG_LEVEL as 'trace'] ?? 'info';

export const logger = pino({
  level,
  transport: isDev
    ? { target: 'pino-pretty', options: { colorize: true, translateTime: 'HH:MM:ss' } }
    : undefined,
});