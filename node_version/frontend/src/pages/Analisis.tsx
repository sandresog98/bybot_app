export default function Analisis() {
  return (
    <div className="page-card">
      <h2 className="h5" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>
        <i className="bi bi-robot" /> Análisis con IA
      </h2>
      <p className="text-muted">Extracción y validación de datos de un proceso usando Gemini.</p>
      <div className="alert alert-warning" style={{ fontSize: '.9rem' }}>
        <i className="bi bi-hourglass-split" /> <strong>En construcción.</strong> Implementación disponible en la Fase 2 del plan.
      </div>
    </div>
  );
}