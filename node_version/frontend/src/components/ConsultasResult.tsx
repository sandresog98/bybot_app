import { useState } from 'react';
import type { ProcesosConsulta } from '../api/types';
import { useConsultaDetalle } from '../api/queries';

interface Props {
  consultas: ProcesosConsulta[];
}

const BOT_LABELS: Record<string, string> = {
  fosiga: 'Fosiga (ADRES)',
  ruaf: 'RUAF (SISPRO)',
  rues: 'RUES (Reg. Mercantil)',
  simpleco: 'Simple.co (PILA)',
};

const BOT_COLORS: Record<string, string> = {
  fosiga: '#0d6efd',
  ruaf: '#198754',
  rues: '#6f42c1',
  simpleco: '#fd7e14',
};

// Config de presentación por estado canónico de procesos_consultas
const STATUS: Record<string, { icon: string; cls: string; badge: string; texto: string }> = {
  exitoso: { icon: 'bi-check-circle-fill', cls: 'text-success', badge: 'bg-success', texto: 'Consulta con información exitosa' },
  sin_pagos: { icon: 'bi-info-circle-fill', cls: 'text-warning', badge: 'bg-warning text-dark', texto: 'Consultó, sin información (continúa)' },
  fallido: { icon: 'bi-x-circle-fill', cls: 'text-danger', badge: 'bg-danger', texto: 'Error consultando' },
  pendiente: { icon: 'bi-clock', cls: 'text-muted', badge: 'bg-secondary', texto: 'En cola…' },
  procesando: { icon: 'bi-arrow-repeat', cls: 'text-primary', badge: 'bg-primary', texto: 'Consultando…' },
};

function personaLabel(tipo: string) {
  return tipo === 'deudor' ? 'Deudor' : 'Codeudor';
}

function SimplecoDetalle({ consulta }: { consulta: ProcesosConsulta }) {
  const { data } = useConsultaDetalle(consulta.consulta_id != null ? consulta.consulta_id : null, true);
  const d = (data?.datos ?? null) as Record<string, unknown> | null;
  if (!d) {
    return <div className="text-muted small">Sin datos extraídos para este comprobante.</div>;
  }

  const rows: Array<[string, string]> = [];
  const add = (label: string, v: unknown) => {
    if (v !== null && v !== undefined && String(v) !== '') rows.push([label, String(v)]);
  };
  add('Periodo', d.periodo_cotizacion || `${d.periodo_anio ?? ''}${d.periodo_mes ? '-' + d.periodo_mes : ''}`);
  add('Empresa', d.empresa);
  add('NIT', d.nit_entidad);
  add('Tipo planilla', d.tipo_planilla);
  add('Número planilla', d.numero_planilla);
  add('Fecha comprobante', d.fecha_comprobante);
  add('Empleado', d.empleado);
  add('Cédula', d.cedula);
  add('Documento', d.documento_identificacion);
  add('Administradora', d.nombre_entidad || d.tipo_admin);
  add('Código entidad', d.codigo_entidad);
  add('Observaciones', d.observaciones as unknown);

  if (rows.length === 0) return null;

  return (
    <div className="table-responsive mt-2">
      <table className="table table-sm table-bordered mb-0" style={{ fontSize: '0.8rem' }}>
        <tbody>
          {rows.map(([k, v]) => (
            <tr key={k}>
              <th className="text-muted" style={{ width: '40%', background: 'var(--by-fondo-soft)' }}>{k}</th>
              <td>{v}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export default function ConsultasResult({ consultas }: Props) {
  const [openId, setOpenId] = useState<number | null>(null);

  if (consultas.length === 0) return null;

  const personas = [...new Set(consultas.map((c) => c.persona_tipo))];

  const groups = personas.map((pt) => ({
    persona: pt,
    items: consultas.filter((c) => c.persona_tipo === pt).sort((a, b) => a.orden_ejecucion - b.orden_ejecucion),
  }));

  return (
    <div className="page-card mt-3">
      <h3 className="h6 mb-3" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>
        <i className="bi bi-search" /> Resultados de consultas externas
      </h3>

      {groups.map((g) => (
        <div key={g.persona} className="mb-3">
          <h4 className="small fw-semibold mb-2" style={{ color: 'var(--by-azul-marino)' }}>
            {personaLabel(g.persona)} — <code className="small">{g.items[0]?.numero_id}</code>
          </h4>

          <div className="row g-2">
            {g.items.map((c) => {
              const resumen = c.resultado_resumen as Record<string, unknown> | null;
              const st = STATUS[c.estado] || STATUS.pendiente;
              const color = BOT_COLORS[c.bot] || '#6c757d';
              const isPending = c.estado === 'pendiente' || c.estado === 'procesando';
              const isOpen = openId === c.id;
              const showDatos = c.estado === 'exitoso' && c.consulta_tabla && c.consulta_id != null;

              return (
                <div key={c.id} className="col-12 col-md-6 col-lg-4">
                  <div className="card h-100" style={{ borderLeft: `4px solid ${color}` }}>
                    <div className="card-body py-2 px-3">
                      <div className="d-flex align-items-center gap-2 mb-1">
                        <i className={`${st.icon} ${st.cls}`} style={c.estado === 'procesando' ? { animation: 'spin 2s linear infinite' } : {}} />
                        <span className="fw-semibold small">{BOT_LABELS[c.bot] || c.bot}</span>
                        <span className={`badge ms-auto ${st.badge}`} style={{ fontSize: '0.65rem' }}>{st.texto}</span>
                      </div>

                      {isPending ? (
                        <div className="text-muted small">
                          {resumen?.mensaje ? String(resumen.mensaje) : st.texto}
                        </div>
                      ) : (
                        <div className="small">
                          {Boolean(resumen?.estado) && <div className="text-muted">Bot: {String(resumen!.estado)}</div>}
                          {Boolean(resumen?.motivo) && <div className="text-muted text-truncate" title={String(resumen!.motivo)}>{String(resumen!.motivo)}</div>}
                        </div>
                      )}

                      {showDatos && (
                        <button
                          className="btn btn-sm btn-outline-secondary mt-2"
                          onClick={() => setOpenId(isOpen ? null : c.id)}
                        >
                          <i className={`bi ${isOpen ? 'bi-chevron-up' : 'bi-chevron-down'} me-1`} />
                          {isOpen ? 'Ocultar' : 'Ver datos extraídos'}
                        </button>
                      )}
                      {isOpen && showDatos && <SimplecoDetalle consulta={c} />}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      ))}

      <style>{`
        @keyframes spin {
          from { transform: rotate(0deg); }
          to { transform: rotate(360deg); }
        }
      `}</style>
    </div>
  );
}