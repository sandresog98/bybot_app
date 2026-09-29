import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { UsersPanel } from './UsersPanel';

describe('UsersPanel', () => {
  it('crea un usuario con los datos del formulario', async () => {
    const onSubmit = vi.fn().mockResolvedValue(undefined);
    render(<UsersPanel users={[]} busy={false} onSubmit={onSubmit} />);
    await userEvent.type(screen.getByPlaceholderText('Nombre'), 'Ana Pérez');
    await userEvent.type(screen.getByPlaceholderText('Usuario'), 'ana');
    await userEvent.type(screen.getByPlaceholderText('Contraseña'), 'clave12345');
    await userEvent.selectOptions(screen.getByRole('combobox'), 'admin');
    await userEvent.click(screen.getByRole('button', { name: 'Crear usuario' }));
    expect(onSubmit).toHaveBeenCalledWith({ name: 'Ana Pérez', username: 'ana', password: 'clave12345', role: 'admin' });
  });
});