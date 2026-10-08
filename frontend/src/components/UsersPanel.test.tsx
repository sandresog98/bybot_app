import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { UsersPanel } from './UsersPanel';
import type { User } from '../types';

const admin: User = { id: 1, username: 'ana', name: 'Ana Pérez', role: 'operator', active: true };

describe('UsersPanel', () => {
  it('crea un usuario desde el modal', async () => {
    const onSubmit = vi.fn().mockResolvedValue(undefined);
    render(<UsersPanel users={[]} busy={false} onSubmit={onSubmit} />);
    await userEvent.click(screen.getByRole('button', { name: 'Nuevo usuario' }));
    const dialog = screen.getByRole('dialog');
    await userEvent.type(within(dialog).getByPlaceholderText('Nombre'), 'Ana Pérez');
    await userEvent.type(within(dialog).getByPlaceholderText('Usuario'), 'ana');
    await userEvent.type(within(dialog).getByPlaceholderText('Contraseña'), 'clave12345');
    await userEvent.selectOptions(within(dialog).getByRole('combobox'), 'admin');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Crear usuario' }));
    expect(onSubmit).toHaveBeenCalledWith({ name: 'Ana Pérez', username: 'ana', password: 'clave12345', role: 'admin' });
  });

  it('edita un usuario desde el modal', async () => {
    const onSubmit = vi.fn().mockResolvedValue(undefined);
    const onUpdate = vi.fn().mockResolvedValue(undefined);
    render(<UsersPanel users={[admin]} busy={false} onSubmit={onSubmit} onUpdate={onUpdate} />);
    await userEvent.click(screen.getByTitle('Editar'));
    const dialog = screen.getByRole('dialog');
    const nameInput = within(dialog).getByDisplayValue('Ana Pérez');
    await userEvent.clear(nameInput);
    await userEvent.type(nameInput, 'Ana Gómez');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Guardar' }));
    expect(onUpdate).toHaveBeenCalledWith(1, expect.objectContaining({ name: 'Ana Gómez', active: true }));
  });
});
