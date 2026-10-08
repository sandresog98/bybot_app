import type { PrismaClient } from '@prisma/client';

type Json = Record<string, unknown>;

const isObject = (value: unknown): value is Json => typeof value === 'object' && value !== null && !Array.isArray(value);
const obj = (value: unknown): Json => (isObject(value) ? value : {});
const rows = (value: unknown): Json[] => (Array.isArray(value) ? value.filter(isObject) : []);

const str = (value: unknown): string | null => {
  if (value === null || value === undefined) return null;
  const text = String(value).trim();
  return text === '' ? null : text;
};

export function toNumber(value: unknown): number | null {
  if (typeof value === 'number') return Number.isFinite(value) ? value : null;
  if (typeof value !== 'string') return null;
  let text = value.trim().replace(/[^0-9.,-]/g, '');
  if (!text) return null;
  if (text.includes(',') && text.includes('.')) text = text.replace(/\./g, '').replace(',', '.');
  else if (text.includes(',')) text = text.replace(',', '.');
  else if ((text.match(/\./g) ?? []).length > 1) text = text.replace(/\./g, '');
  const parsed = Number(text);
  return Number.isFinite(parsed) ? parsed : null;
}

const int = (value: unknown): number | null => {
  const parsed = toNumber(value);
  return parsed === null ? null : Math.round(parsed);
};

