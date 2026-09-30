import type { FormEvent } from 'react';
import { FILE_TIPO_LABELS } from '../types';
import type { Detail } from '../types';
import { ResultadoIA } from './ResultadoIA';

type Props = {
  selected: Detail | null;
  busyUpload: boolean;
  busyAnalyze: boolean;
  onUpload: (form: FormData, tipo?: string) => Promise<void>;
  onAnalyze: (fileId?: number) => Promise<void>;
  onDownload: (fileId: number, name: string) => void;
  onValidar: (analysisId: number, datos: unknown) => Promise<void>;
};

const extensionOf = (name: string) => name.split('.').pop()?.toUpperCase() ?? 'FILE';
const fmtUsd = (v: string | number | null | undefined) => (v == null ? null : `USD ${Number(v).toFixed(6)}`);

export function ProcessDetail({ selected, busyUpload, busyAnalyze, onUpload, onAnalyze, onDownload, onValidar }: Props) {
  const handleUpload = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const formEl = event.currentTarget;
    const form = new FormData(formEl);
    const tipo = String(form.get('tipo') ?? '').trim() || undefined;
    void onUpload(form, tipo).then(() => formEl.reset());
  };

  if (!selected) return <article><p className="placeholder">Selecciona o crea un proceso.</p></article>;

  return (
    <article>
      <h2>{selected.title}</h2>
      <p className="hint">
        {selected.code} · <span className="status" data-status={selected.status}>{selected.status}</span>
        {selected.entidad && <> · Cliente: <strong>{selected.entidad.nombre}</strong></>}
      </p>

      <h3>Archivos</h3>
      <form onSubmit={handleUpload} className="inline">
        <input name="file" type="file" accept=".pdf,.txt,.csv,.json,.jpg,.jpeg,.png" required />
        <select name="tipo" defaultValue="otro">
          {Object.entries(FILE_TIPO_LABELS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </select>
        <button type="submit" disabled={busyUpload}>{busyUpload ? 'Subiendo…' : 'Subir'}</button>
      </form>
      <ul>
        {selected.files.length === 0 && <p className="placeholder">Este proceso aún no tiene archivos.</p>}
        {selected.files.map(file => (
          <li key={file.id} className="file-item">
            <button className="file-name" onClick={() => onDownload(file.id, file.originalName)} title="Descargar">
              <span className="ico">{extensionOf(file.originalName)}</span>
              <span>{file.originalName}</span>
            </button>
            <span className="file-size">
              {file.tipo ? FILE_TIPO_LABELS[file.tipo] ?? file.tipo : ''} · {(file.sizeBytes / 1024).toFixed(1)} KB
            </span>
            <span className="file-actions">
              <button className="btn-small" disabled={busyAnalyze} onClick={() => void onAnalyze(file.id)}>
                {busyAnalyze ? 'Analizando…' : 'Analizar IA'}
              </button>
            </span>
          </li>
        ))}
      </ul>

      <h3>Resultados</h3>
      {selected.analyses.length === 0
        ? <p className="placeholder">Aún no hay análisis en este proceso.</p>
        : selected.analyses.map(item => item.status === 'completed' ? (
          <section key={item.id} className="result-block">
            <div className="result-head">
              <span className="status" data-status={item.status}>completado</span>
              {item.model && <span className="consumo-chip">{item.model}</span>}
              {(item.inputTokens != null || item.outputTokens != null) && (
                <span className="consumo-chip">tok {item.inputTokens ?? 0} / {item.outputTokens ?? 0}</span>
              )}
              {fmtUsd(item.costUsd) && <span className="consumo-chip">{fmtUsd(item.costUsd)}</span>}
            </div>
            <ResultadoIA
              data={item.validated ?? item.result ?? { error: item.error }}
              onSave={datos => onValidar(item.id, datos)}
            />
          </section>
        ) : (
          <details key={item.id} className="result" open={item.status === 'failed'}>
            <summary><span className="status" data-status={item.status}>{item.status}</span></summary>
            {item.error && <p className="notice">{item.error}</p>}
          </details>
        ))}
    </article>
  );
}