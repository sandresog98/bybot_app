export type CuotaInput = {
  numero: number | null;
  fecha: Date | null;
  cuota: number | null;
  abonoCapital: number | null;
  abonoInteres: number | null;
  saldo: number | null;
};

export type LiquidacionInput = {
  cuotas: CuotaInput[];
  cuotaInicial: number;
  cuotaCorte: number;
  overridesCapital?: Record<number, number>;
  interesesMora?: number | null;
  saldoCapitalExtracto?: number | null;
  totalDeudaExtracto?: number | null;
  smlmv: number;
  umbralMinimaSmlmv?: number;
};

export type LiquidacionCuota = {
  numero: number;
  fecha: Date | null;
  capital: number;
  interesPlazo: number;
  saldoCapital: number | null;
  inconsistente: boolean;
};

export type LiquidacionResult = {
  cuotas: LiquidacionCuota[];
  totalCapitalMora: number;
  totalInteresPlazo: number;
  interesesMora: number | null;
  capitalAcelerado: number | null;
  saldoCapitalCorte: number | null;
  total: number;
  cuantia: number;
  smlmv: number;
  umbralMinima: number;
  topeMinima: number;
  competencia: 'minima' | 'menor';
  juez: string;
  warnings: string[];
};

const round2 = (value: number) => Math.round(value * 100) / 100;

export function liquidarDemanda(input: LiquidacionInput): LiquidacionResult {
  const warnings: string[] = [];
  const umbralMinima = input.umbralMinimaSmlmv ?? 40;

  if (input.cuotaInicial > input.cuotaCorte) {
    throw new Error('La cuota inicial en mora no puede ser mayor que la cuota de corte.');
  }

  const byNumero = new Map<number, CuotaInput>();
  for (const cuota of input.cuotas) if (cuota.numero != null) byNumero.set(cuota.numero, cuota);

  const cuotas: LiquidacionCuota[] = [];
  let totalCapitalMora = 0;
  let totalInteresPlazo = 0;

  for (let numero = input.cuotaInicial; numero <= input.cuotaCorte; numero += 1) {
    const cuota = byNumero.get(numero);
    if (!cuota) { warnings.push(`No hay datos de la cuota ${numero}.`); continue; }
    const override = input.overridesCapital?.[numero];
    const capital = override ?? cuota.abonoCapital ?? 0;
    const interesPlazo = cuota.abonoInteres ?? 0;
    const inconsistente = cuota.cuota != null && Math.abs(round2(capital + interesPlazo) - round2(cuota.cuota)) > 1;
    if (inconsistente) warnings.push(`Cuota ${numero}: capital + interés (${round2(capital + interesPlazo)}) ≠ cuota (${cuota.cuota}).`);
    if (override != null) warnings.push(`Cuota ${numero}: capital ajustado manualmente a ${override}.`);
    totalCapitalMora += capital;
    totalInteresPlazo += interesPlazo;
    cuotas.push({ numero, fecha: cuota.fecha, capital, interesPlazo, saldoCapital: cuota.saldo, inconsistente });
  }

  const corte = byNumero.get(input.cuotaCorte);
  const saldoCapitalCorte = corte?.saldo ?? null;
  const capitalAcelerado = saldoCapitalCorte;
  const interesesMora = input.interesesMora ?? null;

  const total = round2(totalCapitalMora + totalInteresPlazo + (interesesMora ?? 0) + (capitalAcelerado ?? 0));
  const cuantia = total;
  const topeMinima = round2(umbralMinima * input.smlmv);
  const competencia = cuantia < topeMinima ? 'minima' : 'menor';
  const juez = competencia === 'minima' ? 'Juez de Pequeñas Causas y Competencia Múltiple' : 'Juez Civil Municipal';

  if (input.saldoCapitalExtracto != null && capitalAcelerado != null && Math.abs(input.saldoCapitalExtracto - capitalAcelerado) > 1000) {
    warnings.push(`Saldo a capital del extracto (${input.saldoCapitalExtracto}) difiere del proyectado en la cuota ${input.cuotaCorte} (${capitalAcelerado}).`);
  }
  if (input.totalDeudaExtracto != null && input.saldoCapitalExtracto != null && input.totalDeudaExtracto < input.saldoCapitalExtracto) {
    warnings.push('El total de la deuda del extracto es menor que el saldo a capital (posible inconsistencia del documento).');
  }

  return {
    cuotas,
    totalCapitalMora: round2(totalCapitalMora),
    totalInteresPlazo: round2(totalInteresPlazo),
    interesesMora,
    capitalAcelerado,
    saldoCapitalCorte,
    total,
    cuantia,
    smlmv: input.smlmv,
    umbralMinima,
    topeMinima,
    competencia,
    juez,
    warnings,
  };
}

// Mapeo de producto del crédito al tipo de demanda (según README CONFIAR).
export type TipoDemanda = 'consumo' | 'hipotecario';
export function tipoDemandaPorProducto(producto?: string | null): TipoDemanda {
  const value = (producto ?? '').toLowerCase();
  if (value.includes('findeter') || value.includes('hipotec') || value.includes('vivienda') || value.includes('vis')) return 'hipotecario';
  return 'consumo';
}
