import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'node',
    pool: 'forks',
    env: {
      DATABASE_URL: 'mysql://root@localhost:3306/node2_test',
      JWT_SECRET: 'test-secret-for-node2-backend-32chars',
      UPLOAD_DIR: '.test-uploads',
      AI_API_URL: '',
      AI_API_KEY: '',
      AI_MODEL: '',
    },
  },
});