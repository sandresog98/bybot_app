import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api';
import { useApp } from '../context';
import type { Process } from '../types';

export function Home() {
  const { user } = useApp();
  const [processes, setProcesses] = useState<Process[]>([]);

  useEffect(() => {
    api.listProcesses().then(page => setProcesses(page.items)).catch(() => setProcesses([]));
  }, []);

  const totalFiles = processes.reduce((acc, p) => acc + (p._count?.files ?? 0), 0);
  const totalAnalyses = processes.reduce((acc, p) => acc + (p._count?.analyses ?? 0), 0);
  const firstName = user.name.split(' ')[0];

  return (
    <section className="page">
      <header className="page-head">
        <div>
          <h1>Bienvenido, {firstName}</h1>
          <p className="hint">Centraliza la carga de archivos, el análisis con IA y el almacenamiento de la información.</p>
        </div>
      </header>

      <div className="stat-grid">
        <Link to="/procesos" className="stat-card">
          <span className="stat-num">{processes.length}</span>
          <span className="stat-label">Procesos</span>
        </Link>
        <Link to="/procesos" className="stat-card">
          <span className="stat-num">{totalFiles}</span>
          <span className="stat-label">Archivos</span>
        </Link>
        <Link to="/procesos" className="stat-card">
          <span className="stat-num">{totalAnalyses}</span>
          <span className="stat-label">Análisis IA</span>
        </Link>
      </div>

      <div className="quick-actions">
        <Link className="btn" to="/procesos">Ir a Procesos</Link>
        {user.role === 'admin' && <Link className="btn btn-ghost" to="/usuarios">Gestionar Usuarios</Link>}
      </div>
    </section>
  );
}
