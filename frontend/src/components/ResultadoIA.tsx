import { useState } from 'react';

type Props = {
  data: unknown;
  onSave: (validated: unknown) => Promise<void>;
};

type Path = Array<string | number>;

const labelOf = (key: string | number) =>
  String(key).replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());

const isObj = (v: unknown): v is Record<string, unknown> => typeof v === 'object' && v !== null && !Array.isArray(v);
const isArray = (v: unknown): v is unknown[] => Array.isArray(v);
const money = (n: number) => new Intl.NumberFormat('es-CO').format(n);

function pretty(v: unknown): string {
  if (v == null || v === '') return '—';
  if (typeof v === 'boolean') return v ? 'Sí' : 'No';
  if (typeof v === 'number') return money(v);
  return String(v);
}

function updateAt(root: unknown, path: Path, value: unknown): unknown {
  if (path.length === 0) return value;
  const [head, ...rest] = path;
  if (Array.isArray(root)) {
    const copy = [...root];
    copy[head as number] = updateAt(copy[head as number], rest, value);
    return copy;
  }
  if (isObj(root)) {
    return { ...root, [head as string]: updateAt(root[head as string], rest, value) };
  }
  return root;
}

function Input({ value, onValue }: { value: unknown; onValue: (v: string) => void }) {
  if (typeof value === 'boolean') {
    return <input type="checkbox" checked={value} onChange={e => onValue(String(e.target.checked))} />;
  }
  if (typeof value === 'number') {
    return <input type="number" value={String(value)} onChange={e => onValue(e.target.value)} />;
  }
  return <input type="text" value={value == null ? '' : String(value)} onChange={e => onValue(e.target.value)} />;
}

function CellInput({ value, onValue, editing }: { value: unknown; onValue: (v: unknown) => void; editing: boolean }) {
  if (!editing) return <span className="cell">{pretty(value)}</span>;
  return <Input value={value} onValue={onValue} />;
}

export function ResultadoIA({ data, onSave }: Props) {
  const [draft, setDraft] = useState<unknown>(data);
  const [editing, setEditing] = useState(false);
  const [saving, setSaving] = useState(false);

  const set = (path: Path, value: unknown) => setDraft((prev: unknown) => updateAt(prev, path, value));

  const renderScalar = (path: Path, v: unknown, editingFlag = editing) => (
    <CellInput
      value={v}
      editing={editingFlag}
      onValue={val => set(path, val)}
    />
  );

  const renderTable = (path: Path, rows: unknown[]) => {
    if (rows.length === 0) return <p className="placeholder">Sin datos.</p>;
    const keySet = new Set<string>();
    for (const row of rows) if (isObj(row)) Object.keys(row).forEach(k => keySet.add(k));
    const cols = [...keySet];
    return (
      <div className="ia-table-wrap">
        <table className="ia-table">
          <thead><tr>{cols.map(c => <th key={c}>{labelOf(c)}</th>)}</tr></thead>
          <tbody>
            {rows.map((row, r) => (
              <tr key={r}>{cols.map((c, ci) => (
                <td key={ci}>{isObj(row) ? renderScalar([...path, r, c], row[c]) : '—'}</td>
              ))}</tr>
            ))}
          </tbody>
        </table>
      </div>
    );
  };

  const renderNode = (path: Path, v: unknown): React.ReactNode => {
    if (isArray(v)) {
      if (v.length > 0 && v.every(isObj)) return renderTable(path, v);
      if (v.length === 0) return <span className="muted">—</span>;
      return <span>{v.map(pretty).join(', ')}</span>;
    }
    if (isObj(v)) {
      return (
        <div className="ia-card">
          {Object.entries(v).map(([k, val]) => (
            <div className="ia-row" key={k}>
              <span className="ia-label">{labelOf(k)}</span>
              <span className="ia-value">
                {isArray(val) || isObj(val)
                  ? renderNode([...path, k], val)
                  : renderScalar([...path, k], val)}
              </span>
            </div>
          ))}
        </div>
      );
    }
    return <span className="ia-value">{pretty(v)}</span>;
  };

  const handleSave = async () => {
    setSaving(true);
    try { await onSave(draft); setEditing(false); } finally { setSaving(false); }
  };

  return (
    <div className="ia">
      <div className="ia-toolbar">
        <span className="ia-title">Datos extraídos</span>
        {editing ? (
          <div className="ia-actions">
            <button type="button" className="btn-ghost btn-small" onClick={() => { setDraft(data); setEditing(false); }}>Cancelar</button>
            <button type="button" className="btn-small" disabled={saving} onClick={() => void handleSave()}>
              {saving ? 'Guardando…' : 'Guardar validación'}
            </button>
          </div>
        ) : (
          <button type="button" className="btn-ghost btn-small" onClick={() => setEditing(true)}>Editar</button>
        )}
      </div>
      {draft == null ? <p className="placeholder">Sin datos extraídos.</p> : renderNode([], draft)}
    </div>
  );
}