import { spawn } from 'node:child_process';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { env } from '../config/env.js';

const here = fileURLToPath(new URL('.', import.meta.url));
// backend/src/core → subir 3 niveles a node_version/
const nodeRoot = resolve(here, '..', '..', '..');
const workerDir = resolve(nodeRoot, 'botworker');
const botsDir = resolve(nodeRoot, 'bots');

export interface RunResult {
  ok: boolean;
  stdout: string;
  stderr: string;
  exitCode: number | null;
}

/**
 * Ejecuta un script Python del botworker por child_process.
 * @param script nombre relativo a botworker, ej. "analizador.py"
 * @param args argumentos de línea de comandos
 * @param opts { cwdBots: boolean } para ejecutar en bots/ (ejecutar un bot con -m)
 */
export function runWorker(script: string, args: string[] = [], opts: { cwdBots?: boolean } = {}): Promise<RunResult> {
  const cwd = opts.cwdBots ? botsDir : workerDir;
  const scriptPath = opts.cwdBots ? script : resolve(workerDir, script);
  return new Promise((res) => {
    const child = spawn(env.WORKER_PY_BIN, [scriptPath, ...args], {
      cwd,
      timeout: env.WORKER_TIMEOUT_SEG * 1000,
    });
    let stdout = '';
    let stderr = '';
    child.stdout.on('data', (b) => (stdout += b.toString()));
    child.stderr.on('data', (b) => (stderr += b.toString()));
    child.on('error', (err) => {
      res({ ok: false, stdout, stderr: stderr + '\n' + String(err), exitCode: null });
    });
    child.on('close', (code) => {
      res({ ok: code === 0, stdout, stderr, exitCode: code });
    });
  });
}

export async function runWorkerJson(script: string, args: string[] = [], opts: { cwdBots?: boolean } = {}): Promise<{ ok: boolean; data: unknown; error?: string }> {
  const r = await runWorker(script, args, opts);
  if (!r.ok) return { ok: false, data: null, error: `Exit ${r.exitCode}: ${r.stderr}${r.stdout}` };
  try {
    return { ok: true, data: JSON.parse(r.stdout) };
  } catch (e) {
    return { ok: false, data: null, error: `Respuesta no JSON: ${(e as Error).message}; stdout=${r.stdout.slice(0, 500)}` };
  }
}