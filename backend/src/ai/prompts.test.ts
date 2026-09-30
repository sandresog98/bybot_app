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
    const p = selectPrompt('pagare', 'condiar');
    expect(p).toContain('PAGARÉ');
  });

  it('cae al prompt genérico para tipos sin registro o "otro"', () => {
    expect(selectPrompt('otro', 'condiar')).toBe(EXTRACTION_PROMPT);
    expect(selectPrompt('desconocido')).toBe(EXTRACTION_PROMPT);
    expect(selectPrompt(undefined)).toBe(EXTRACTION_PROMPT);
  });

  it('usa el prompt específico de la entidad por encima del global', () => {
    const global = selectPrompt('estado_cuenta', 'condiar');
    const specific = selectPrompt('estado_cuenta', 'crearcoop');
    expect(specific).not.toBe(global);
    expect(global).toContain('TOTAL SALDO A CARGO A');
  });
});