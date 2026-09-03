import { useState, useEffect } from 'react';
import { useAuth } from '../auth/useAuth';

interface Props {
  datos: Record<string, unknown>;
  datos_validados: Record<string, unknown> | null;
  estado: string;
  onGuardar: (datos: Record<string, unknown>) => void;
  saving: boolean;
}

// ── Formato numérico SOLO VISUAL (no altera los datos guardados) ──
// Un valor es "numérico" si es number finito o un string de solo dígitos/decimal.
function looksNumeric(v: unknown): boolean {
  if (typeof v === 'number') return Number.isFinite(v);
  if (typeof v === 'string') {
    const t = v.trim();
    return t !== '' && /^-?\d+([.,]\d+)?$/.test(t);
  }
  return false;
}

// Muestra miles con '.' y decimales con ',' (es-CO). Solo para presentación.
function fmtNum(v: unknown): string {
  if (!looksNumeric(v)) return v == null ? '' : String(v);
  const n = typeof v === 'number' ? v : Number(String(v).replace(',', '.'));
  if (!Number.isFinite(n)) return String(v);
  return n.toLocaleString('es-CO', { maximumFractionDigits: 10 });
}

/**
 * Input que muestra el número FORMATEADO cuando no está enfocado y el valor CRUDO
 * al enfocarlo (para editar). Lo que se propaga en onChange es siempre el texto crudo
 * tecleado → el dato guardado nunca contiene separadores de formato.
 */
function EditNum({ value, onChange, textarea = false, rows, className = 'form-control form-control-sm' }: {
  value: unknown;
  onChange: (v: string) => void;
  textarea?: boolean;
  rows?: number;
  className?: string;
}) {
  const [focused, setFocused] = useState(false);
  const raw = value == null ? '' : String(value);
  const shown = focused || !looksNumeric(value) ? raw : fmtNum(value);
  const handlers = {
    className,
    value: shown,
    onFocus: () => setFocused(true),
    onBlur: () => setFocused(false),
    onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => onChange(e.target.value),
  };
  return textarea ? <textarea {...handlers} rows={rows} /> : <input {...handlers} />;
}

/** Texto de solo lectura con recorte visual ("ver más/menos") si es muy largo. */
function CollapsibleText({ text, limit = 240 }: { text: string; limit?: number }) {
  const [open, setOpen] = useState(false);
  const style = { fontSize: '.9rem', whiteSpace: 'pre-wrap' as const };
  if (text.length <= limit) return <div style={style}>{text}</div>;
  return (
    <div style={style}>
      {open ? text : text.slice(0, limit).trimEnd() + '… '}
      <button type="button" className="btn btn-link btn-sm p-0 align-baseline" onClick={() => setOpen(!open)}>
        {open ? 'ver menos' : 'ver más'}
      </button>
    </div>
  );
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
    if (value.length === 0) {
      return (
        <div key={key} className="mb-2">
          <label className="form-label small text-muted">{label}</label>
          <div className="text-muted small fst-italic">Sin datos</div>
        </div>
      );
    }
    const allObjects = value.every((it) => typeof it === 'object' && it !== null && !Array.isArray(it));
    if (allObjects) {
      // Array de objetos → tabla (columnas = unión de claves). Ideal para amortización/referencias.
      const cols = Array.from(new Set(value.flatMap((o) => Object.keys(o as Record<string, unknown>))));
      return (
        <div key={key} className="mb-2">
          <label className="form-label small text-muted">{label} <span className="text-muted">({value.length})</span></label>
          <div className="table-responsive" style={{ maxHeight: 320, overflow: 'auto' }}>
            <table className="table table-sm table-bordered mb-0" style={{ fontSize: '.8rem' }}>
              <thead className="table-light" style={{ position: 'sticky', top: 0 }}>
                <tr>{cols.map((c) => <th key={c}>{c.replace(/_/g, ' ')}</th>)}</tr>
              </thead>
              <tbody>
                {(value as Record<string, unknown>[]).map((row, i) => (
                  <tr key={i}>
                    {cols.map((c) => (
                      <td key={c}>
                        {readOnly
                          ? <span>{fmtNum(row[c])}</span>
                          : <EditNum className="form-control form-control-sm border-0 p-1" value={row[c]}
                              onChange={(val) => {
                                const next = [...(value as Record<string, unknown>[])];
                                next[i] = { ...next[i], [c]: val };
                                onChange(key, next);
                              }} />}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      );
    }
    // Array de primitivos
    return (
      <div key={key} className="mb-2">
        <label className="form-label small text-muted">{label}</label>
        <div style={{ fontSize: '.85rem' }}>{value.map((v) => fmtNum(v)).join(', ')}</div>
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
                const objRows = v.filter((it) => typeof it === 'object' && it !== null && !Array.isArray(it));
                if (v.length > 0 && objRows.length === v.length) {
                  // Array de objetos anidado (p.ej. amortizacion.cuotas) → tabla legible.
                  const cols = Array.from(new Set(v.flatMap((o) => Object.keys(o as Record<string, unknown>))));
                  return (
                    <div key={k} className="mb-1">
                      <span className="text-muted small">{k.replace(/_/g, ' ')} ({v.length}):</span>
                      <div className="table-responsive mt-1" style={{ maxHeight: 320, overflow: 'auto' }}>
                        <table className="table table-sm table-bordered mb-0" style={{ fontSize: '.75rem' }}>
                          <thead className="table-light" style={{ position: 'sticky', top: 0 }}>
                            <tr>{cols.map((c) => <th key={c}>{c.replace(/_/g, ' ')}</th>)}</tr>
                          </thead>
                          <tbody>
                            {(v as Record<string, unknown>[]).map((row, ri) => (
                              <tr key={ri}>{cols.map((c) => <td key={c}>{fmtNum(row[c])}</td>)}</tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  );
                }
                return (
                  <div key={k} className="row mb-1">
                    <div className="col-4 text-muted small">{k.replace(/_/g, ' ')}</div>
                    <div className="col-8">{v.map((x) => fmtNum(x)).join(', ')}</div>
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
                        <div className="col-8">{Array.isArray(sv) ? (sv as unknown[]).map((x) => fmtNum(x)).join(', ') : fmtNum(sv)}</div>
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
                    ? <span>{fmtNum(v)}</span>
                    : <EditNum value={v}
                        onChange={(val) => {
                          const updated = { ...(value as Record<string, unknown>), [k]: val };
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
        ? (looksNumeric(value)
            ? <div style={{ fontSize: '.9rem' }}>{fmtNum(value)}</div>
            : <CollapsibleText text={strVal} />)
        : <EditNum value={value} textarea={isLongText}
            rows={isLongText ? Math.min(8, Math.max(3, Math.ceil(strVal.length / 80))) : undefined}
            onChange={(val) => onChange(key, val)} />}
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
