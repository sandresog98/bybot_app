import Fastify from 'fastify';
import sensible from '@fastify/sensible';
import cors from '@fastify/cors';
import rateLimit from '@fastify/rate-limit';
import cookie from '@fastify/cookie';
import multipart from '@fastify/multipart';
import { env } from './config/env.js';
import { logger } from './core/logger.js';
import authPlugin from './plugins/authPlugin.js';
import { healthRoutes } from './modules/health.js';
import { authRoutes } from './modules/auth/auth.routes.js';
import { dashboardRoutes } from './modules/dashboard/dashboard.routes.js';
import { procesosRoutes } from './modules/procesos/procesos.routes.js';
import { analisisRoutes } from './modules/analisis/analisis.routes.js';
import { promptsRoutes } from './modules/prompts/prompts.routes.js';
import { usuariosRoutes } from './modules/usuarios/usuarios.routes.js';
import { configuracionRoutes } from './modules/configuracion/configuracion.routes.js';

async function main() {
  const app = Fastify({
    logger: false, // usamos Pino propio
    bodyLimit: 50 * 1024 * 1024, // 50 MB para subida de archivos vía multipart
  });

  await app.register(sensible);
  await app.register(cookie, {});
  await app.register(rateLimit, { max: env.BACKEND_RATE_LIMIT, timeWindow: '1 minute' });
  await app.register(cors, {
    origin: true, // lock down in prod
    credentials: true,
  });
  await app.register(multipart, {
    limits: { fileSize: 50 * 1024 * 1024 },
  });

  await app.register(authPlugin);

  // Rutas
  await app.register(healthRoutes);                  // /health (sin auth, para probes)
  await app.register(async (api) => {
    await api.register(healthRoutes);                // /api/v1/health (también sin auth)
    await api.register(authRoutes, { prefix: '/auth' });
    await api.register(dashboardRoutes, { prefix: '/dashboard' });
    await api.register(procesosRoutes, { prefix: '/procesos' });
    await api.register(analisisRoutes, { prefix: '/analisis' });
    await api.register(promptsRoutes, { prefix: '/prompts' });
    await api.register(usuariosRoutes, { prefix: '/usuarios' });
    await api.register(configuracionRoutes, { prefix: '/configuracion' });
  }, { prefix: '/api/v1' });

  app.setErrorHandler((err, _req, rep) => {
    logger.error({ err: err.message, stack: err.stack }, 'unhandled error');
    const status = (err as { status?: number }).status ?? 500;
    rep.code(status).send({ success: false, message: err.message, data: null });
  });

  app.listen({ host: env.BACKEND_HOST, port: env.BACKEND_PORT }, (e) => {
    if (e) {
      logger.error(e, 'no se pudo iniciar');
      process.exit(1);
    }
    logger.info(`🖥  ByBot backend en http://${env.BACKEND_HOST}:${env.BACKEND_PORT} (env=${env.APP_ENV})`);
    logger.info(`   API: http://${env.BACKEND_HOST}:${env.BACKEND_PORT}/api/v1`);
  });
}

main().catch((e) => {
  logger.error(e, 'fatal');
  process.exit(1);
});