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