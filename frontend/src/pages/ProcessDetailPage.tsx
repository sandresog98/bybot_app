import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { api } from '../api';
import { ProcessDetail } from '../components/ProcessDetail';
import { LiquidacionPanel } from '../components/LiquidacionPanel';
import { useApp } from '../context';
import type { Detail, Structured } from '../types';

export function ProcessDetailPage() {
  const { id } = useParams();
  const processId = Number(id);
  const { toast } = useApp();
  const navigate = useNavigate();
  const [detail, setDetail] = useState<Detail | null>(null);
  const [structured, setStructured] = useState<Structured | null>(null);
  const [busy, setBusy] = useState<Record<string, boolean>>({});

  const load = async () => {
    const [process, data] = await Promise.all([api.getProcess(processId), api.getStructured(processId)]);
    setDetail(process);
    setStructured(data);
  };

  useEffect(() => {
    load().catch(() => toast('error', 'No se pudo cargar el proceso.'));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [processId]);

  const run = async (key: string, fn: () => Promise<void>) => {
    setBusy(b => ({ ...b, [key]: true }));
    try { await fn(); }
    catch (error) { toast('error', error instanceof Error ? error.message : 'Ocurrió un error.'); }
    finally { setBusy(b => ({ ...b, [key]: false })); }
  };

  const upload = (form: FormData, tipo?: string) =>
    run('upload', async () => { await api.uploadFile(processId, form, tipo); toast('success', 'Archivo subido.'); await load(); });
  const replace = (fileId: number, form: FormData, tipo?: string) =>
    run('replace', async () => { await api.replaceFile(processId, fileId, form, tipo); toast('success', 'Archivo reemplazado.'); await load(); });
  const remove = (fileId: number) =>
    run('delete', async () => { await api.deleteFile(fileId); toast('success', 'Archivo eliminado.'); await load(); });
  const analyze = (fileId?: number) =>
    run('analyze', async () => { await api.analyze(processId, fileId); toast('success', 'Análisis encolado.'); await load(); });
  const analyzeAll = () =>
    run('analyzeAll', async () => {
      const result = await api.analyzeAll(processId);
      toast('success', `Encolados ${result.queued} de ${result.total} archivos (${result.skipped} ya procesados).`);
      await load();
    });
  const consolidate = () =>
    run('consolidate', async () => { await api.consolidate(processId); toast('success', 'Consolidación encolada.'); await load(); });
  const view = (fileId: number) => window.open(api.viewUrl(fileId), '_blank', 'noopener,noreferrer');
  const download = (fileId: number, name: string) =>
    run('download', async () => {
      const response = await fetch(api.downloadUrl(fileId), { credentials: 'include' });
      if (!response.ok) throw new Error('No se pudo descargar el archivo.');
      const url = URL.createObjectURL(await response.blob());
      const link = document.createElement('a');
      link.href = url; link.download = name; link.click();
      URL.revokeObjectURL(url);
    });
  const validar = (analysisId: number, datos: unknown) =>
    run('validar', async () => { await api.validarAnalysis(analysisId, datos); toast('success', 'Validación guardada.'); await load(); });

  return (
    <section className="page">
      <div className="detail-toolbar">
        <button type="button" className="btn-ghost btn-small" onClick={() => navigate('/procesos')}>← Volver a Procesos</button>
      </div>
      <ProcessDetail
        selected={detail}
        structured={structured}
        busyUpload={!!busy['upload']}
        busyAnalyze={!!busy['analyze']}
        busyAnalyzeAll={!!busy['analyzeAll']}
        busyConsolidate={!!busy['consolidate']}
        onUpload={upload}
        onAnalyze={analyze}
        onAnalyzeAll={analyzeAll}
        onConsolidate={consolidate}
        onDelete={remove}
        onReplace={replace}
        onView={view}
        onDownload={download}
        onValidar={validar}
      />
      <section className="section-card">
        <div className="section-head"><h3>Liquidación (borrador)</h3></div>
        <LiquidacionPanel processId={processId} />
      </section>
    </section>
  );
}
