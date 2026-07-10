import { env } from '../config/env.js';
import { LocalStorage } from './LocalStorage.js';
import type { Storage } from './Storage.js';

let _driver: Storage | null = null;

export function getStorage(): Storage {
  if (_driver) return _driver;
  if (env.STORAGE_DRIVER === 'remote') {
    throw new Error('RemoteStorage no implementado (driver=remote). Defina STORAGE_DRIVER=local o implemente el proveedor.');
  }
  _driver = new LocalStorage();
  return _driver;
}