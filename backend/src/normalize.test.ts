import type { PrismaClient } from '@prisma/client';
import { describe, expect, it, vi } from 'vitest';
import { normalizeProcess, toNumber } from './normalize.js';

function fakePrisma() {
  const calls: { credito: Record<string, unknown> | null; movimiento: unknown[]; cuota: unknown[]; parte: Array<Record<string, unknown>>; campo: Array<Record<string, unknown>> } = {
    credito: null, movimiento: [], cuota: [], parte: [], campo: [],
  };
  const tx = {
    movimiento: { deleteMany: vi.fn(), createMany: vi.fn(async ({ data }: { data: unknown[] }) => { calls.movimiento = data; }) },
    cuotaAmortizacion: { deleteMany: vi.fn(), createMany: vi.fn(async ({ data }: { data: unknown[] }) => { calls.cuota = data; }) },
    extraccionCampo: { deleteMany: vi.fn(), createMany: vi.fn(async ({ data: data_ }: { data: Array<Record<string, unknown>> }) => { calls.campo = data_; }) },
    parte: { deleteMany: vi.fn(), createMany: vi.fn(async ({ data }: { data: Array<Record<string, unknown>> }) => { calls.parte = data; }) },
    credito: { deleteMany: vi.fn(), create: vi.fn(async ({ data }: { data: Record<string, unknown> }) => { calls.credito = data; return { id: 99 }; }) },
  };
  const prisma = { $transaction: vi.fn(async (fn: (t: typeof tx) => Promise<unknown>) => fn(tx)) };
  return { prisma: prisma as unknown as PrismaClient, calls };
}

describe('toNumber', () => {
  it('parsea formatos numéricos con separadores', () => {
    expect(toNumber('21.343.017')).toBe(21343017);
    expect(toNumber('4.745.186,50')).toBe(4745186.5);
    expect(toNumber('14.75')).toBe(14.75);
    expect(toNumber(20.5)).toBe(20.5);
    expect(toNumber('')).toBeNull();
    expect(toNumber('N/A')).toBeNull();
  });
});

describe('normalizeProcess', () => {
  it('escribe partes, crédito, movimientos, cuotas y campos', async () => {
    const { prisma, calls } = fakePrisma();
    await normalizeProcess(prisma, 7, 12, {
      estado_cuenta: { saldo_capital: 1000, total_deuda: 1200, fecha_corte: '2024-09-17' },
      deudor: { nombre_completo: 'Ana Pérez', numero_documento: '123' },
      codeudor: { existe: false },
      movimientos: [{ fecha: '2024-01-01', total: 10 }],
      amortizacion: { cuotas: [{ numero: 1, cuota: 100 }], tasa_ea: 20.5 },
      _consolidado: { fuentes: [] },
    });

    expect(calls.credito).toMatchObject({ processId: 7, saldoCapital: 1000, totalDeuda: 1200, tasaEa: 20.5 });
    expect(calls.movimiento).toHaveLength(1);
    expect(calls.cuota).toHaveLength(1);
    const roles = calls.parte.map(p => p.rol);
    expect(roles).toContain('deudor');
    expect(roles).not.toContain('codeudor');
    expect(calls.campo.some(c => String(c.ruta).startsWith('_consolidado'))).toBe(false);
    expect(calls.campo.some(c => c.ruta === 'estado_cuenta.saldo_capital' && c.valorNumero === 1000)).toBe(true);
  });
});
