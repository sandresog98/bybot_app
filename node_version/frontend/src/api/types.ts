// Tipos espejo del backend. Se mantenemos simples; pueden migrarse a openapi/zod más adelante.

export interface User {
  id: number;
  usuario: string;
  nombre_completo?: string;
  nombre?: string;
  email?: string;
  rol: string;
  clave_un_solo_uso: boolean;
  estado_activo?: boolean;
  ultimo_acceso?: string | null;
  modulos?: string[];
}

export interface LoginResponse {
  user: User;
  access_token: string;
  refresh_token: string;
  must_change_password: boolean;
}

export interface DashboardStats {
  counts: {
    procesos: number;
    archivos: number;
    analizados: number;
    cola_pendientes: number;
    usuarios: number;
    prompts: number;
  };
  por_estado: Array<{ estado: string; n: number }>;
  ultimos: Array<{ id: number; codigo: string; estado: string; prioridad: number; created_at: string }>;
}

// Procesos
export type ProcesoTipo = 'cobranza' | 'demanda' | 'otro';
export type ProcesoEstado =
  | 'creado' | 'archivos_cargados' | 'en_analisis' | 'analizado'
  | 'validado' | 'completado' | 'error' | 'cancelado';

export interface Entidad {
  id: number;
  codigo: string;
  nombre: string;
  nit: string | null;
}

export interface EntidadAdmin extends Entidad {
  activo: boolean;
  created_at: string;
  total_tipos_doc: number;
  total_procesos: number;
  total_prompts: number;
}

export interface EntidadTipoDoc {
  value: string;      // categoría lógica canónica (se envía como `tipo` al subir)
  label: string;
  clave: string;
  obligatorio: boolean;
  orden: number;
}

// Fila cruda del catálogo (administración)
export interface EntidadTipoDocFull {
  id: number;
  entidad_id: number;
  clave: string;
  label: string;
  categoria_logica: string;
  obligatorio: number;   // 0/1
  orden: number;
  activo: number;        // 0/1
}

export const CATEGORIAS_LOGICAS = [
  'pagare', 'estado_cuenta', 'amortizacion', 'vinculacion', 'poder', 'anexo', 'identificacion', 'otro',
] as const;

export interface Proceso {
  id: number;
  codigo: string;
  tipo: ProcesoTipo;
  entidad_id: number | null;
  entidad: string | null;
  entidad_codigo: string | null;
  estado: ProcesoEstado;
  prioridad: number;
  creado_por: string | null;
  asignado_a: string | null;
  total_archivos: number;
  notas: string | null;
  created_at: string;
  updated_at: string;
}

export interface ProcesoDetalle extends Proceso {
  creado_por_id: number | null;
  asignado_a_id: number | null;
  archivos: Archivo[];
  historial: Historial[];
}

export interface Archivo {
  id: number;
  nombre_original: string;
  nombre_archivo?: string;
  tipo: string;
  mime_type: string;
  tamanio_bytes: number;
  hash_sha256: string | null;
  created_at: string;
}

export interface Historial {
  id: number;
  accion: string;
  estado_anterior: string | null;
  estado_nuevo: string | null;
  descripcion: string | null;
  fecha: string;
  usuario: string | null;
}

export type ArchivoTipo = 'estado_cuenta' | 'anexo' | 'solicitud_deudor' | 'solicitud_codeudor' | 'identificacion' | 'otro';

export interface Prompt {
  id: number;
  nombre: string;
  version: string;
  tipo: string;
  entidad_id: number | null;
  entidad: string | null;
  entidad_codigo: string | null;
  contenido: string;
  activo: boolean;
  notas: string | null;
  creado_por: number | null;
  created_at: string;
  updated_at: string;
}

export interface ProcesosConsulta {
  id: number;
  proceso_id: number;
  persona_tipo: string;
  bot: string;
  numero_id: string;
  consulta_tabla: string | null;
  consulta_id: number | null;
  estado: string;
  resultado_resumen: Record<string, unknown> | null;
  orden_ejecucion: number;
  created_at: string;
  updated_at: string;
}

export interface ConsultaDetalle extends ProcesosConsulta {
  datos: Record<string, unknown> | null;
}

export interface AnalisisDatos {
  id: number;
  proceso_id: number;
  version: number;
  datos_originales: Record<string, unknown>;
  datos_validados: Record<string, unknown> | null;
  metadata: Record<string, unknown> | null;
  modelo: string | null;
  tokens_total: number | null;
  tokens_entrada: number | null;
  tokens_salida: number | null;
  costo_estimado_usd?: number;
  fecha_analisis: string;
  validado_por: number | null;
  fecha_validacion: string | null;
}

export interface ConsumoIa {
  analisis_count: number;
  tokens_entrada: number;
  tokens_salida: number;
  tokens_total: number;
  costo_estimado_usd: number;
  precios: { entrada: number; salida: number };
}

// Usuarios
export interface Usuario {
  id: number;
  usuario: string;
  nombre_completo: string;
  email?: string | null;
  rol: string;
  clave_un_solo_uso: boolean;
  estado_activo?: boolean;
  ultimo_acceso?: string | null;
  created_at?: string;
}

export interface UsuarioCreado extends Usuario {
  password_temporal: string;
}