import type { ReactNode } from 'react';
import { ChevronDown, ChevronRight } from 'lucide-react';

type Props = {
  title: ReactNode;
  subtitle?: ReactNode;
  open: boolean;
  onToggle: () => void;
  children: ReactNode;
  className?: string;
};

export function Collapsible({ title, subtitle, open, onToggle, children, className }: Props) {
  return (
    <section className={`collapsible${open ? ' open' : ''}${className ? ` ${className}` : ''}`}>
      <button type="button" className="collapsible-head" aria-expanded={open} onClick={onToggle}>
        {open ? <ChevronDown size={16} aria-hidden /> : <ChevronRight size={16} aria-hidden />}
        <span className="collapsible-title">{title}</span>
        {subtitle && <span className="collapsible-subtitle">{subtitle}</span>}
      </button>
      {open && <div className="collapsible-body">{children}</div>}
    </section>
  );
}
