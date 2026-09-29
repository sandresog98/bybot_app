import { prisma } from './db.js';

export function audit(userId: number | null, action: string, resource: string, resourceId?: number, detail?: string) {
  return prisma.auditEvent.create({ data: { userId, action, resource, resourceId, detail } });
}
