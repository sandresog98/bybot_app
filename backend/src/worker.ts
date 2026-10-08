import { pathToFileURL } from 'node:url';
import { analyzeFile } from './ai.js';
import { estimateCostUsd } from './ai/cost.js';
import { consolidate } from './consolidate.js';
import { normalizeProcess } from './normalize.js';
import { Prisma } from '@prisma/client';
import { config } from './config.js';
import { prisma } from './db.js';
import { initializeStorage, readStoredFile } from './storage.js';

async function runConsolidation(analysisId: number, processId: number) {
  const fileAnalyses = await prisma.analysis.findMany({
    where: { processId, scope: 'file', status: 'completed' },
    include: { file: { select: { tipo: true, originalName: true } } },
    orderBy: { createdAt: 'asc' },
  });
  const latestByFile = new Map<number | null, (typeof fileAnalyses)[number]>();
  for (const item of fileAnalyses) latestByFile.set(item.fileId, item);
  if (latestByFile.size === 0) {
    await prisma.analysis.update({ where: { id: analysisId }, data: { status: 'failed', error: 'No hay análisis de archivo completados para consolidar.' } });
    return;
  }
  const result = consolidate(
    [...latestByFile.values()].map(item => ({
      tipo: item.file?.tipo ?? null,
      name: item.file?.originalName ?? `file#${item.fileId}`,
      result: item.validated ?? item.result,
    })),
  );
  await prisma.analysis.update({ where: { id: analysisId }, data: { status: 'completed', error: null, result: result as unknown as Prisma.InputJsonValue } });
  await prisma.process.update({ where: { id: processId }, data: { status: 'analyzed' } });
  try {
    await normalizeProcess(prisma, processId, analysisId, result);
  } catch (error) {
    console.error(`No se pudo normalizar el proceso ${processId}:`, error);
  }
}

export async function executeNext() {
  const candidate = await prisma.analysis.findFirst({ where: { status: 'queued', attempts: { lt: 3 } }, orderBy: { createdAt: 'asc' } });
  if (!candidate) return false;
  const claim = await prisma.analysis.updateMany({ where: { id: candidate.id, status: 'queued' }, data: { status: 'running', attempts: { increment: 1 } } });
  if (claim.count === 0) return true;
  const analysis = await prisma.analysis.findUniqueOrThrow({ where: { id: candidate.id }, include: { file: true, process: { include: { entidad: true } } } });
  if (analysis.scope === 'process') {
    try {
      await runConsolidation(analysis.id, analysis.processId);
    } catch {
      await prisma.analysis.update({ where: { id: analysis.id }, data: { status: 'failed', error: 'No se pudo consolidar el análisis del proceso.' } });
    }
    return true;
  }
  if (!analysis.file) { await prisma.analysis.update({ where: { id: analysis.id }, data: { status: 'failed', error: 'El archivo asociado ya no existe.' } }); return true; }
  try {
    const { result, usage } = await analyzeFile({
      name: analysis.file.originalName, mimeType: analysis.file.mimeType, tipo: analysis.file.tipo ?? undefined,
      entidadCodigo: analysis.process.entidad?.codigo,
      content: await readStoredFile(analysis.file.storageKey),
    });
    const storedResult = JSON.parse(JSON.stringify(result)) as Prisma.InputJsonValue;
    const model = usage?.model ?? analysis.model;
    const plain = result as Record<string, unknown>;
    const deudor = (plain.deudor && typeof plain.deudor === 'object' ? plain.deudor : {}) as Record<string, unknown>;
    const deudorNombre = typeof deudor.nombre_completo === 'string' ? deudor.nombre_completo.trim() : '';
    const deudorDocumento = typeof deudor.numero_documento === 'string' ? deudor.numero_documento.trim() : '';
    const processData: Prisma.ProcessUpdateInput = { status: 'analyzed' };
    if (deudorNombre && !analysis.process.deudorNombre) processData.deudorNombre = deudorNombre;
    if (deudorDocumento && !analysis.process.deudorDocumento) processData.deudorDocumento = deudorDocumento;
    await prisma.$transaction([prisma.analysis.update({
      where: { id: analysis.id },
      data: {
        status: 'completed', result: storedResult, error: null,
        model, inputTokens: usage?.inputTokens ?? null, outputTokens: usage?.outputTokens ?? null,
        costUsd: estimateCostUsd(model ?? undefined, usage?.inputTokens, usage?.outputTokens),
      },
    }), prisma.process.update({ where: { id: analysis.processId }, data: processData })]);
  } catch (error) {
    const message = error instanceof Error && error.name === 'AbortError' ? 'El proveedor IA excedió el tiempo de espera.' : 'No se pudo completar el análisis. Reintenta más tarde.';
    const attempts = analysis.attempts;
    await prisma.analysis.update({ where: { id: analysis.id }, data: { status: attempts >= analysis.maxAttempts ? 'failed' : 'queued', error: message } });
  }
  return true;
}

async function main() {
  await initializeStorage();
  await prisma.analysis.updateMany({ where: { status: 'running' }, data: { status: 'queued' } });
  for (;;) { const processed = await executeNext(); if (!processed) await new Promise(resolve => setTimeout(resolve, config.ANALYSIS_POLL_MS)); }
}

const isMain = process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href;
if (isMain) {
  main().catch(error => { console.error(error); process.exit(1); });
}