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

export interface Proceso {
  id: number;
  codigo: string;
  tipo: ProcesoTipo;
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
  fecha_analisis: string;
  validado_por: number | null;
  fecha_validacion: string | null;
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