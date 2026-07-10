export default function Procesos() {
  return (
    <div className="page-card">
      <h2 className="h5" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>
        <i className="bi bi-folder" /> Procesos
      </h2>
      <p className="text-muted">Módulo de carga de archivos del caso (PDF, imágenes, HTML, Excel) y su seguimiento.</p>
      <div className="alert alert-warning" style={{ fontSize: '.9rem' }}>
        <i className="bi bi-hourglass-split" /> <strong>En construcción.</strong> Implementación completa disponible en la Fase 1 del plan.
        Aquí se podrán: crear procesos, subir/descargar/visualizar archivos, ver historial.
      </div>
    </div>
  );
}