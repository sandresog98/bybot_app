import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { api } from '../api';
import { AppContext } from '../context';
import { ClientsPage } from './ClientsPage';

vi.mock('../api', () => ({ api: { listEntidades: vi.fn(), createEntidad: vi.fn(), updateEntidad: vi.fn() } }));

const renderPage = () => render(
  <AppContext.Provider value={{ user: { id: 1, username: 'admin', name: 'Admin', role: 'admin', active: true }, toast: vi.fn(), logout: vi.fn(), theme: 'light', toggleTheme: vi.fn() }}>
    <ClientsPage />
  </AppContext.Provider>,
);

describe('ClientsPage', () => {
  it('lista clientes y alterna su estado', async () => {
    vi.mocked(api.listEntidades).mockResolvedValue([{ id: 1, codigo: 'confiar', nombre: 'CONFIAR', nit: '123', active: true }]);
    vi.mocked(api.updateEntidad).mockResolvedValue({ id: 1, codigo: 'confiar', nombre: 'CONFIAR', active: false });
    renderPage();
    expect(await screen.findByText('CONFIAR')).toBeInTheDocument();
    await userEvent.click(screen.getByTitle('Desactivar'));
    expect(api.updateEntidad).toHaveBeenCalledWith(1, { active: false });
  });
});
