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
  active?: boolean;
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
  deudorNombre?: string | null;
  deudorDocumento?: string | null;
  _count?: { files: number; analyses: number };
};

export type FileItem = {
  id: number;
  originalName: string;
  mimeType: string;
  originalMimeType?: string | null;
  converted?: boolean;
  sizeBytes: number;
  tipo?: string | null;
};

export type Analysis = {
  id: number;
  status: string;
  fileId?: number | null;
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

export type Parte = {
  id: number;
  rol: string;
  orden: number;
  nombreCompleto?: string | null;
  tipoDocumento?: string | null;
  numeroDocumento?: string | null;
  fechaExpedicion?: string | null;
  lugarExpedicion?: string | null;
  fechaNacimiento?: string | null;
  direccion?: string | null;
  ciudad?: string | null;
  departamento?: string | null;
  telefono?: string | null;
  celular?: string | null;
  email?: string | null;
  ocupacion?: string | null;
  empresa?: string | null;
  ingresosMensuales?: number | string | null;
  relacionDeudor?: string | null;
};

export type Credito = {
  id: number;
  numeroCredito?: string | null;
  numeroPagare?: string | null;
  producto?: string | null;
  monto?: number | string | null;
  plazoMeses?: number | null;
  tasaEa?: number | string | null;
  tasaInteresCorriente?: number | string | null;
  tasaInteresMora?: number | string | null;
  fechaDesembolso?: string | null;
  fechaCorte?: string | null;
  fechaCausacion?: string | null;
  saldoCapital?: number | string | null;
  totalInteresesCorrientes?: number | string | null;
  totalInteresesMora?: number | string | null;
  totalSeguroVida?: number | string | null;
  totalDeuda?: number | string | null;
  diasMora?: number | null;
  fechaUltimoPago?: string | null;
  valorUltimoPago?: number | string | null;
};

export type Movimiento = {
  id: number;
  orden: number;
  documento?: string | null;
  fecha?: string | null;
  descripcion?: string | null;
  total?: number | string | null;
  capital?: number | string | null;
  interes?: number | string | null;
  mora?: number | string | null;
  seguroVida?: number | string | null;
  otros?: number | string | null;
};

export type CuotaAmortizacion = {
  id: number;
  numero?: number | null;
  fecha?: string | null;
  cuota?: number | string | null;
  abonoCapital?: number | string | null;
  abonoInteres?: number | string | null;
  saldo?: number | string | null;
};

export type ExtraccionCampo = {
  id: number;
  ruta: string;
  clave: string;
  valorTexto?: string | null;
  valorNumero?: number | string | null;
  valorFecha?: string | null;
  valorBool?: boolean | null;
};

export type Structured = {
  partes: Parte[];
  credito: Credito | null;
  movimientos: Movimiento[];
  cuotas: CuotaAmortizacion[];
  campos: ExtraccionCampo[];
};

export type LiquidacionCuota = {
  numero: number;
  fecha: string | null;
  capital: number;
  interesPlazo: number;
  saldoCapital: number | null;
  inconsistente: boolean;
};

export type Liquidacion = {
  cuotas: LiquidacionCuota[];
  totalCapitalMora: number;
  totalInteresPlazo: number;
  interesesMora: number | null;
  capitalAcelerado: number | null;
  saldoCapitalCorte: number | null;
  total: number;
  cuantia: number;
  smlmv: number;
  umbralMinima: number;
  topeMinima: number;
  competencia: 'minima' | 'menor';
  juez: string;
  warnings: string[];
  tipoDemanda: 'consumo' | 'hipotecario';
};

export type Toast = {
  id: number;
  type: 'success' | 'error' | 'info';
  message: string;
};
export type ToastType = Toast['type'];

export type NewUserPayload = {
  name: string;
  username: string;
  password: string;
  role: 'admin' | 'operator';
};