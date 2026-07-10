import Fastify from 'fastify';
import sensible from '@fastify/sensible';
import { env } from './config/env.js';
import { logger } from './core/logger.js';
import internalAuthPlugin from './plugins/internalAuth.js';
import { internalRoutes } from './routes/internal.js';

async function main() {
  const app = Fastify({ logger: false, bodyLimit: 60 * 1024 * 1024 });

  await app.register(sensible);
  await app.register(internalAuthPlugin);

  app.get('/health', async () => ({
    service: 'bybot-botstorage',
    driver: env.STORAGE_DRIVER,
    dir: env.STORAGE_LOCAL_DIR,
    ts: new Date().toISOString(),
  }));

  await app.register(internalRoutes, { prefix: '/internal' });

  app.setErrorHandler((err, _req, rep) => {
    logger.error({ err: err.message, stack: err.stack }, 'unhandled error');
    const status = (err as { status?: number }).status ?? 500;
    rep.code(status).send({ success: false, message: err.message });
  });

  app.listen({ host: env.BOTSTORAGE_HOST, port: env.BOTSTORAGE_PORT }, (e) => {
    if (e) {
      logger.error(e, 'no se pudo iniciar');
      process.exit(1);
    }
    logger.info(`📦  ByBot botstorage en http://${env.BOTSTORAGE_HOST}:${env.BOTSTORAGE_PORT} (driver=${env.STORAGE_DRIVER})`);
  });
}

main().catch((e) => {
  logger.error(e, 'fatal');
  process.exit(1);
});