import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { Login } from './Login';

describe('Login', () => {
  it('envía usuario y contraseña', async () => {
    const onSubmit = vi.fn().mockResolvedValue(undefined);
    render(<Login onSubmit={onSubmit} busy={false} />);
    await userEvent.type(screen.getByPlaceholderText('Usuario'), 'admin');
    await userEvent.type(screen.getByPlaceholderText('Contraseña'), 'secreto');
    await userEvent.click(screen.getByRole('button', { name: 'Ingresar' }));
    expect(onSubmit).toHaveBeenCalledWith('admin', 'secreto');
  });

  it('deshabilita y muestra estado ocupado', () => {
    render(<Login onSubmit={vi.fn()} busy message="Credenciales inválidas" />);
    expect(screen.getByRole('button', { name: 'Ingresando…' })).toBeDisabled();
    expect(screen.getByText('Credenciales inválidas')).toBeInTheDocument();
  });
});