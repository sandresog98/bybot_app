import type { ProcesosConsulta } from '../api/types';

interface Props {
  consultas: ProcesosConsulta[];
}

const BOT_LABELS: Record<string, string> = {
  fosiga: 'Fosiga (ADRES)',
  ruaf: 'RUAF (SISPRO)',
  rues: 'RUES (Reg. Mercantil)',
};

const BOT_COLORS: Record<string, string> = {
  fosiga: '#0d6efd',
  ruaf: '#198754',
  rues: '#6f42c1',
};

function botIcon(estado: string) {
  switch (estado) {
    case 'exitoso': return 'bi-check-circle-fill text-success';
    case 'fallido': return 'bi-x-circle-fill text-danger';
    case 'pendiente': return 'bi-clock text-muted';
    case 'procesando': return 'bi-arrow-repeat text-primary spin';
    default: return 'bi-question-circle text-muted';
  }
}

function personaLabel(tipo: string) {
  return tipo === 'deudor' ? 'Deudor' : 'Codeudor';
}

export default function ConsultasResult({ consultas }: Props) {
  if (consultas.length === 0) return null;

  const personas = [...new Set(consultas.map((c) => c.persona_tipo))];

  // Agrupar por persona_tipo
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
              const color = BOT_COLORS[c.bot] || '#6c757d';
              const isPending = c.estado === 'pendiente' || c.estado === 'procesando';

              return (
                <div key={c.id} className="col-12 col-md-6 col-lg-4">
                  <div className="card h-100" style={{ borderLeft: `4px solid ${color}` }}>
                    <div className="card-body py-2 px-3">
                      <div className="d-flex align-items-center gap-2 mb-1">
                        <i className={botIcon(c.estado)} style={c.estado === 'procesando' ? { animation: 'spin 2s linear infinite' } : {}} />
                        <span className="fw-semibold small">{BOT_LABELS[c.bot] || c.bot}</span>
                      </div>
                      {isPending ? (
                        <div className="text-muted small">
                          {resumen?.mensaje ? String(resumen.mensaje) : c.estado === 'procesando' ? 'Consultando…' : 'En cola…'}
                        </div>
                      ) : c.estado === 'exitoso' ? (
                        <div className="small">
                          {resumen?.estado && <div className="text-muted">Estado: {String(resumen.estado)}</div>}
                          {resumen?.motivo && <div className="text-muted text-truncate" title={String(resumen.motivo)}>{String(resumen.motivo)}</div>}
                        </div>
                      ) : (
                        <div className="small text-danger">
                          {resumen?.mensaje ? String(resumen.mensaje) : 'Error en la consulta'}
                        </div>
                      )}
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
