import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export interface RolDef {
  label: string;
  modulos: string[];
}

interface RolesDoc {
  _doc?: string;
  roles: Record<string, RolDef>;
}

let _roles: RolesDoc | null = null;

function load(): RolesDoc {
  if (_roles) return _roles;
  const here = fileURLToPath(new URL('.', import.meta.url));
  // backend/src/core/ → subir 3 niveles a node_version/
  const root = resolve(here, '..', '..', '..');
  const path = resolve(root, 'roles.json');
  const txt = readFileSync(path, 'utf-8');
  _roles = JSON.parse(txt) as RolesDoc;
  return _roles;
}

export function allRoles(): Record<string, RolDef> {
  return load().roles;
}

export function canModule(rol: string | null | undefined, module: string): boolean {
  if (!rol || !_roles) return false;
  const def = load().roles[rol];
  if (!def) return false;
  return def.modulos.includes(module);
}

export function modulesFor(rol: string | null | undefined): string[] {
  if (!rol) return [];
  return load().roles[rol]?.modulos ?? [];
}