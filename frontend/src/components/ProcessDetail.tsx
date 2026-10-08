import { useState } from 'react';
import type { FormEvent } from 'react';
import { FILE_TIPO_LABELS } from '../types';
import type { Detail, FileItem, Structured } from '../types';
import { ResultadoIA } from './ResultadoIA';
import { StructuredData } from './StructuredData';

type Props = {
  selected: Detail | null;
  structured: Structured | null;
  busyUpload: boolean;
  busyAnalyze: boolean;
  busyAnalyzeAll: boolean;
  busyConsolidate: boolean;
  onUpload: (form: FormData, tipo?: string) => Promise<void>;
  onAnalyze: (fileId?: number) => Promise<void>;
  onAnalyzeAll: () => Promise<void>;
  onConsolidate: () => Promise<void>;
  onDelete: (fileId: number) => Promise<void>;
  onReplace: (fileId: number, form: FormData, tipo?: string) => Promise<void>;
  onView: (fileId: number, name: string) => void;
  onDownload: (fileId: number, name: string) => void;
  onValidar: (analysisId: number, datos: unknown) => Promise<void>;
};

const MIME_EXT: Record<string, string> = {
  'application/pdf': 'PDF',
  'image/png': 'PNG',
  'image/jpeg': 'JPG',
  'image/webp': 'WEBP',
  'text/plain': 'TXT',
  'text/csv': 'CSV',
  'application/json': 'JSON',
};
const extOfMime = (mime?: string | null) => {
  if (!mime) return 'FILE';
  return MIME_EXT[mime] ?? mime.split('/')[1]?.toUpperCase() ?? 'FILE';
};
const fmtUsd = (v: string | number | null | undefined) => (v == null ? null : `USD ${Number(v).toFixed(6)}`);

