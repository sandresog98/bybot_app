import { type ReactNode, useEffect } from 'react';

interface ModalProps {
  open: boolean;
  onClose: () => void;
  title: string;
  children: ReactNode;
  footer?: ReactNode;
  size?: 'sm' | 'md' | 'lg';
}

export default function Modal({ open, onClose, title, children, footer, size = 'md' }: ModalProps) {
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [open, onClose]);

  if (!open) return null;

  const sizeClass = size === 'sm' ? 'by-modal-sm' : size === 'lg' ? 'by-modal-lg' : '';

  return (
    <div className="by-modal-backdrop" onClick={onClose}>
      <div className={`by-modal-card ${sizeClass}`} onClick={(e) => e.stopPropagation()}>
        <div className="by-modal-header">
          <h3>{title}</h3>
          <button className="btn-close" onClick={onClose} aria-label="Cerrar" />
        </div>
        <div className="by-modal-body">{children}</div>
        {footer && <div className="by-modal-footer">{footer}</div>}
      </div>
    </div>
  );
}