import { PrismaClient } from '@prisma/client';
import { env } from '../config/env.js';

export const prisma = new PrismaClient({
  log: env.APP_DEBUG ? ['query', 'warn', 'error'] : ['warn', 'error'],
});