export type ConsolidateSource = {
  tipo: string | null;
  name: string;
  result: unknown;
};

export type ConsolidateOutput = Record<string, unknown> & {
  _consolidado: {
    fuentes: Array<{ tipo: string | null; archivo: string }>;
    documentos: number;
    generadoEn: string;
  };
};

// A mayor prioridad, sus valores prevalecen ante conflictos (se aplican al final).
const PRIORITY: Record<string, number> = {
  pagare: 50,
  estado_cuenta: 40,
  vinculacion: 30,
  poder: 20,
  anexo: 15,
  amortizacion: 10,
  otro: 5,
};

const isObject = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null && !Array.isArray(value);
const isEmpty = (value: unknown) => value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0);

const stableKey = (value: unknown): string => {
  if (Array.isArray(value)) return `[${value.map(stableKey).join(',')}]`;
  if (isObject(value)) return `{${Object.keys(value).sort().map(k => `${k}:${stableKey(value[k])}`).join(',')}}`;
  return JSON.stringify(value) ?? 'null';
};

function dedupe(rows: unknown[]): unknown[] {
  const seen = new Set<string>();
  const out: unknown[] = [];
  for (const row of rows) {
    const key = stableKey(row);
    if (!seen.has(key)) { seen.add(key); out.push(row); }
  }
  return out;
}

function mergeValue(base: unknown, incoming: unknown): unknown {
  if (isEmpty(incoming)) return base;
  if (isEmpty(base)) return incoming;
  if (Array.isArray(base) && Array.isArray(incoming)) return dedupe([...base, ...incoming]);
  if (isObject(base) && isObject(incoming)) {
    const out: Record<string, unknown> = { ...base };
    for (const [key, value] of Object.entries(incoming)) out[key] = mergeValue(out[key], value);
    return out;
  }
  return incoming; // conflicto escalar: gana el de mayor prioridad (aplicado al final)
}

export function consolidate(sources: ConsolidateSource[]): ConsolidateOutput {
  const ordered = [...sources].sort(
    (a, b) => (PRIORITY[a.tipo ?? 'otro'] ?? 0) - (PRIORITY[b.tipo ?? 'otro'] ?? 0),
  );

  let merged: Record<string, unknown> = {};
  for (const source of ordered) {
    if (!isObject(source.result)) continue;
    merged = mergeValue(merged, source.result) as Record<string, unknown>;
  }

  return {
    ...merged,
    _consolidado: {
      fuentes: ordered.map(source => ({ tipo: source.tipo, archivo: source.name })),
      documentos: ordered.length,
      generadoEn: new Date().toISOString(),
    },
  };
}
