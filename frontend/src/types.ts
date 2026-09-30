export type User = {
  id: number;
  username: string;
  name: string;
  role: 'admin' | 'operator';
  active: boolean;
};

export type Entidad = {
  id: number;
  codigo: string;
  nombre: string;
  nit?: string | null;
};

export const FILE_TIPOS = ['estado_cuenta', 'amortizacion', 'pagare', 'vinculacion', 'poder', 'anexo', 'otro'] as const;
export type FileTipo = (typeof FILE_TIPOS)[number];
export const FILE_TIPO_LABELS: Record<string, string> = {
  estado_cuenta: 'Estado de cuenta',
  amortizacion: 'Amortización / plan de pagos',
  pagare: 'Pagaré',
  vinculacion: 'Vinculación',
  poder: 'Poder',
  anexo: 'Anexos',
  otro: 'Otro',
};

export type Process = {
  id: number;
  code: string;
  title: string;
  status: string;
  createdAt: string;
  entidad?: { codigo: string; nombre: string } | null;
  _count?: { files: number; analyses: number };
};

export type FileItem = {
  id: number;
  originalName: string;
  mimeType: string;
  sizeBytes: number;
  tipo?: string | null;
};

export type Analysis = {
  id: number;
  status: string;
  provider?: string;
  model?: string;
  result?: unknown;
  validated?: unknown;
  error?: string;
  inputTokens?: number | null;
  outputTokens?: number | null;
  costUsd?: string | number | null;
};

export type Detail = Process & {
  files: FileItem[];
  analyses: Analysis[];
  creator?: { name: string };
};

export type Toast = {
  id: number;
  type: 'success' | 'error' | 'info';
  message: string;
};

export type NewUserPayload = {
  name: string;
  username: string;
  password: string;
  role: 'admin' | 'operator';
};