const date = (value: unknown): Date | null => {
  if (value === null || value === undefined || value === '') return null;
  const text = String(value).trim();
  const parsed = new Date(text.length <= 10 ? `${text}T00:00:00Z` : text);
  return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const isDateText = (value: string) => /^\d{4}-\d{2}-\d{2}([T ].*)?$/.test(value.trim());

type Field = { ruta: string; clave: string; value: unknown };

function flatten(value: unknown, prefix: string, out: Field[]) {
  if (value === null || value === undefined) return;
  if (Array.isArray(value)) return; // los arreglos se representan en sus propias tablas
  if (isObject(value)) {
    for (const [key, child] of Object.entries(value)) flatten(child, prefix ? `${prefix}.${key}` : key, out);
    return;
  }
  if (!prefix) return;
  out.push({ ruta: prefix, clave: prefix.split('.').pop() ?? prefix, value });
}

function parteFrom(source: Json, rol: string, orden: number, processId: number) {
  return {
    processId,
    rol,
    orden,
    tipoDocumento: str(source.tipo_documento),
    numeroDocumento: str(source.numero_documento),
    nombreCompleto: str(source.nombre_completo),
    fechaExpedicion: date(source.fecha_expedicion),
    lugarExpedicion: str(source.lugar_expedicion),
    fechaNacimiento: date(source.fecha_nacimiento),
    direccion: str(source.direccion),
    ciudad: str(source.ciudad),
    departamento: str(source.departamento),
    telefono: str(source.telefono),
    celular: str(source.celular),
    email: str(source.email),
    ocupacion: str(source.ocupacion),
    empresa: str(source.empresa),
    ingresosMensuales: toNumber(source.ingresos_mensuales),
    relacionDeudor: str(source.relacion_deudor),
  };
}

function fieldData(processId: number, analysisId: number | null, field: Field) {
  const base = { processId, analysisId, ruta: field.ruta, clave: field.clave };
  if (typeof field.value === 'number' && Number.isFinite(field.value)) return { ...base, valorNumero: field.value };
  if (typeof field.value === 'boolean') return { ...base, valorBool: field.value };
  const text = String(field.value);
  if (isDateText(text)) {
    const parsed = date(text);
    if (parsed) return { ...base, valorFecha: parsed };
  }
  return { ...base, valorTexto: text };
}

export async function normalizeProcess(prisma: PrismaClient, processId: number, analysisId: number | null, result: unknown): Promise<void> {
  const r = obj(result);
  const estado = obj(r.estado_cuenta);
  const credito = obj(r.credito);
  const amortizacion = obj(r.amortizacion);
  const pagare = obj(r.pagare);
  const deudor = obj(r.deudor);
  const codeudor = obj(r.codeudor);
  const poder = obj(r.poder);
  const referencias = rows(r.referencias);
  const movimientos = rows(r.movimientos);
  const cuotas = rows(amortizacion.cuotas);

  await prisma.$transaction(async tx => {
    await tx.movimiento.deleteMany({ where: { processId } });
    await tx.cuotaAmortizacion.deleteMany({ where: { processId } });
    await tx.extraccionCampo.deleteMany({ where: { processId } });
    await tx.parte.deleteMany({ where: { processId } });
    await tx.credito.deleteMany({ where: { processId } });

    const creditoData = {
      processId,
      numeroCredito: str(credito.numero_credito) ?? str(estado.numero_credito),
      numeroPagare: str(credito.numero_pagare) ?? str(pagare.numero),
      producto: str(credito.producto),
      monto: toNumber(credito.monto) ?? toNumber(pagare.valor),
      plazoMeses: int(credito.plazo_meses),
      tasaEa: toNumber(credito.tasa_ea) ?? toNumber(amortizacion.tasa_ea) ?? toNumber(estado.tasa_ea),
      tasaInteresCorriente: toNumber(estado.tasa_interes_corriente),
      tasaInteresMora: toNumber(estado.tasa_interes_mora),
      fechaDesembolso: date(credito.fecha_desembolso),
      fechaCorte: date(estado.fecha_corte),
      fechaCausacion: date(estado.fecha_causacion),
      saldoCapital: toNumber(estado.saldo_capital),
      totalInteresesCorrientes: toNumber(estado.total_intereses_corrientes),
      totalInteresesMora: toNumber(estado.total_intereses_mora),
      totalSeguroVida: toNumber(estado.total_seguro_vida),
      totalDeuda: toNumber(estado.total_deuda),
      diasMora: int(estado.dias_mora),
      fechaUltimoPago: date(estado.fecha_ultimo_pago),
      valorUltimoPago: toNumber(estado.valor_ultimo_pago),
    };
    const hasCredito = Object.entries(creditoData).some(([key, value]) => key !== 'processId' && value !== null);
    const created = hasCredito ? await tx.credito.create({ data: creditoData }) : null;

    if (created && movimientos.length) {
      await tx.movimiento.createMany({
        data: movimientos.map((row, index) => ({
          processId, creditoId: created.id, orden: index,
          documento: str(row.documento), fecha: date(row.fecha), descripcion: str(row.descripcion),
          total: toNumber(row.total), capital: toNumber(row.capital), interes: toNumber(row.interes),
          mora: toNumber(row.mora), seguroVida: toNumber(row.seguro_vida ?? row.seguroVida), otros: toNumber(row.otros),
        })),
      });
    }

    if (created && cuotas.length) {
      await tx.cuotaAmortizacion.createMany({
        data: cuotas.map(row => ({
          processId, creditoId: created.id, numero: int(row.numero), fecha: date(row.fecha),
          cuota: toNumber(row.cuota), abonoCapital: toNumber(row.abono_capital),
          abonoInteres: toNumber(row.abono_interes), saldo: toNumber(row.saldo),
        })),
      });
    }

    const partes = [];
    if (Object.keys(deudor).length) partes.push(parteFrom(deudor, 'deudor', 0, processId));
    if (Object.keys(codeudor).length && codeudor.existe !== false) partes.push(parteFrom(codeudor, 'codeudor', 0, processId));
    referencias.forEach((ref, index) => partes.push({
      processId, rol: 'referencia', orden: index, nombreCompleto: str(ref.nombre),
      telefono: str(ref.telefono), relacionDeudor: str(ref.relacion),
    }));
    if (str(poder.apoderado)) partes.push({ processId, rol: 'apoderado', orden: 0, nombreCompleto: str(poder.apoderado), numeroDocumento: str(poder.numero_documento_apoderado) });
    if (str(poder.otorgante)) partes.push({ processId, rol: 'otorgante', orden: 0, nombreCompleto: str(poder.otorgante) });

    const validPartes = partes.filter(parte => Object.entries(parte).some(([key, value]) => !['processId', 'rol', 'orden'].includes(key) && value !== null && value !== undefined));
    if (validPartes.length) await tx.parte.createMany({ data: validPartes });

    const fields: Field[] = [];
    flatten(r, '', fields);
    const fieldRows = fields
      .filter(field => field.ruta && !field.ruta.startsWith('_consolidado'))
      .map(field => fieldData(processId, analysisId, field));
    if (fieldRows.length) await tx.extraccionCampo.createMany({ data: fieldRows });

    const deudorNombre = str(deudor.nombre_completo);
    const deudorDocumento = str(deudor.numero_documento);
    const processData: { deudorNombre?: string; deudorDocumento?: string } = {};
    if (deudorNombre) processData.deudorNombre = deudorNombre;
    if (deudorDocumento) processData.deudorDocumento = deudorDocumento;
    if (Object.keys(processData).length) await tx.process.update({ where: { id: processId }, data: processData });
  });
}
