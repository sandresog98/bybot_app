import { describe, expect, it } from 'vitest';
import { selectPrompt } from './prompts.js';
import { EXTRACTION_PROMPT } from './types.js';

describe('selectPrompt', () => {
  it('usa el prompt específico de la entidad cuando existe', () => {
    const p = selectPrompt('estado_cuenta', 'crearcoop');
    expect(p).toContain('DETALLE DE MOVIMIENTOS POR CRÉDITO');
    expect(p).not.toBe(EXTRACTION_PROMPT);
  });

  it('cae al prompt global del tipo cuando no hay específico para la entidad', () => {
    const p = selectPrompt('pagare', 'confiar');
    expect(p).toContain('PAGARÉ');
  });

  it('cae al prompt genérico para tipos sin registro o "otro"', () => {
    expect(selectPrompt('otro', 'confiar')).toBe(EXTRACTION_PROMPT);
    expect(selectPrompt('desconocido')).toBe(EXTRACTION_PROMPT);
    expect(selectPrompt(undefined)).toBe(EXTRACTION_PROMPT);
  });

  it('usa el prompt específico de la entidad por encima del global', () => {
    const global = selectPrompt('estado_cuenta', 'confiar');
    const specific = selectPrompt('estado_cuenta', 'crearcoop');
    expect(specific).not.toBe(global);
    expect(global).toContain('TOTAL SALDO A CARGO A');
  });

  it('incluye la regla de fecha de causación en el estado de cuenta', () => {
    expect(selectPrompt('estado_cuenta')).toContain('Fecha de causación');
    expect(selectPrompt('estado_cuenta')).toContain('fecha_causacion');
    expect(selectPrompt('estado_cuenta', 'crearcoop')).toContain('Fecha de causación');
  });

  it('exige la TEA redondeada a 2 decimales', () => {
    expect(selectPrompt('estado_cuenta')).toContain('REDONDÉALO a 2 decimales');
    expect(selectPrompt('amortizacion')).toContain('REDONDÉALO a 2 decimales');
  });

  it('enriquece la vinculación con deudor y codeudor por separado', () => {
    const p = selectPrompt('vinculacion');
    expect(p).toContain('DOS personas distintas');
    expect(p).toContain('FECHA DE EXPEDICIÓN');
    expect(p).toContain('fecha_nacimiento');
  });
});