import { useState } from 'react';

interface Props {
  personas: Array<{ tipo: string; numero_id: string; nombre?: string }>;
  onConfirm: (bots: string[]) => void;
  onCancel: () => void;
  loading: boolean;
}

const BOTS_DISPONIBLES = [
  { id: 'fosiga', label: 'Fosiga (ADRES)', desc: 'Consulta EPS — nombres, apellidos, régimen, estado afiliación' },
  { id: 'ruaf', label: 'RUAF (SISPRO)', desc: 'Afiliación salud — EPS, régimen, estado, fecha afiliación' },
  { id: 'rues', label: 'RUES (Reg. Mercantil)', desc: 'Registro mercantil — razón social, NIT, matrícula, estado' },
];

const ORDEN_POR_DEFECTO = ['fosiga', 'ruaf', 'rues'];

export default function ConsultasModal({ personas, onConfirm, onCancel, loading }: Props) {
  const [selectedBots, setSelectedBots] = useState<Set<string>>(new Set(ORDEN_POR_DEFECTO));

  const toggleBot = (id: string) => {
    const next = new Set(selectedBots);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    setSelectedBots(next);
  };

  const handleConfirm = () => {
    const ordered = ORDEN_POR_DEFECTO.filter((b) => selectedBots.has(b));
    onConfirm(ordered);
  };

  return (
    <div className="modal d-block" tabIndex={-1} style={{ backgroundColor: 'rgba(0,0,0,.5)' }}>
      <div className="modal-dialog modal-lg modal-dialog-centered">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">
              <i className="bi bi-robot me-1" /> Consultar en plataformas externas
            </h5>
            <button type="button" className="btn-close" onClick={onCancel} />
          </div>
          <div className="modal-body">
            <p className="text-muted small mb-3">
              Se consultarán los siguientes datos en las plataformas externas para:
            </p>
            <ul className="mb-3">
              {personas.map((p) => (
                <li key={p.tipo}>
                  <strong>{p.tipo === 'deudor' ? 'Deudor' : 'Codeudor'}</strong>
                  {p.nombre ? ` — ${p.nombre}` : ''}{' '}
                  <code className="small">{p.numero_id}</code>
                </li>
              ))}
            </ul>

            <p className="small fw-semibold mb-2">Selecciona los bots a ejecutar (orden respeta dependencias):</p>

            <div className="list-group">
              {BOTS_DISPONIBLES.map((bot, idx) => (
                <label key={bot.id} className={`list-group-item list-group-item-action d-flex align-items-center gap-3 ${selectedBots.has(bot.id) ? 'active' : ''}`}>
                  <input
                    type="checkbox"
                    className="form-check-input"
                    checked={selectedBots.has(bot.id)}
                    onChange={() => toggleBot(bot.id)}
                  />
                  <div className="flex-grow-1">
                    <div className="d-flex align-items-center gap-2">
                      <span className="badge bg-secondary rounded-pill">{idx + 1}</span>
                      <strong>{bot.label}</strong>
                    </div>
                    <small className="d-block text-muted" style={{ marginLeft: '1.7rem' }}>{bot.desc}</small>
                  </div>
                </label>
              ))}
            </div>

            <div className="mt-3 small text-muted">
              <i className="bi bi-info-circle me-1" />
              Las dependencias se respetan automáticamente. Si un bot falla, los siguientes continúan.
            </div>
          </div>
          <div className="modal-footer">
            <button className="btn btn-sm btn-secondary" onClick={onCancel} disabled={loading}>Cancelar</button>
            <button className="btn btn-sm btn-primary" onClick={handleConfirm} disabled={loading || selectedBots.size === 0}>
              {loading ? <><span className="spinner-border spinner-border-sm me-1" /> Consultando…</> : <><i className="bi bi-play-fill me-1" /> Ejecutar consultas</>}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
