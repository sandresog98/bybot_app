import { useState, useEffect } from 'react';
import { useAuth } from '../auth/useAuth';

interface Props {
  datos: Record<string, unknown>;
  datos_validados: Record<string, unknown> | null;
  estado: string;
  onGuardar: (datos: Record<string, unknown>) => void;
  saving: boolean;
}

function renderField(key: string, value: unknown, onChange: (k: string, v: unknown) => void, readOnly: boolean) {
  const label = key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

  if (value === null || value === undefined) {
    return (
      <div key={key} className="mb-2">
        <label className="form-label small text-muted">{label}</label>
        <div className="text-muted small fst-italic">—</div>
      </div>
    );
  }

  if (Array.isArray(value)) {
    return (
      <div key={key} className="mb-2">
        <label className="form-label small text-muted">{label}</label>
        {value.length === 0
          ? <div className="text-muted small fst-italic">Sin datos</div>
          : value.map((item, i) => (
              <div key={i} className="card card-body py-2 px-3 mb-1 bg-light" style={{ fontSize: '.85rem' }}>
                {typeof item === 'object' && item !== null
                  ? Object.entries(item as Record<string, unknown>).map(([k, v]) => (
                      <div key={k} className="row mb-1">
                        <div className="col-4 text-muted small">{k.replace(/_/g, ' ')}</div>
                        <div className="col-8">
                          {readOnly
                            ? <span>{String(v ?? '')}</span>
                            : <input className="form-control form-control-sm" value={String(v ?? '')}
                                onChange={(e) => {
                                  const newVal = [...value] as Record<string, unknown>[];
                                  newVal[i] = { ...(newVal[i] as Record<string, unknown>), [k]: e.target.value };
                                  onChange(key, newVal);
                                }} />}
                        </div>
                      </div>
                    ))
                  : <span>{String(item)}</span>}
              </div>
            ))}
      </div>
    );
  }

  if (typeof value === 'object' && value !== null) {
    return (
      <div key={key} className="mb-2">
        <label className="form-label small text-muted">{label}</label>
        <div className="card card-body py-2 px-3 bg-light" style={{ fontSize: '.85rem' }}>
          {Object.entries(value as Record<string, unknown>).map(([k, v]) => {
            if (typeof v === 'object' && v !== null) {
              if (Array.isArray(v)) {
                return (
                  <div key={k} className="row mb-1">
                    <div className="col-4 text-muted small">{k.replace(/_/g, ' ')}</div>
                    <div className="col-8">{v.join(', ')}</div>
                  </div>
                );
              }
              return (
                <div key={k} className="mb-1">
                  <span className="text-muted small">{k.replace(/_/g, ' ')}:</span>
                  <div className="card card-body py-1 px-2 bg-light mt-1" style={{ fontSize: '.8rem' }}>
                    {Object.entries(v as Record<string, unknown>).map(([sk, sv]) => (
                      <div key={sk} className="row mb-0">
                        <div className="col-4 text-muted">{sk.replace(/_/g, ' ')}</div>
                        <div className="col-8">{Array.isArray(sv) ? (sv as unknown[]).join(', ') : String(sv ?? '')}</div>
                      </div>
                    ))}
                  </div>
                </div>
              );
            }
            return (
              <div key={k} className="row mb-1">
                <div className="col-4 text-muted small">{k.replace(/_/g, ' ')}</div>
                <div className="col-8">
                  {readOnly
                    ? <span>{String(v ?? '')}</span>
                    : <input className="form-control form-control-sm" value={String(v ?? '')}
                        onChange={(e) => {
                          const updated = { ...(value as Record<string, unknown>), [k]: e.target.value };
                          onChange(key, updated);
                        }} />}
                </div>
              </div>
            );
          })}
        </div>
      </div>
    );
  }

  const strVal = String(value);
  const isLongText = key === 'observaciones' || key === 'notas' || (strVal.length > 100);

  return (
    <div key={key} className="mb-2">
      <label className="form-label small text-muted">{label}</label>
      {readOnly
        ? <div style={{ fontSize: '.9rem', whiteSpace: 'pre-wrap' }}>{strVal}</div>
        : isLongText
          ? <textarea className="form-control form-control-sm" rows={Math.min(8, Math.max(3, Math.ceil(strVal.length / 80)))}
              value={strVal} onChange={(e) => onChange(key, e.target.value)} />
          : <input className="form-control form-control-sm" value={strVal}
              onChange={(e) => onChange(key, e.target.value)} />}
    </div>
  );
}

export default function ValidacionForm({ datos, datos_validados, estado, onGuardar, saving }: Props) {
  const { user } = useAuth();
  const canEdit = (user?.rol === 'admin' || user?.rol === 'supervisor') && estado === 'analizado';
  const [editData, setEditData] = useState<Record<string, unknown>>({});

  useEffect(() => {
    if (estado === 'analizado') {
      setEditData(JSON.parse(JSON.stringify(datos)));
    }
  }, [datos, estado]);

  const handleChange = (key: string, value: unknown) => {
    setEditData((prev) => ({ ...prev, [key]: value }));
  };

  const isReadOnly = estado === 'validado' || !canEdit;
  const displayData = isReadOnly && datos_validados ? datos_validados : editData;

  return (
    <div className="page-card mt-3">
      <h3 className="h6 mb-3" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>
        <i className="bi bi-check2-square" /> Datos extraídos por IA
      </h3>

      {Object.keys(datos).length === 0 && (
        <div className="text-muted text-center py-3">No hay datos disponibles.</div>
      )}

      {Object.entries(displayData).map(([key, value]) => {
        if (value === null || value === undefined) return null;
        return renderField(key, value, handleChange, isReadOnly);
      })}

      {isReadOnly && estado === 'validado' && (
        <div className="alert alert-success py-2 mb-0 mt-2">
          <i className="bi bi-check-circle-fill me-1" /> Datos validados el {new Date().toLocaleDateString()}
        </div>
      )}

      {canEdit && (
        <div className="mt-3 d-flex gap-2">
          <button className="btn btn-primary btn-sm" onClick={() => onGuardar(editData)} disabled={saving}>
            {saving ? <><span className="spinner-border spinner-border-sm me-1" /> Guardando…</> : <><i className="bi bi-check-lg me-1" /> Guardar validación</>}
          </button>
        </div>
      )}
    </div>
  );
}
