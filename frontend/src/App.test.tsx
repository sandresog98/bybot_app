import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { App } from './App';
import { api } from './api';

vi.mock('./api', () => ({ api: { me: vi.fn(), login: vi.fn(), logout: vi.fn(), listProcesses: vi.fn(), listEntidades: vi.fn(), createEntidad: vi.fn(), updateEntidad: vi.fn(), listUsers: vi.fn(), getProcess: vi.fn(), getStructured: vi.fn(), liquidar: vi.fn(), createProcess: vi.fn(), createUser: vi.fn(), updateUser: vi.fn(), uploadFile: vi.fn(), replaceFile: vi.fn(), deleteFile: vi.fn(), analyze: vi.fn(), analyzeAll: vi.fn(), consolidate: vi.fn(), validarAnalysis: vi.fn(), downloadUrl: vi.fn(), viewUrl: vi.fn() } }));

const admin = { id: 1, username: 'admin', name: 'Administrador', role: 'admin' as const, active: true };
const renderApp = () => render(<MemoryRouter><App /></MemoryRouter>);

describe('App', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(api.listProcesses).mockResolvedValue({ items: [] });
    vi.mocked(api.listEntidades).mockResolvedValue([]);
    vi.mocked(api.listUsers).mockResolvedValue({ items: [] });
  });

  it('inicia sesión y muestra la página de inicio con el sidebar', async () => {
    vi.mocked(api.me).mockResolvedValue(admin);
    renderApp();
    expect(await screen.findByRole('heading', { name: /Bienvenido, Administrador/ })).toBeInTheDocument();
    expect(screen.getByText('Administrador · admin')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Procesos' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Usuarios' })).toBeInTheDocument();
  });

  it('cierra sesión al pulsar Salir', async () => {
    vi.mocked(api.me).mockResolvedValue(admin);
    vi.mocked(api.logout).mockResolvedValue({ ok: true });
    renderApp();
    await screen.findByRole('button', { name: 'Salir' });
    await userEvent.click(screen.getByRole('button', { name: 'Salir' }));
    expect(api.logout).toHaveBeenCalled();
    expect(await screen.findByRole('button', { name: 'Ingresar' })).toBeInTheDocument();
  });
});
