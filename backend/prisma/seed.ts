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

const entidades = [
  { codigo: 'condiar', nombre: 'CONDIAR' },
  { codigo: 'crearcoop', nombre: 'Cooperativa CREAR LTDA' },
  { codigo: 'somec', nombre: 'SOCIAL Y DE MERCADEO CENTRAL LTDA (SOMEC)' },
];
for (const e of entidades) {
  await prisma.entidad.upsert({ where: { codigo: e.codigo }, update: {}, create: e });
}

await prisma.$disconnect();
console.log(`Seed OK: admin y ${entidades.length} entidades.`);