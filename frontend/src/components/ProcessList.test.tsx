import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { ProcessList } from './ProcessList';
import type { Entidad, Process } from '../types';

const processes: Process[] = [
  { id: 1, code: 'PR-2026-AAA', title: 'Proceso A', status: 'created', createdAt: '', entidad: { codigo: 'confiar', nombre: 'CONFIAR' }, deudorNombre: 'ANA PEREZ', deudorDocumento: '1019044893' },
  { id: 2, code: 'PR-2026-BBB', title: 'Proceso B', status: 'analyzed', createdAt: '', _count: { files: 3, analyses: 1 } },
];

const entidades: Entidad[] = [
  { id: 1, codigo: 'confiar', nombre: 'CONFIAR' },
  { id: 2, codigo: 'somec', nombre: 'SOMEC' },
];

describe('ProcessList', () => {
  it('muestra los procesos, su estado, cliente y deudor', () => {
    render(<ProcessList processes={processes} entidades={entidades} busy={false} onSelect={vi.fn()} onCreate={vi.fn()} />);
    expect(screen.getByText('Proceso A')).toBeInTheDocument();
    expect(screen.getByText('Proceso B')).toBeInTheDocument();
    expect(screen.getAllByText('created').length).toBe(1);
    expect(screen.getAllByText(/CONFIAR/).length).toBeGreaterThan(0);
    expect(screen.getByText(/ANA PEREZ/)).toBeInTheDocument();
    expect(screen.getByText(/1019044893/)).toBeInTheDocument();
    expect(screen.getByText(/Sin datos de deudor/)).toBeInTheDocument();
  });

  it('abre un proceso al seleccionarlo', async () => {
    const onSelect = vi.fn();
    render(<ProcessList processes={processes} entidades={entidades} busy={false} onSelect={onSelect} onCreate={vi.fn()} />);
    await userEvent.click(screen.getByText('Proceso A'));
    expect(onSelect).toHaveBeenCalledWith(1);
  });

  it('crea un proceso con entidad opcional y resetea', async () => {
    const onCreate = vi.fn().mockResolvedValue(undefined);
    const { container } = render(<ProcessList processes={processes} entidades={entidades} busy={false} onSelect={vi.fn()} onCreate={onCreate} />);
    await userEvent.type(screen.getByPlaceholderText('Nombre del proceso'), 'Nuevo');
    await userEvent.selectOptions(screen.getByRole('combobox'), '2');
    fireEvent.submit(container.querySelector('form.create-form')!);
    expect(onCreate).toHaveBeenCalledWith('Nuevo', 2);
  });

  it('notifica cambios en los filtros', async () => {
    const onFilterChange = vi.fn();
    render(
      <ProcessList
        processes={processes}
        entidades={entidades}
        busy={false}
        onSelect={vi.fn()}
        onCreate={vi.fn()}
        filters={{ q: '', entidadId: 0, status: '' }}
        onFilterChange={onFilterChange}
      />,
    );
    await userEvent.type(screen.getByLabelText('Buscar procesos'), 'Perez');
    expect(onFilterChange).toHaveBeenCalled();
    await userEvent.selectOptions(screen.getByLabelText('Filtrar por cliente'), '2');
    expect(onFilterChange).toHaveBeenCalledWith({ entidadId: 2 });
  });
});
