import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { ResultadoIA } from './ResultadoIA';

const data = {
  deudor: { nombre_completo: 'Ana Pérez', numero_documento: 123456 },
  movimientos: [{ fecha: '2026-01-01', total: 500000 }, { fecha: '2025-12-01', total: 250000 }],
};

describe('ResultadoIA', () => {
  it('renderiza objetos como cards con formato monetario es-CO', () => {
    render(<ResultadoIA data={data} onSave={vi.fn()} />);
    expect(screen.getByText('Nombre Completo')).toBeInTheDocument();
    expect(screen.getByText('Ana Pérez')).toBeInTheDocument();
    expect(screen.getByText('123.456')).toBeInTheDocument();
  });

  it('renderiza arrays de objetos como tabla con columnas', () => {
    render(<ResultadoIA data={data} onSave={vi.fn()} />);
    expect(screen.getByText('Fecha')).toBeInTheDocument();
    expect(screen.getByText('Total')).toBeInTheDocument();
    expect(screen.getByText('500.000')).toBeInTheDocument();
    expect(screen.getByText('250.000')).toBeInTheDocument();
  });

  it('permite editar y guardar la validación', async () => {
    const onSave = vi.fn().mockResolvedValue(undefined);
    render(<ResultadoIA data={data} onSave={onSave} />);

    await userEvent.click(screen.getByRole('button', { name: 'Editar' }));
    const input = screen.getByDisplayValue('Ana Pérez');
    await userEvent.clear(input);
    await userEvent.type(input, 'Ana María Pérez');
    await userEvent.click(screen.getByRole('button', { name: 'Guardar validación' }));

    expect(onSave).toHaveBeenCalledTimes(1);
    const saved = onSave.mock.calls[0][0] as typeof data;
    expect(saved.deudor.nombre_completo).toBe('Ana María Pérez');
  });
});