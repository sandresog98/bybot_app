import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { Toasts } from './Toasts';
import type { Toast } from '../types';

const toasts: Toast[] = [
  { id: 1, type: 'success', message: 'Listo.' },
  { id: 2, type: 'error', message: 'Falló.' },
];

describe('Toasts', () => {
  it('no renderiza nada sin toasts', () => {
    render(<Toasts toasts={[]} onDismiss={vi.fn()} />);
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
  });

  it('muestra y cierra toasts', async () => {
    const onDismiss = vi.fn();
    render(<Toasts toasts={toasts} onDismiss={onDismiss} />);
    expect(screen.getByText('Listo.')).toBeInTheDocument();
    expect(screen.getByText('Falló.')).toBeInTheDocument();
    await userEvent.click(screen.getAllByLabelText('Cerrar')[0]);
    expect(onDismiss).toHaveBeenCalledWith(1);
  });
});