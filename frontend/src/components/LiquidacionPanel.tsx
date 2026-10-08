import { useState } from 'react';
import { api } from '../api';
import type { Liquidacion } from '../types';

const money = (value: number | null | undefined) => (value == null ? '—' : new Intl.NumberFormat('es-CO').format(value));

function Row({ label, value }: { label: string; value: string }) {
  return <div className="ia-row"><span className="ia-label">{label}</span><span className="ia-value">{value}</span></div>;
}

export function LiquidacionPanel({ processId }: { processId: number }) {
  const [cuotaInicial, setCuotaInicial] = useState('');
  const [cuotaCorte, setCuotaCorte] = useState('');
  const [interesesMora, setInteresesMora] = useState('');
  const [result, setResult] = useState<Liquidacion | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const calcular = async () => {
    setBusy(true); setError(null);
    try {
      const data = await api.liquidar(processId, {
        cuotaInicial: Number(cuotaInicial),
        cuotaCorte: Number(cuotaCorte),
        ...(interesesMora ? { interesesMora: Number(interesesMora) } : {}),
      });
      setResult(data);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'No se pudo calcular la liquidación.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="liquidacion">
      <div className="inline">
        <label className="field-inline">Cuota inicial en mora
          <input type="number" min={1} value={cuotaInicial} onChange={e => setCuotaInicial(e.target.value)} />
        </label>
        <label className="field-inline">Cuota de corte
          <input type="number" min={1} value={cuotaCorte} onChange={e => setCuotaCorte(e.target.value)} />
        </label>
        <label className="field-inline">Intereses de mora (opcional)
          <input type="number" value={interesesMora} onChange={e => setInteresesMora(e.target.value)} />
        </label>
        <button type="button" disabled={busy || !cuotaInicial || !cuotaCorte} onClick={() => void calcular()}>
          {busy ? 'Calculando…' : 'Calcular liquidación'}
        </button>
      </div>

      {error && <p className="notice">{error}</p>}

      {result && (
        <>
          <div className="result-head">
            <span className="consumo-chip">Tipo: {result.tipoDemanda}</span>
            <span className="consumo-chip">Competencia: {result.competencia}</span>
            <span className="consumo-chip">{result.juez}</span>
          </div>
          <div className="ia-table-wrap">
            <table className="ia-table">
              <thead><tr><th>Cuota</th><th>Fecha</th><th>Capital</th><th>Interés plazo</th><th>Saldo capital</th></tr></thead>
              <tbody>
                {result.cuotas.map(c => (
                  <tr key={c.numero}>
                    <td>{c.numero}</td>
                    <td>{c.fecha ? String(c.fecha).slice(0, 10) : '—'}</td>
                    <td>{money(c.capital)}</td>
                    <td>{money(c.interesPlazo)}</td>
                    <td>{money(c.saldoCapital)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="ia-card">
            <Row label="Total capital en mora" value={money(result.totalCapitalMora)} />
            <Row label="Total intereses de plazo" value={money(result.totalInteresPlazo)} />
            <Row label="Intereses de mora" value={money(result.interesesMora)} />
            <Row label="Capital acelerado (saldo cuota de corte)" value={money(result.capitalAcelerado)} />
            <Row label="Total a demandar" value={money(result.total)} />
            <Row label={`Tope mínima cuantía (${result.umbralMinima} SMLMV)`} value={money(result.topeMinima)} />
          </div>
          {result.warnings.length > 0 && (
            <ul className="warnings">
              {result.warnings.map((warning, index) => <li key={index} className="notice">{warning}</li>)}
            </ul>
          )}
        </>
      )}
    </div>
  );
}
