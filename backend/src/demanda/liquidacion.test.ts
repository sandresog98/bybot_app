import { describe, expect, it } from 'vitest';
import { liquidarDemanda, tipoDemandaPorProducto } from './liquidacion.js';

const cuotas = [
  { numero: 18, fecha: null, cuota: 1000, abonoCapital: 600, abonoInteres: 400, saldo: 5000 },
  { numero: 19, fecha: null, cuota: 1000, abonoCapital: 610, abonoInteres: 390, saldo: 4390 },
  { numero: 20, fecha: null, cuota: 1000, abonoCapital: 620, abonoInteres: 380, saldo: 3770 },
];

describe('liquidarDemanda', () => {
  it('suma capitales e intereses y usa el saldo de la cuota de corte como capital acelerado', () => {
    const result = liquidarDemanda({ cuotas, cuotaInicial: 18, cuotaCorte: 20, smlmv: 1000 });
    expect(result.totalCapitalMora).toBe(1830);
    expect(result.totalInteresPlazo).toBe(1170);
    expect(result.capitalAcelerado).toBe(3770);
    expect(result.total).toBe(6770);
    expect(result.competencia).toBe('minima');
    expect(result.cuotas).toHaveLength(3);
  });

  it('marca competencia menor cuando supera el tope de mínima cuantía', () => {
    const result = liquidarDemanda({ cuotas, cuotaInicial: 18, cuotaCorte: 20, smlmv: 100, umbralMinimaSmlmv: 10 });
    expect(result.topeMinima).toBe(1000);
    expect(result.competencia).toBe('menor');
  });

  it('permite override de capital y avisa de inconsistencias', () => {
    const conInconsistencia = [{ numero: 1, fecha: null, cuota: 1000, abonoCapital: 500, abonoInteres: 400, saldo: 900 }];
    const result = liquidarDemanda({
      cuotas: conInconsistencia, cuotaInicial: 1, cuotaCorte: 1, smlmv: 1000,
      overridesCapital: { 1: 700 },
    });
    expect(result.cuotas[0]?.capital).toBe(700);
    expect(result.warnings.some(w => w.includes('ajustado manualmente'))).toBe(true);
    expect(result.warnings.some(w => w.includes('≠ cuota'))).toBe(true);
  });

  it('advierte si el total del extracto es menor que el saldo a capital', () => {
    const result = liquidarDemanda({
      cuotas, cuotaInicial: 18, cuotaCorte: 20, smlmv: 1000,
      saldoCapitalExtracto: 21_343_017, totalDeudaExtracto: 4_745_186,
    });
    expect(result.warnings.some(w => w.includes('menor que el saldo a capital'))).toBe(true);
  });

  it('lanza error si la cuota inicial supera la de corte', () => {
    expect(() => liquidarDemanda({ cuotas, cuotaInicial: 20, cuotaCorte: 18, smlmv: 1000 })).toThrow();
  });
});

describe('tipoDemandaPorProducto', () => {
  it('clasifica hipotecario y consumo', () => {
    expect(tipoDemandaPorProducto('007 – Crédito sobre aportes sociales')).toBe('consumo');
    expect(tipoDemandaPorProducto('FINDETER VIS')).toBe('hipotecario');
    expect(tipoDemandaPorProducto(null)).toBe('consumo');
  });
});
