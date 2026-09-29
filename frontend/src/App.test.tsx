import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { App } from './App';
import { api } from './api';

vi.mock('./api', () => ({ api: { me: vi.fn(), login: vi.fn(), logout: vi.fn(), listProcesses: vi.fn(), listUsers: vi.fn(), getProcess: vi.fn(), createProcess: vi.fn(), createUser: vi.fn(), uploadFile: vi.fn(), analyze: vi.fn(), downloadUrl: vi.fn() } }));

const admin = { id: 1, username: 'admin', name: 'Administrador', role: 'admin' as const, active: true };

describe('App', () => {
  beforeEach(() => { vi.clearAllMocks(); });

  it('inicia sesión y muestra el panel para un admin', async () => {
    vi.mocked(api.me).mockResolvedValue(admin);
    vi.mocked(api.listProcesses).mockResolvedValue({ items: [] });
    vi.mocked(api.listUsers).mockResolvedValue({ items: [] });
    render(<App />);
    expect(await screen.findByRole('heading', { name: 'Usuarios' })).toBeInTheDocument();
    expect(screen.getByText('Administrador · admin')).toBeInTheDocument();
  });

  it('cierra sesión al pulsar Salir', async () => {
    vi.mocked(api.me).mockResolvedValue(admin);
    vi.mocked(api.listProcesses).mockResolvedValue({ items: [] });
    vi.mocked(api.listUsers).mockResolvedValue({ items: [] });
    vi.mocked(api.logout).mockResolvedValue({ ok: true });
    render(<App />);
    await screen.findByRole('button', { name: 'Salir' });
    await userEvent.click(screen.getByRole('button', { name: 'Salir' }));
    expect(api.logout).toHaveBeenCalled();
    expect(await screen.findByRole('button', { name: 'Ingresar' })).toBeInTheDocument();
  });
});