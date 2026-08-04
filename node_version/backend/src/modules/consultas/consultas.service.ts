import { prisma } from '../../core/db.js';
import { push } from '../../core/queue.js';
import { auditProceso } from '../../core/audit.js';
import { notFound, badRequest } from '../../core/errors.js';

const BOTS_POR_DEFECTO = ['fosiga', 'ruaf', 'rues'];

interface Persona {
  tipo: string;
  numero_id: string;
  nombre?: string;
}

/** Obtiene la configuración de orden de bots desde app_configuracion */
async function getBotOrder(): Promise<string[]> {
  const cfg = await prisma.appConfiguracion.findUnique({ where: { clave: 'bot_order' } });
  if (cfg?.valor) {
    try {
      const order = JSON.parse(cfg.valor) as string[];
      if (Array.isArray(order) && order.length > 0) return order;
    } catch { /* fallback */ }
  }
  return BOTS_POR_DEFECTO;
}

/** Encola consultas de bots para un proceso validado. */
export async function encolarConsultas(
  procesoId: number,
  usuarioId: number,
  botsSeleccionados?: string[],
) {
  const proc = await prisma.proceso.findUnique({
    where: { id: procesoId },
    select: { id: true, estado: true, codigo: true },
  });
  if (!proc) throw notFound('Proceso no encontrado');
  if (proc.estado !== 'validado') throw badRequest('El proceso debe estar en estado "validado" para consultar.');

  // Obtener datos validados para extraer deudor/codeudor
  const datosIa = await prisma.procesosDatosIa.findFirst({
    where: { proceso_id: procesoId },
    orderBy: { version: 'desc' },
    select: { datos_validados: true },
  });
  if (!datosIa?.datos_validados) throw badRequest('No hay datos validados para este proceso.');

  const dv = datosIa.datos_validados as Record<string, unknown>;
  const personas: Persona[] = [];
  const deudor = dv.deudor as Record<string, unknown> | undefined;
  const codeudor = dv.codeudor as Record<string, unknown> | undefined;

  if (deudor?.numero_id) {
    personas.push({ tipo: 'deudor', numero_id: String(deudor.numero_id), nombre: String(deudor.nombre ?? '') });
  }
  if (codeudor?.numero_id) {
    personas.push({ tipo: 'codeudor', numero_id: String(codeudor.numero_id), nombre: String(codeudor.nombre ?? '') });
  }

  if (personas.length === 0) throw badRequest('No se encontraron deudor/codeudor con número de identificación en los datos validados.');

  const botOrder = await getBotOrder();
  const bots = botsSeleccionados ?? botOrder;

  // Crear registros en procesos_consultas y encolar jobs
  const consultasCreadas: Array<{
    id: number;
    persona_tipo: string;
    bot: string;
    numero_id: string;
    estado: string;
  }> = [];

  for (const persona of personas) {
    for (let i = 0; i < bots.length; i++) {
      const bot = bots[i];
      if (!BOTS_POR_DEFECTO.includes(bot)) continue;

      const pc = await prisma.procesosConsulta.create({
        data: {
          proceso_id: procesoId,
          persona_tipo: persona.tipo,
          bot,
          numero_id: persona.numero_id,
          estado: 'pendiente',
          orden_ejecucion: i + 1,
        },
      });

      consultasCreadas.push({
        id: pc.id,
        persona_tipo: persona.tipo,
        bot,
        numero_id: persona.numero_id,
        estado: 'pendiente',
      });
    }
  }

  // Encolar jobs en orden (uno por consulta)
  for (const c of consultasCreadas) {
    await push('bybot:consultar', `consultar_${c.bot}`, {
      consulta_id: c.id,
      proceso_id: procesoId,
      persona_tipo: c.persona_tipo,
      bot: c.bot,
      numero_id: c.numero_id,
      orden: c.orden_ejecucion,
    }, procesoId, 5);
  }

  await auditProceso(procesoId, usuarioId, 'encolar_consultas', {
    estado_anterior: proc.estado,
    estado_nuevo: proc.estado,
    descripcion: `Consultas encoladas para ${proc.codigo}: ${personas.map((p) => `${p.tipo}(${p.numero_id})`).join(', ')} bots=[${bots.join(',')}]`,
  });

  return { total: consultasCreadas.length, personas: personas.map((p) => p.tipo), bots };
}

/** Obtiene los resultados de consultas de un proceso. */
export async function getConsultas(procesoId: number) {
  const rows = await prisma.procesosConsulta.findMany({
    where: { proceso_id: procesoId },
    orderBy: [{ persona_tipo: 'asc' }, { orden_ejecucion: 'asc' }],
  });
  return rows;
}

/** Obtiene el detalle de una consulta específica (incluye datos del bot). */
export async function getConsultaDetalle(consultaId: number) {
  const pc = await prisma.procesosConsulta.findUnique({ where: { id: consultaId } });
  if (!pc) throw notFound('Consulta no encontrada');
  if (!pc.consulta_tabla || !pc.consulta_id) return { ...pc, datos: null };

  let datos: unknown = null;
  if (pc.consulta_tabla === 'fosiga_consultas') {
    datos = await prisma.fosigaConsulta.findUnique({ where: { id: pc.consulta_id } });
  } else if (pc.consulta_tabla === 'ruaf_consultas') {
    datos = await prisma.ruafConsulta.findUnique({ where: { id: pc.consulta_id } });
  } else if (pc.consulta_tabla === 'rues_consultas') {
    datos = await prisma.ruesConsulta.findUnique({ where: { id: pc.consulta_id } });
  }

  return { ...pc, datos };
}
