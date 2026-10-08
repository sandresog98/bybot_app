import { describe, expect, it } from 'vitest';
import { consolidate } from './consolidate.js';

describe('consolidate', () => {
  it('fusiona por prioridad conservando la unión de campos', () => {
    const out = consolidate([
      { tipo: 'vinculacion', name: 'v.pdf', result: { deudor: { nombre_completo: 'Juan', direccion: 'Calle 1' }, referencias: [{ nombre: 'A' }, { nombre: 'B' }] } },
      { tipo: 'pagare', name: 'p.pdf', result: { deudor: { nombre_completo: 'Juan Perez', numero_documento: '123' }, pagare: { valor: 100 } } },
    ]);
    expect(out.deudor).toEqual({ nombre_completo: 'Juan Perez', direccion: 'Calle 1', numero_documento: '123' });
    expect(out.pagare).toEqual({ valor: 100 });
    expect(out.referencias).toHaveLength(2);
    expect(out._consolidado.documentos).toBe(2);
  });

  it('deduplica arreglos y no deja que null sobrescriba un valor', () => {
    const out = consolidate([
      { tipo: 'anexo', name: 'a.pdf', result: { movimientos: [{ fecha: '2024-01-01', total: 10 }], observaciones: 'nota' } },
      { tipo: 'estado_cuenta', name: 'e.pdf', result: { movimientos: [{ fecha: '2024-01-01', total: 10 }, { fecha: '2024-02-01', total: 20 }], observaciones: null } },
    ]);
    expect((out.movimientos as unknown[]).length).toBe(2);
    expect(out.observaciones).toBe('nota');
    expect(out._consolidado.fuentes.map(f => f.tipo)).toEqual(['anexo', 'estado_cuenta']);
  });
});
