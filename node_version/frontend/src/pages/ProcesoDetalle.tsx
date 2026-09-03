import { useState, useCallback, useRef, useEffect, type DragEvent } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { useProceso, useUpdateProceso, useAsignables, useUploadArchivo, useDeleteArchivo, fetchArchivoBlob, useAnalizarProceso, useAnalisisEstado, useAnalisisDatos, useConsumoProceso, useValidarProceso, useConsultarProceso, useConsultasProceso, useEntidadTiposDoc } from '../api/queries';
import { useAuth } from '../auth/useAuth';
import { estadoColor, formatDate, formatBytes, iconForMime } from '../components/format';
import ValidacionForm from '../components/ValidacionForm';
import ConsultasModal from '../components/ConsultasModal';
import ConsultasResult from '../components/ConsultasResult';
import type { ProcesoEstado } from '../api/types';

const ESTADOS: ProcesoEstado[] = ['creado', 'archivos_cargados', 'en_analisis', 'analizado', 'validado', 'completado', 'error', 'cancelado'];
// Fallback global cuando el proceso no tiene entidad (los tipos por entidad vienen del backend).
const ARCHIVO_TIPOS_GLOBAL = [
  { value: 'estado_cuenta', label: 'Estado de cuenta' },
  { value: 'anexo', label: 'Anexo' },
  { value: 'pagare', label: 'Pagaré' },
  { value: 'amortizacion', label: 'Amortización' },
  { value: 'vinculacion', label: 'Vinculación' },
  { value: 'identificacion', label: 'Identificación' },
  { value: 'otro', label: 'Otro' },
];
// Formatos aceptados por el input de archivos (incluye TIFF).
const ACCEPT_FILES = '.pdf,.jpg,.jpeg,.png,.tif,.tiff,.html,.xls,.xlsx';

interface UploadTask {
  file: File;
  tipo: string;
  progress: number;
  status: 'pending' | 'uploading' | 'done' | 'error';
  error?: string;
}

