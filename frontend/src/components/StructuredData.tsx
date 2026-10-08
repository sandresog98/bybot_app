import type { Credito, Parte, Structured } from '../types';

const fmt = (value: unknown): string => {
  if (value === null || value === undefined || value === '') return '—';
  if (typeof value === 'boolean') return value ? 'Sí' : 'No';
  if (typeof value === 'number') return new Intl.NumberFormat('es-CO').format(value);
  const text = String(value);
  if (/^\d{4}-\d{2}-\d{2}T/.test(text)) return text.slice(0, 10);
  return text;
};

const ROL_LABEL: Record<string, string> = {
  deudor: 'Deudor',
  codeudor: 'Codeudor',
  referencia: 'Referencia',
  apoderado: 'Apoderado',
  otorgante: 'Otorgante',
};

const PARTE_FIELDS: Array<[keyof Parte, string]> = [
  ['tipoDocumento', 'Tipo doc.'], ['numeroDocumento', 'Documento'], ['nombreCompleto', 'Nombre'],
  ['fechaExpedicion', 'Expedición'], ['fechaNacimiento', 'Nacimiento'], ['direccion', 'Dirección'],
  ['ciudad', 'Ciudad'], ['departamento', 'Departamento'], ['telefono', 'Teléfono'], ['celular', 'Celular'],
  ['email', 'Email'], ['ocupacion', 'Ocupación'], ['empresa', 'Empresa'], ['ingresosMensuales', 'Ingresos'],
  ['relacionDeudor', 'Relación'],
];

const CREDITO_FIELDS: Array<[keyof Credito, string]> = [
  ['numeroCredito', 'N.º crédito'], ['numeroPagare', 'N.º pagaré'], ['producto', 'Producto'], ['monto', 'Monto'],
  ['plazoMeses', 'Plazo (meses)'], ['tasaEa', 'TEA'], ['tasaInteresCorriente', 'Tasa corriente'], ['tasaInteresMora', 'Tasa mora'],
  ['fechaDesembolso', 'Desembolso'], ['fechaCausacion', 'Causación'], ['fechaCorte', 'Corte'],
  ['saldoCapital', 'Saldo capital'], ['totalInteresesCorrientes', 'Int. corrientes'], ['totalInteresesMora', 'Int. mora'],
  ['totalSeguroVida', 'Seguro vida'], ['totalDeuda', 'Total deuda'], ['diasMora', 'Días mora'],
  ['fechaUltimoPago', 'Último pago'], ['valorUltimoPago', 'Valor último pago'],
];

function ParteCard({ parte }: { parte: Parte }) {
  const fields = PARTE_FIELDS.filter(([key]) => parte[key] !== null && parte[key] !== undefined && parte[key] !== '');
  return (
    <div className="ia-card">
      <div className="ia-row"><span className="ia-label">Rol</span><span className="ia-value">{ROL_LABEL[parte.rol] ?? parte.rol}</span></div>
      {fields.map(([key, label]) => (
        <div className="ia-row" key={String(key)}><span className="ia-label">{label}</span><span className="ia-value">{fmt(parte[key])}</span></div>
      ))}
    </div>
  );
}

export function StructuredData({ data }: { data: Structured | null }) {
  if (!data) return null;
  const { partes, credito, movimientos, cuotas, campos } = data;
  const empty = partes.length === 0 && !credito && movimientos.length === 0 && cuotas.length === 0;
  if (empty) return <p className="placeholder">Aún no hay datos estructurados. Consolida el análisis del proceso.</p>;

  return (
    <div className="structured">
      {partes.length > 0 && (
        <>
          <h4>Partes</h4>
          <div className="structured-grid">{partes.map(parte => <ParteCard key={parte.id} parte={parte} />)}</div>
        </>
      )}

      {credito && (
        <>
          <h4>Crédito</h4>
          <div className="ia-card">
            {CREDITO_FIELDS.filter(([key]) => credito[key] !== null && credito[key] !== undefined && credito[key] !== '').map(([key, label]) => (
              <div className="ia-row" key={String(key)}><span className="ia-label">{label}</span><span className="ia-value">{fmt(credito[key])}</span></div>
            ))}
          </div>
        </>
      )}

      {movimientos.length > 0 && (
        <>
          <h4>Movimientos ({movimientos.length})</h4>
          <div className="ia-table-wrap">
            <table className="ia-table">
              <thead><tr><th>Documento</th><th>Fecha</th><th>Descripción</th><th>Total</th><th>Capital</th><th>Interés</th><th>Mora</th><th>Seg. vida</th><th>Otros</th></tr></thead>
              <tbody>
                {movimientos.map(m => (
                  <tr key={m.id}>
                    <td>{fmt(m.documento)}</td><td>{fmt(m.fecha)}</td><td>{fmt(m.descripcion)}</td><td>{fmt(m.total)}</td>
                    <td>{fmt(m.capital)}</td><td>{fmt(m.interes)}</td><td>{fmt(m.mora)}</td><td>{fmt(m.seguroVida)}</td><td>{fmt(m.otros)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}

      {cuotas.length > 0 && (
        <details className="history" open={cuotas.length <= 12}>
          <summary>Cuotas de amortización ({cuotas.length})</summary>
          <div className="ia-table-wrap">
            <table className="ia-table">
              <thead><tr><th>N.º</th><th>Fecha</th><th>Cuota</th><th>Abono capital</th><th>Abono interés</th><th>Saldo</th></tr></thead>
              <tbody>
                {cuotas.map(c => (
                  <tr key={c.id}><td>{fmt(c.numero)}</td><td>{fmt(c.fecha)}</td><td>{fmt(c.cuota)}</td><td>{fmt(c.abonoCapital)}</td><td>{fmt(c.abonoInteres)}</td><td>{fmt(c.saldo)}</td></tr>
                ))}
              </tbody>
            </table>
          </div>
        </details>
      )}

      {campos.length > 0 && (
        <details className="history">
          <summary>Campos extraídos ({campos.length})</summary>
          <div className="ia-table-wrap">
            <table className="ia-table">
              <thead><tr><th>Ruta</th><th>Valor</th></tr></thead>
              <tbody>
                {campos.map(c => (
                  <tr key={c.id}><td>{c.ruta}</td><td>{fmt(c.valorTexto ?? c.valorNumero ?? c.valorFecha ?? c.valorBool)}</td></tr>
                ))}
              </tbody>
            </table>
          </div>
        </details>
      )}
    </div>
  );
}
