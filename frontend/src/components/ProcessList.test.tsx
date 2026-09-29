import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { ProcessList } from './ProcessList';
import type { Process } from '../types';

const processes: Process[] = [
  { id: 1, code: 'PR-2026-AAA', title: 'Proceso A', status: 'created', createdAt: '' },
  { id: 2, code: 'PR-2026-BBB', title: 'Proceso B', status: 'analyzed', createdAt: '', _count: { files: 3, analyses: 1 } },
];

describe('ProcessList', () => {
  it('muestra los procesos y su estado', () => {
    render(<ProcessList processes={processes} busy={false} onSelect={vi.fn()} onCreate={vi.fn()} />);
    expect(screen.getByText('Proceso A')).toBeInTheDocument();
    expect(screen.getByText('Proceso B')).toBeInTheDocument();
    expect(screen.getAllByText('created').length).toBe(1);
  });

  it('abre un proceso al seleccionarlo', async () => {
    const onSelect = vi.fn();
    render(<ProcessList processes={processes} busy={false} onSelect={onSelect} onCreate={vi.fn()} />);
    await userEvent.click(screen.getByText('Proceso A'));
    expect(onSelect).toHaveBeenCalledWith(1);
  });

  it('crea un proceso y resetea el formulario', async () => {
    const onCreate = vi.fn().mockResolvedValue(undefined);
    render(<ProcessList processes={processes} busy={false} onSelect={vi.fn()} onCreate={onCreate} />);
    await userEvent.type(screen.getByPlaceholderText('Nombre del proceso'), 'Nuevo');
    await userEvent.click(screen.getByRole('button', { name: 'Crear' }));
    expect(onCreate).toHaveBeenCalledWith('Nuevo');
  });
});