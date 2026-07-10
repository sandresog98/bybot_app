export default function Usuarios() {
  return (
    <div className="page-card">
      <h2 className="h5" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>
        <i className="bi bi-people" /> Usuarios
      </h2>
      <p className="text-muted">Gestión de cuentas y reseteo de contraseñas de un solo uso.</p>
      <div className="alert alert-warning" style={{ fontSize: '.9rem' }}>
        <i className="bi bi-hourglass-split" /> <strong>En construcción.</strong> CRUD disponible más adelante.
      </div>
    </div>
  );
}