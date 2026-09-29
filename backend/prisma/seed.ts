import 'dotenv/config';
import bcrypt from 'bcryptjs';
import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();
const username = process.env.ADMIN_USERNAME ?? 'admin';
const password = process.env.ADMIN_PASSWORD;
if (!password) throw new Error('Define ADMIN_PASSWORD antes de ejecutar db:seed.');

await prisma.user.upsert({
  where: { username },
  update: {},
  create: { username, passwordHash: await bcrypt.hash(password, 12), name: 'Administrador', role: 'admin' },
});
await prisma.$disconnect();
