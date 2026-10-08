import { useState } from 'react';
import type { FormEvent } from 'react';
import { Check, ChevronsDownUp, ChevronsUpDown, Eye, RefreshCw, Sparkles, Trash2, Upload, UploadCloud, AlertTriangle, X } from 'lucide-react';
import { FILE_TIPO_LABELS } from '../types';
import type { Detail, FileItem, Structured } from '../types';
import { statusLabel } from '../status';
import { Collapsible } from './Collapsible';
import { Modal } from './Modal';
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

const ACCEPT = '.pdf,.txt,.csv,.json,.jpg,.jpeg,.png,.webp,.tif,.tiff,.bmp,.gif,.avif';

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
  const [pendingDelete, setPendingDelete] = useState<FileItem | null>(null);
  const [selectedRun, setSelectedRun] = useState<Record<string, number>>({});
  const [uploadName, setUploadName] = useState('');
  const [openResults, setOpenResults] = useState<Record<string, boolean>>({});
  const [structuredOpen, setStructuredOpen] = useState(false);

  const handleUpload = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const formEl = event.currentTarget;
    const form = new FormData(formEl);
    const tipo = String(form.get('tipo') ?? '').trim() || undefined;
    void onUpload(form, tipo).then(() => { formEl.reset(); setUploadName(''); });
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

  const allOpen = groups.length > 0 && groups.every(group => openResults[group.key]);

  return (
    <article>
      <h2>{selected.title}</h2>
      <p className="hint">
        {selected.code} · <span className="status" data-status={selected.status}>{statusLabel(selected.status)}</span>
        {selected.entidad && <> · Cliente: <strong>{selected.entidad.nombre}</strong></>}
        {selected.deudorNombre && <> · Deudor: <strong>{selected.deudorNombre}</strong>{selected.deudorDocumento ? ` (${selected.deudorDocumento})` : ''}</>}
      </p>

      <section className="upload-card">
        <div className="section-head">
          <h3>Subir archivo</h3>
        </div>
        <form onSubmit={handleUpload} className="upload-form">
          <label className="dropzone">
            <UploadCloud size={22} aria-hidden />
            <span className="dropzone-hint">Haz clic o arrastra un archivo aquí</span>
            <span className="dropzone-name">{uploadName || 'Ningún archivo seleccionado'}</span>
            <input
              name="file"
              type="file"
              accept={ACCEPT}
              required
              onChange={event => setUploadName(event.target.files?.[0]?.name ?? '')}
            />
          </label>
          <div className="upload-controls">
            <select name="tipo" defaultValue="otro" aria-label="Tipo de archivo">
              {Object.entries(FILE_TIPO_LABELS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
            </select>
            <button type="submit" disabled={busyUpload}>
              <Upload size={16} aria-hidden />
              {busyUpload ? 'Subiendo…' : 'Subir'}
            </button>
          </div>
        </form>
      </section>

      <div className="section-head">
        <h3>Archivos</h3>
        <button type="button" className="btn-small" disabled={busyAnalyzeAll || selected.files.length === 0} onClick={() => void onAnalyzeAll()}>
          <Sparkles size={16} aria-hidden />
          {busyAnalyzeAll ? 'Analizando…' : 'Analizar todos los archivos IA'}
        </button>
      </div>

      <ul className="file-list">
        {selected.files.length === 0 && <p className="placeholder">Este proceso aún no tiene archivos.</p>}
        {selected.files.map(file => {
          const analysis = latestByFile.get(file.id);
          return (
            <li key={file.id} className="file-item">
              <button className="file-name" onClick={() => onDownload(file.id, file.originalName)} title="Descargar">
                <span className="ico">{extOfMime(file.mimeType)}</span>
                <span className="file-label">{file.originalName}</span>
              </button>
              <span className="file-meta">
                <span>{file.tipo ? FILE_TIPO_LABELS[file.tipo] ?? file.tipo : ''} · {(file.sizeBytes / 1024).toFixed(1)} KB</span>
                {file.converted && (
                  <span className="consumo-chip" title={`Almacenado como ${file.mimeType}; original ${file.originalMimeType ?? 'desconocido'}`}>
                    {extOfMime(file.originalMimeType)} → {extOfMime(file.mimeType)}
                  </span>
                )}
                {analysis && <span className="status" data-status={analysis.status}>{statusLabel(analysis.status)}</span>}
              </span>
              <span className="file-actions">
                <button type="button" className="icon-btn" title="Ver" onClick={() => onView(file.id, file.originalName)}>
                  <Eye size={16} aria-hidden />
                </button>
                <button type="button" className="icon-btn primary" title="Analizar IA" disabled={busyAnalyze} onClick={() => void onAnalyze(file.id)}>
                  <Sparkles size={16} aria-hidden />
                </button>
                <label className="icon-btn" title="Recargar (reemplazar)">
                  <RefreshCw size={16} aria-hidden />
                  <input type="file" hidden accept={ACCEPT} onChange={event => { handleReplace(file, event.target.files); event.target.value = ''; }} />
                </label>
                {confirmId === file.id ? (
                  <span className="confirm-group">
                    <button type="button" className="icon-btn danger" title="Confirmar eliminación" onClick={() => { setConfirmId(null); setPendingDelete(file); }}>
                      <Check size={16} aria-hidden />
                    </button>
                    <button type="button" className="icon-btn" title="Cancelar" onClick={() => setConfirmId(null)}>
                      <X size={16} aria-hidden />
                    </button>
                  </span>
                ) : (
                  <button type="button" className="icon-btn danger" title="Eliminar" onClick={() => setConfirmId(file.id)}>
                    <Trash2 size={16} aria-hidden />
                  </button>
                )}
              </span>
            </li>
          );
        })}
      </ul>

      <div className="section-head">
        <h3>Resultados</h3>
        <div className="toolbar">
          {groups.length > 0 && (
            <button type="button" className="icon-btn" title={allOpen ? 'Contraer todo' : 'Expandir todo'} onClick={() => setOpenResults(allOpen ? {} : Object.fromEntries(groups.map(group => [group.key, true])))}>
              {allOpen ? <ChevronsDownUp size={16} aria-hidden /> : <ChevronsUpDown size={16} aria-hidden />}
            </button>
          )}
          <button type="button" className="btn-small" disabled={busyConsolidate || selected.analyses.length === 0} onClick={() => void onConsolidate()}>
            {busyConsolidate ? 'Consolidando…' : 'Consolidar análisis del proceso'}
          </button>
        </div>
      </div>

      {selected.analyses.length === 0
        ? <p className="placeholder">Aún no hay análisis en este proceso.</p>
        : groups.map(({ key, runs, primary }) => {
          const chosen = runs.find(run => run.id === selectedRun[key]) ?? primary;
          const file = key === 'process' ? null : selected.files.find(item => String(item.id) === key);
          const title = file ? file.originalName : 'Análisis del proceso';
          return (
            <Collapsible
              key={key}
              className="result-block"
              open={Boolean(openResults[key])}
              onToggle={() => setOpenResults(state => ({ ...state, [key]: !state[key] }))}
              title={title}
              subtitle={(
                <>
                  <span className="status" data-status={chosen.status}>{statusLabel(chosen.status)}</span>
                  {chosen.model && <span className="consumo-chip">{chosen.model}</span>}
                  {(chosen.inputTokens != null || chosen.outputTokens != null) && (
                    <span className="consumo-chip">tok {chosen.inputTokens ?? 0} / {chosen.outputTokens ?? 0}</span>
                  )}
                  {fmtUsd(chosen.costUsd) && <span className="consumo-chip">{fmtUsd(chosen.costUsd)}</span>}
                  {runs.length > 1 && <span className="consumo-chip">{runs.length} ejecuciones</span>}
                </>
              )}
            >
              {chosen.status === 'completed'
                ? <ResultadoIA data={chosen.validated ?? chosen.result ?? {}} onSave={datos => onValidar(chosen.id, datos)} />
                : <p className="notice">{chosen.error ?? `Estado: ${statusLabel(chosen.status)}`}</p>}
              {runs.length > 1 && (
                <details className="history">
                  <summary>Historial de ejecuciones ({runs.length})</summary>
                  <ul className="history-list">
                    {runs.map(run => (
                      <li key={run.id} className={`history-item${run.id === chosen.id ? ' active' : ''}`}>
                        <span className="status" data-status={run.status}>{statusLabel(run.status)}</span>
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
            </Collapsible>
          );
        })}

      <Collapsible
        className="structured-block"
        open={structuredOpen}
        onToggle={() => setStructuredOpen(state => !state)}
        title="Datos estructurados"
      >
        <StructuredData data={structured} />
      </Collapsible>

      {pendingDelete && (
        <Modal title="Eliminar archivo" onClose={() => setPendingDelete(null)}>
          <div className="warning-box">
            <AlertTriangle size={30} aria-hidden />
            <p>
              ¿Seguro que deseas eliminar <strong>{pendingDelete.originalName}</strong>?
              Esta acción no se puede deshacer.
            </p>
          </div>
          <div className="modal-actions">
            <button type="button" className="btn-ghost" onClick={() => setPendingDelete(null)}>Cancelar</button>
            <button
              type="button"
              className="btn-danger"
              onClick={() => { const target = pendingDelete; setPendingDelete(null); void onDelete(target.id); }}
            >
              Eliminar definitivamente
            </button>
          </div>
        </Modal>
      )}
    </article>
  );
}
