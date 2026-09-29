import type { Toast } from '../types';

type Props = {
  toasts: Toast[];
  onDismiss: (id: number) => void;
};

export function Toasts({ toasts, onDismiss }: Props) {
  if (toasts.length === 0) return null;
  return (
    <div className="toasts" role="status" aria-live="polite">
      {toasts.map(t => (
        <div key={t.id} className={`toast toast-${t.type}`}>
          <span>{t.message}</span>
          <button type="button" className="toast-close" onClick={() => onDismiss(t.id)} aria-label="Cerrar">×</button>
        </div>
      ))}
    </div>
  );
}