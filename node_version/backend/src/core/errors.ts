/** Helpers de respuesta uniforme para rutas Fastify. */
export function ok<T>(data: T, message = 'OK') {
  return { success: true, message, data };
}

export function err(message: string, data: unknown = null) {
  return { success: false, message, data };
}

/** Error con código HTTP (paraConfigureAwait en Fastify handler). */
export class HttpError extends Error {
  status: number;
  data: unknown;
  constructor(status: number, message: string, data: unknown = null) {
    super(message);
    this.status = status;
    this.data = data;
  }
}

export const badRequest = (msg: string, data: unknown = null) => new HttpError(400, msg, data);
export const unauthorized = (msg = 'No autorizado') => new HttpError(401, msg);
export const forbidden = (msg = 'Acceso denegado') => new HttpError(403, msg);
export const notFound = (msg = 'No encontrado') => new HttpError(404, msg);