export default function ProcesoDetalle() {
  const { id } = useParams<{ id: string }>();
  const procesoId = id ? Number(id) : null;
  const { user } = useAuth();
  const tokenValid = !!user;

  const { data: proc, isLoading, error } = useProceso(procesoId, tokenValid);
  const { data: tiposEntidad } = useEntidadTiposDoc(proc?.entidad_id ?? null, tokenValid);
  const updateMut = useUpdateProceso();
  const deleteArchivoMut = useDeleteArchivo(procesoId ?? 0);
  const { data: asignables } = useAsignables(tokenValid);
  const uploadMut = useUploadArchivo(procesoId ?? 0);
  const analizarMut = useAnalizarProceso();
  const pollActive = !!proc && (proc.estado === 'en_analisis');
  const { data: estadoAnalisis } = useAnalisisEstado(procesoId, pollActive);
  const showDatosIa = !!proc && (proc.estado === 'analizado' || proc.estado === 'validado');
  const { data: analisisDatos } = useAnalisisDatos(procesoId, showDatosIa);
  const { data: consumoIa } = useConsumoProceso(procesoId, showDatosIa);
  const validarMut = useValidarProceso();
  const queryClient = useQueryClient();

  // Consultas de bots
  const hasConsultas = !!proc && (proc.estado === 'validado' || proc.estado === 'completado');
  const { data: consultasData } = useConsultasProceso(procesoId, hasConsultas);
  const consultarMut = useConsultarProceso();
  const [showConsultasModal, setShowConsultasModal] = useState(false);

  // Extraer personas de datos validados para el modal
  const consultaPersonas = (() => {
    if (!analisisDatos?.datos_validados) return [];
    const dv = analisisDatos.datos_validados as Record<string, unknown>;
    const result: Array<{ tipo: string; numero_id: string; nombre?: string }> = [];
    const deudor = dv.deudor as Record<string, unknown> | undefined;
    const codeudor = dv.codeudor as Record<string, unknown> | undefined;
    if (deudor?.numero_id) result.push({ tipo: 'deudor', numero_id: String(deudor.numero_id), nombre: String(deudor.nombre ?? '') });
    if (codeudor?.numero_id) result.push({ tipo: 'codeudor', numero_id: String(codeudor.numero_id), nombre: String(codeudor.nombre ?? '') });
    return result;
  })();

  // Refresca datos del proceso cuando el análisis termina
  useEffect(() => {
    if (estadoAnalisis && ['completado', 'fallido', 'cancelado'].includes(estadoAnalisis.estado)) {
      queryClient.invalidateQueries({ queryKey: ['proceso', procesoId] });
    }
  }, [estadoAnalisis, queryClient, procesoId]);

  const [tasks, setTasks] = useState<UploadTask[]>([]);
  const [defaultTipo, setDefaultTipo] = useState('otro');
  const [dragover, setDragover] = useState(false);
  const fileInput = useRef<HTMLInputElement>(null);

  // Tipos de documento a ofrecer: los de la entidad del proceso si existen; si no, el fallback global.
  const tiposDoc = (tiposEntidad && tiposEntidad.length > 0)
    ? tiposEntidad.map((t) => ({ value: t.value, label: t.label }))
    : ARCHIVO_TIPOS_GLOBAL;

  // Cuando llegan los tipos, fijar el valor por defecto al primero disponible.
  useEffect(() => {
    if (tiposDoc.length > 0 && !tiposDoc.some((t) => t.value === defaultTipo)) {
      setDefaultTipo(tiposDoc[0].value);
    }
  }, [tiposDoc, defaultTipo]);

  // previewId state removed (usando links directos)

  const canEdit = user?.rol === 'admin' || user?.rol === 'supervisor';

  const addFiles = useCallback((files: FileList | File[]) => {
    const arr = Array.from(files);
    if (arr.length === 0) return;
    const newTasks: UploadTask[] = arr.map((f) => ({ file: f, tipo: defaultTipo, progress: 0, status: 'pending' }));
    setTasks((prev) => [...prev, ...newTasks]);

    // Subida secuencial
    (async () => {
      for (let i = 0; i < newTasks.length; i++) {
        const idx = tasks.length + i;
        setTasks((prev) => prev.map((t, j) => (j === idx ? { ...t, status: 'uploading' } : t)));
        try {
          await uploadMut.mutateAsync(
            { file: newTasks[i].file, tipo: newTasks[i].tipo },
            {
              onSuccess: () => setTasks((prev) => prev.map((t, j) => (j === idx ? { ...t, status: 'done', progress: 100 } : t))),
            },
          );
        } catch (e) {
          // Extraer el mensaje real del response de axios (error.response.data.message)
          const axiosErr = e as { response?: { data?: { message?: string } }; message?: string };
          const errMsg = axiosErr.response?.data?.message ?? axiosErr.message ?? 'Error al subir archivo';
          setTasks((prev) => prev.map((t, j) => (j === idx ? { ...t, status: 'error', error: errMsg } : t)));
        }
      }
    })();
  }, [defaultTipo, tasks.length, uploadMut]);

  const onDrop = (e: DragEvent) => {
    e.preventDefault();
    setDragover(false);
    if (e.dataTransfer.files.length) addFiles(e.dataTransfer.files);
  };

  const onSelectChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files?.length) addFiles(e.target.files);
  };

  const onEstadoChange = (nuevoEstado: ProcesoEstado) => {
    if (!procesoId) return;
    updateMut.mutate({ id: procesoId, data: { estado: nuevoEstado } });
  };

  const onAsignarChange = (asignadoId: string) => {
    if (!procesoId) return;
    updateMut.mutate({ id: procesoId, data: { asignado_a: asignadoId ? Number(asignadoId) : null } });
  };

  const onEliminar = (archivoId: number) => {
    if (!confirm('¿Eliminar este archivo del proceso?')) return;
    deleteArchivoMut.mutate(archivoId);
  };

  const onAnalizar = () => {
    if (!procesoId) return;
    if (!confirm('¿Analizar todos los archivos de este proceso con IA?')) return;
    analizarMut.mutate(procesoId);
  };

  // Preview: descarga blob con auth header, abre en nueva pestaña (sin token en URL)
  const onPreview = async (archivoId: number) => {
    try {
      const { url, mime } = await fetchArchivoBlob(archivoId);
      if (mime.startsWith('image/') || mime === 'application/pdf' || mime === 'text/html') {
        window.open(url, '_blank');
      } else {
        // No se puede preview inline → descargar
        const a = document.createElement('a');
        a.href = url;
        a.download = '';
        a.click();
      }
      // Revocar después de 60s (tiempo suficiente para que la pestaña cargue)
      setTimeout(() => URL.revokeObjectURL(url), 60_000);
    } catch { /* ignore */ }
  };

  // Download: igual con blob, fuerza descarga
  const onDownload = async (archivoId: number, nombreOriginal: string) => {
    try {
      const { url } = await fetchArchivoBlob(archivoId);
      const a = document.createElement('a');
      a.href = url;
      a.download = nombreOriginal;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      setTimeout(() => URL.revokeObjectURL(url), 5000);
    } catch { /* ignore */ }
  };

  if (isLoading) return <div className="page-card text-center text-muted py-5">Cargando proceso…</div>;
  if (error || !proc) return <div className="page-card"><div className="alert alert-danger">Proceso no encontrado.</div><Link to="/procesos" className="btn btn-primary">Volver</Link></div>;

  return (
    <>
      <div className="d-flex justify-content-between align-items-center mb-3">
        <div className="d-flex align-items-center gap-3">
          <div>
            <Link to="/procesos" className="text-muted text-decoration-none small">← Volver a procesos</Link>
            <h2 className="h4 mb-0" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>{proc.codigo}</h2>
            <p className="text-muted mb-0" style={{ fontSize: '.85rem' }}>{proc.tipo} · creado {formatDate(proc.created_at)}</p>
          </div>
          {/* Botón Analizar — visible si hay archivos y el proceso no está completado/cancelado */}
          {(proc.total_archivos > 0 || proc.archivos.length > 0) && !['completado', 'cancelado'].includes(proc.estado) && proc.estado !== 'en_analisis' && (
            <button className={`btn btn-sm ${['creado', 'archivos_cargados'].includes(proc.estado) ? 'btn-success' : 'btn-outline-success'}`} onClick={onAnalizar} disabled={analizarMut.isPending}>
              {analizarMut.isPending
                ? <><span className="spinner-border spinner-border-sm me-1" /> Analizando…</>
                : <><i className="bi bi-robot me-1" /> {['creado', 'archivos_cargados'].includes(proc.estado) ? 'Analizar con IA' : 'Re-analizar'}</>}
            </button>
          )}
          {/* Indicador de estado de análisis */}
          {proc.estado === 'en_analisis' && estadoAnalisis && (
            <span className="badge bg-warning">
              {estadoAnalisis.error_mensaje || (estadoAnalisis.estado === 'procesando' ? 'Procesando con Gemini…' : 'En cola…')}
            </span>
          )}
          {proc.estado === 'analizado' && (
            <span className="badge bg-info">Análisis listo</span>
          )}
          {proc.estado === 'validado' && (
            <>
              <button className="btn btn-primary btn-sm" onClick={() => setShowConsultasModal(true)} disabled={consultarMut.isPending}>
                {consultarMut.isPending
                  ? <><span className="spinner-border spinner-border-sm me-1" /> Consultando…</>
                  : <><i className="bi bi-search me-1" /> {consultasData && consultasData.length > 0 ? 'Re-consultar' : 'Consultar en plataformas'}</>}
              </button>
            </>
          )}
          {proc.estado === 'error' && (
            <span className="badge bg-danger">Error en análisis</span>
          )}
        </div>
        <span className={`badge bg-${estadoColor(proc.estado)} fs-6`}>{proc.estado.replace(/_/g, ' ')}</span>
      </div>

      <div className="row g-3">
        <div className="col-12 col-xl-8">
          {/* Uploader */}
          <div className="page-card">
            <h3 className="h6 mb-3" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>
              <i className="bi bi-cloud-upload" /> Archivos
            </h3>
            {proc.entidad && tiposEntidad && tiposEntidad.length > 0 && (
              <div className="alert alert-light border small mb-2 py-2">
                <strong>Documentos esperados de {proc.entidad}:</strong>{' '}
                {tiposEntidad.map((t) => {
                  const cargado = proc.archivos.some((a) => a.tipo === t.value);
                  return (
                    <span key={t.value} className={`badge me-1 ${cargado ? 'bg-success' : (t.obligatorio ? 'bg-secondary' : 'bg-light text-secondary')}`}>
                      {cargado ? '✓ ' : ''}{t.label}{t.obligatorio && !cargado ? ' *' : ''}
                    </span>
                  );
                })}
              </div>
            )}
            <div className="mb-2">
              <label className="form-label small me-2">Tipo por defecto al arrastrar:</label>
              <select className="form-select form-select-sm d-inline-block" style={{ width: 'auto' }} value={defaultTipo} onChange={(e) => setDefaultTipo(e.target.value)}>
                {tiposDoc.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
              </select>
            </div>
            <div
              className={`dropzone mb-3 ${dragover ? 'dragover' : ''}`}
              onDragOver={(e) => { e.preventDefault(); setDragover(true); }}
              onDragLeave={() => setDragover(false)}
              onDrop={onDrop}
              onClick={() => fileInput.current?.click()}
              style={{ padding: '2rem 1rem' }}
            >
              <i className="bi bi-cloud-arrow-up fs-1 d-block mb-2" />
              <strong>Arrastra archivos aquí o haz clic para seleccionar</strong>
              <br /><span className="small text-muted">PDF, JPG, PNG, TIFF, HTML, Excel · Multi-archivo</span>
              <input ref={fileInput} type="file" multiple accept={ACCEPT_FILES} className="d-none" onChange={onSelectChange} />
            </div>

            {/* Cola de subidas */}
            {tasks.length > 0 && (
              <ul className="list-group list-group-flush mb-3">
                {tasks.map((t, i) => (
                  <li key={i} className="list-group-item d-flex align-items-center gap-2 px-0">
                    <i className={`bi bi-${iconForMime(t.file.type)}`} />
                    <span className="flex-grow-1 text-truncate" style={{ maxWidth: 300 }}>{t.file.name}</span>
                    <span className="badge bg-light text-secondary small">{formatBytes(t.file.size)}</span>
                    {t.status === 'uploading' && (
                      <div className="progress" style={{ width: 120, height: 6 }}>
                        <div className="progress-bar" style={{ width: `${t.progress}%` }} />
                      </div>
                    )}
                    {t.status === 'done' && <i className="bi bi-check-circle-fill text-success" />}
                    {t.status === 'error' && <span className="text-danger small" title={t.error}>{t.error ?? 'Error'}</span>}
                  </li>
                ))}
              </ul>
            )}

            {/* Lista de archivos subidos */}
            <h4 className="h6 mt-4 mb-2" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>
              {proc.archivos.length} archivo{proc.archivos.length !== 1 ? 's' : ''} subido{proc.archivos.length !== 1 ? 's' : ''}
            </h4>
            {proc.archivos.length === 0 ? (
              <div className="text-muted text-center py-3">
                <i className="bi bi-inbox fs-2 d-block mb-1" style={{ color: 'var(--by-gris-claro)' }} />
                <span className="small">Aún no hay archivos. Sube uno para empezar.</span>
              </div>
            ) : (
              <table className="table table-sm align-middle">
                <thead><tr><th>Nombre</th><th>Tipo</th><th>Tamaño</th><th></th></tr></thead>
                <tbody>
                  {proc.archivos.map((a) => (
                    <tr key={a.id}>
                      <td><i className={`bi bi-${iconForMime(a.mime_type)} me-1`} />{a.nombre_original}</td>
                      <td><span className="badge bg-light text-secondary">{a.tipo.replace(/_/g, ' ')}</span></td>
                      <td className="small text-muted">{formatBytes(a.tamanio_bytes)}</td>
                      <td>
                        <button className="btn btn-sm btn-outline-primary" onClick={() => onPreview(a.id)} title="Vista previa"><i className="bi bi-eye" /></button>
                        <button className="btn btn-sm btn-outline-secondary ms-1" onClick={() => onDownload(a.id, a.nombre_original)} title="Descargar"><i className="bi bi-download" /></button>
                        {canEdit && <button className="btn btn-sm btn-outline-danger ms-1" onClick={() => onEliminar(a.id)} title="Eliminar"><i className="bi bi-trash" /></button>}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>

          {/* Consumo IA (tokens + costo estimado del proceso) */}
          {showDatosIa && consumoIa && consumoIa.analisis_count > 0 && (
            <div className="page-card mt-3">
              <div className="d-flex flex-wrap align-items-center gap-3">
                <span className="fw-semibold" style={{ color: 'var(--by-azul)' }}><i className="bi bi-cpu me-1" /> Consumo IA</span>
                {analisisDatos?.modelo && <span className="badge bg-light text-secondary">{analisisDatos.modelo}</span>}
                <span className="small text-muted">Entrada: <strong>{consumoIa.tokens_entrada.toLocaleString()}</strong> tok</span>
                <span className="small text-muted">Salida: <strong>{consumoIa.tokens_salida.toLocaleString()}</strong> tok</span>
                <span className="small text-muted">Total: <strong>{consumoIa.tokens_total.toLocaleString()}</strong> tok</span>
                <span className="ms-auto badge bg-success" title={`${consumoIa.analisis_count} análisis · $${consumoIa.precios.entrada}/$${consumoIa.precios.salida} por 1M`}>
                  ≈ US${consumoIa.costo_estimado_usd.toFixed(4)}
                </span>
              </div>
            </div>
          )}

          {/* Validación IA */}
          {showDatosIa && analisisDatos && (
            <ValidacionForm
              datos={analisisDatos.datos_originales as Record<string, unknown>}
              datos_validados={analisisDatos.datos_validados as Record<string, unknown> | null}
              estado={proc.estado}
              onGuardar={(datos_validados) => {
                if (procesoId) validarMut.mutate({ procesoId, datos_validados });
              }}
              saving={validarMut.isPending}
            />
          )}

          {/* Consultas de bots */}
          {hasConsultas && consultasData && consultasData.length > 0 && (
            <ConsultasResult consultas={consultasData} />
          )}

          {/* Modal de consultas */}
          {showConsultasModal && (
            <ConsultasModal
              personas={consultaPersonas}
              onCancel={() => setShowConsultasModal(false)}
              onConfirm={(bots) => {
                if (procesoId) {
                  consultarMut.mutate(
                    { procesoId, bots },
                    { onSuccess: () => setShowConsultasModal(false) },
                  );
                }
              }}
              loading={consultarMut.isPending}
            />
          )}
        </div>

        {/* Lateral derecho: metadatos + historial */}
        <div className="col-12 col-xl-4">
          <div className="page-card mb-3">
            <h3 className="h6 mb-3" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>Información</h3>
            <dl className="row mb-0" style={{ fontSize: '.9rem' }}>
              <dt className="col-4 text-muted fw-normal">Estado</dt>
              <dd className="col-8">
                {canEdit ? (
                  <select className="form-select form-select-sm" value={proc.estado} onChange={(e) => onEstadoChange(e.target.value as ProcesoEstado)}>
                    {ESTADOS.map((e) => <option key={e} value={e}>{e.replace(/_/g, ' ')}</option>)}
                  </select>
                ) : <span className={`badge bg-${estadoColor(proc.estado)}`}>{proc.estado.replace(/_/g, ' ')}</span>}
              </dd>
              <dt className="col-4 text-muted fw-normal mt-2">Prioridad</dt>
              <dd className="col-8 mt-2">{proc.prioridad}</dd>
              <dt className="col-4 text-muted fw-normal mt-2">Asignado a</dt>
              <dd className="col-8 mt-2">
                {canEdit ? (
                  <select className="form-select form-select-sm" value={proc.asignado_a_id ?? ''} onChange={(e) => onAsignarChange(e.target.value)}>
                    <option value="">Sin asignar</option>
                    {asignables?.map((u) => <option key={u.id} value={u.id}>{u.nombre_completo} ({u.rol})</option>)}
                  </select>
                ) : (proc.asignado_a ?? 'Sin asignar')}
              </dd>
              <dt className="col-4 text-muted fw-normal mt-2">Creado por</dt>
              <dd className="col-8 mt-2">{proc.creado_por ?? '—'}</dd>
              <dt className="col-4 text-muted fw-normal mt-2">Notas</dt>
              <dd className="col-8 mt-2">{proc.notas ?? '—'}</dd>
            </dl>
          </div>

          <div className="page-card">
            <h3 className="h6 mb-3" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>Historial</h3>
            {proc.historial.length === 0 ? <p className="text-muted small mb-0">Sin actividad.</p> : (
              <ul className="list-unstyled mb-0">
                {proc.historial.map((h) => (
                  <li key={h.id} className="d-flex gap-2 mb-2" style={{ fontSize: '.85rem' }}>
                    <i className="bi bi-circle-fill text-primary small mt-1" style={{ fontSize: '.5rem' }} />
                    <div>
                      <span className="fw-semibold">{h.accion.replace(/_/g, ' ')}</span>
                      {h.descripcion && <div className="text-muted">{h.descripcion}</div>}
                      <div className="text-muted" style={{ fontSize: '.75rem' }}>{formatDate(h.fecha)} · {h.usuario ?? 'sistema'}</div>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      </div>
    </>
  );
}