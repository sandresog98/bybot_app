import type { FormEvent } from 'react';
import type { Detail } from '../types';

type Props = {
  selected: Detail | null;
  busyUpload: boolean;
  busyAnalyze: boolean;
  onUpload: (form: FormData) => Promise<void>;
  onAnalyze: (fileId?: number) => Promise<void>;
  onDownload: (fileId: number, name: string) => void;
};

const extensionOf = (name: string) => name.split('.').pop()?.toUpperCase() ?? 'FILE';

export function ProcessDetail({ selected, busyUpload, busyAnalyze, onUpload, onAnalyze, onDownload }: Props) {
  const handleUpload = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const formEl = event.currentTarget;
    const form = new FormData(formEl);
    void onUpload(form).then(() => formEl.reset());
  };

  if (!selected) return <article><p className="placeholder">Selecciona o crea un proceso.</p></article>;

  return (
    <article>
      <h2>{selected.title}</h2>
      <p className="hint">{selected.code} · <span className="status" data-status={selected.status}>{selected.status}</span></p>

      <h3>Archivos</h3>
      <form onSubmit={handleUpload} className="inline">
        <input name="file" type="file" accept=".pdf,.txt,.csv,.json,.jpg,.jpeg,.png" required />
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
            <span className="file-size">{(file.sizeBytes / 1024).toFixed(1)} KB</span>
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
        : selected.analyses.map(item => (
          <details key={item.id} className="result" open={item.status === 'completed'}>
            <summary>{item.status}</summary>
            <pre>{JSON.stringify(item.result ?? { error: item.error }, null, 2)}</pre>
          </details>
        ))}
    </article>
  );
}