export function ProcessDetail({ selected, structured, busyUpload, busyAnalyze, busyAnalyzeAll, busyConsolidate, onUpload, onAnalyze, onAnalyzeAll, onConsolidate, onDelete, onReplace, onView, onDownload, onValidar }: Props) {
  const [confirmId, setConfirmId] = useState<number | null>(null);
  const [selectedRun, setSelectedRun] = useState<Record<string, number>>({});

  const handleUpload = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const formEl = event.currentTarget;
    const form = new FormData(formEl);
    const tipo = String(form.get('tipo') ?? '').trim() || undefined;
    void onUpload(form, tipo).then(() => formEl.reset());
  };

  const handleReplace = (file: FileItem, fileList: FileList | null) => {
    const chosen = fileList?.[0];
    if (!chosen) return;
    const form = new FormData();
    form.append('file', chosen);
    void onReplace(file.id, form, file.tipo ?? undefined);
  };

  const latestByFile = new Map<number, Detail['analyses'][number]>();
  const runsByFile = new Map<string, Detail['analyses']>();
  for (const analysis of selected?.analyses ?? []) {
    const key = analysis.fileId == null ? 'process' : String(analysis.fileId);
    const list = runsByFile.get(key) ?? [];
    list.push(analysis);
    runsByFile.set(key, list);
    if (analysis.fileId != null && !latestByFile.has(analysis.fileId)) latestByFile.set(analysis.fileId, analysis);
  }
  const groups = [...runsByFile.entries()]
    .map(([key, runs]) => ({ key, runs, primary: runs.find(run => run.status === 'completed') ?? runs[0] }))
    .sort((a, b) => (b.runs[0]?.id ?? 0) - (a.runs[0]?.id ?? 0));

  if (!selected) return <article><p className="placeholder">Selecciona o crea un proceso.</p></article>;

  return (
    <article>
      <h2>{selected.title}</h2>
      <p className="hint">
        {selected.code} · <span className="status" data-status={selected.status}>{selected.status}</span>
        {selected.entidad && <> · Cliente: <strong>{selected.entidad.nombre}</strong></>}
      </p>

      <div className="section-head">
        <h3>Archivos</h3>
        <button type="button" className="btn-small" disabled={busyAnalyzeAll || selected.files.length === 0} onClick={() => void onAnalyzeAll()}>
          {busyAnalyzeAll ? 'Analizando…' : 'Analizar todos los archivos IA'}
        </button>
      </div>

      <form onSubmit={handleUpload} className="inline">
        <input name="file" type="file" accept=".pdf,.txt,.csv,.json,.jpg,.jpeg,.png,.webp,.tif,.tiff,.bmp,.gif,.avif" required />
        <select name="tipo" defaultValue="otro">
          {Object.entries(FILE_TIPO_LABELS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </select>
        <button type="submit" disabled={busyUpload}>{busyUpload ? 'Subiendo…' : 'Subir'}</button>
      </form>

      <ul>
        {selected.files.length === 0 && <p className="placeholder">Este proceso aún no tiene archivos.</p>}
        {selected.files.map(file => {
          const analysis = latestByFile.get(file.id);
          return (
            <li key={file.id} className="file-item">
              <button className="file-name" onClick={() => onDownload(file.id, file.originalName)} title="Descargar">
                <span className="ico">{extOfMime(file.mimeType)}</span>
                <span>{file.originalName}</span>
              </button>
              <span className="file-size">
                {file.tipo ? FILE_TIPO_LABELS[file.tipo] ?? file.tipo : ''} · {(file.sizeBytes / 1024).toFixed(1)} KB
                {file.converted && (
                  <span className="consumo-chip" title={`Almacenado como ${file.mimeType}; original ${file.originalMimeType ?? 'desconocido'}`}>
                    {extOfMime(file.originalMimeType)} → {extOfMime(file.mimeType)}
                  </span>
                )}
                {analysis && <span className="status" data-status={analysis.status}>{analysis.status}</span>}
              </span>
              <span className="file-actions">
                <button className="btn-small btn-ghost" onClick={() => onView(file.id, file.originalName)}>Ver</button>
                <button className="btn-small" disabled={busyAnalyze} onClick={() => void onAnalyze(file.id)}>
                  {busyAnalyze ? 'Analizando…' : 'Analizar IA'}
                </button>
                <label className="btn-small btn-ghost file-replace">
                  Recargar
                  <input type="file" hidden accept=".pdf,.txt,.csv,.json,.jpg,.jpeg,.png,.webp,.tif,.tiff,.bmp,.gif,.avif" onChange={event => { handleReplace(file, event.target.files); event.target.value = ''; }} />
                </label>
                {confirmId === file.id ? (
                  <>
                    <button className="btn-small btn-danger" onClick={() => { setConfirmId(null); void onDelete(file.id); }}>Confirmar</button>
                    <button className="btn-small btn-ghost" onClick={() => setConfirmId(null)}>Cancelar</button>
                  </>
                ) : (
                  <button className="btn-small btn-ghost" onClick={() => setConfirmId(file.id)}>Eliminar</button>
                )}
              </span>
            </li>
          );
        })}
      </ul>

      <div className="section-head">
        <h3>Resultados</h3>
        <button type="button" className="btn-small" disabled={busyConsolidate || selected.analyses.length === 0} onClick={() => void onConsolidate()}>
          {busyConsolidate ? 'Consolidando…' : 'Consolidar análisis del proceso'}
        </button>
      </div>
      {selected.analyses.length === 0
        ? <p className="placeholder">Aún no hay análisis en este proceso.</p>
        : groups.map(({ key, runs, primary }) => {
          const chosen = runs.find(run => run.id === selectedRun[key]) ?? primary;
          const file = key === 'process' ? null : selected.files.find(item => String(item.id) === key);
          const title = file ? file.originalName : 'Análisis del proceso';
          return (
            <section key={key} className="result-block">
              <div className="result-head">
                <strong className="result-title">{title}</strong>
                <span className="status" data-status={chosen.status}>{chosen.status}</span>
                {chosen.model && <span className="consumo-chip">{chosen.model}</span>}
                {(chosen.inputTokens != null || chosen.outputTokens != null) && (
                  <span className="consumo-chip">tok {chosen.inputTokens ?? 0} / {chosen.outputTokens ?? 0}</span>
                )}
                {fmtUsd(chosen.costUsd) && <span className="consumo-chip">{fmtUsd(chosen.costUsd)}</span>}
                {runs.length > 1 && <span className="consumo-chip">{runs.length} ejecuciones</span>}
              </div>
              {chosen.status === 'completed'
                ? <ResultadoIA data={chosen.validated ?? chosen.result ?? {}} onSave={datos => onValidar(chosen.id, datos)} />
                : <p className="notice">{chosen.error ?? `Estado: ${chosen.status}`}</p>}
              {runs.length > 1 && (
                <details className="history">
                  <summary>Historial de ejecuciones ({runs.length})</summary>
                  <ul className="history-list">
                    {runs.map(run => (
                      <li key={run.id} className={`history-item${run.id === chosen.id ? ' active' : ''}`}>
                        <span className="status" data-status={run.status}>{run.status}</span>
                        <span className="history-meta">
                          #{run.id}{run.model ? ` · ${run.model}` : ''} · tok {run.inputTokens ?? 0}/{run.outputTokens ?? 0}
                          {run.error ? ` · ${run.error}` : ''}
                        </span>
                        {run.id !== chosen.id && (
                          <button type="button" className="btn-small btn-ghost" onClick={() => setSelectedRun(state => ({ ...state, [key]: run.id }))}>
                            Ver esta
                          </button>
                        )}
                      </li>
                    ))}
                  </ul>
                </details>
              )}
            </section>
          );
        })}

      <div className="section-head"><h3>Datos estructurados</h3></div>
      <StructuredData data={structured} />
    </article>
  );
}
