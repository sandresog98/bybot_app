import { pathToFileURL } from 'node:url';
import { analyzeFile } from './ai.js';
import { Prisma } from '@prisma/client';
import { config } from './config.js';
import { prisma } from './db.js';
import { initializeStorage, readStoredFile } from './storage.js';

export async function executeNext() {
  const candidate = await prisma.analysis.findFirst({ where: { status: 'queued', attempts: { lt: 3 } }, orderBy: { createdAt: 'asc' } });
  if (!candidate) return false;
  const claim = await prisma.analysis.updateMany({ where: { id: candidate.id, status: 'queued' }, data: { status: 'running', attempts: { increment: 1 } } });
  if (claim.count === 0) return true;
  const analysis = await prisma.analysis.findUniqueOrThrow({ where: { id: candidate.id }, include: { file: true } });
  if (!analysis.file) { await prisma.analysis.update({ where: { id: analysis.id }, data: { status: 'failed', error: 'El archivo asociado ya no existe.' } }); return true; }
  try {
    const result = await analyzeFile({ name: analysis.file.originalName, mimeType: analysis.file.mimeType, content: await readStoredFile(analysis.file.storageKey) });
    const storedResult = JSON.parse(JSON.stringify(result)) as Prisma.InputJsonValue;
    await prisma.$transaction([prisma.analysis.update({ where: { id: analysis.id }, data: { status: 'completed', result: storedResult, error: null } }), prisma.process.update({ where: { id: analysis.processId }, data: { status: 'analyzed' } })]);
  } catch (error) {
    const message = error instanceof Error && error.name === 'AbortError' ? 'El proveedor IA excedió el tiempo de espera.' : 'No se pudo completar el análisis. Reintenta más tarde.';
    const attempts = analysis.attempts;
    await prisma.analysis.update({ where: { id: analysis.id }, data: { status: attempts >= analysis.maxAttempts ? 'failed' : 'queued', error: message } });
  }
  return true;
}

async function main() {
  await initializeStorage();
  for (;;) { const processed = await executeNext(); if (!processed) await new Promise(resolve => setTimeout(resolve, config.ANALYSIS_POLL_MS)); }
}

const isMain = process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href;
if (isMain) {
  main().catch(error => { console.error(error); process.exit(1); });
}