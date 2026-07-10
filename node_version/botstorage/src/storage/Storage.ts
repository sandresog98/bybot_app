export interface StoreResult {
  key: string;
  size: number;
  mime: string;
}

export interface Storage {
  store(data: Buffer, storedName: string, mime: string): Promise<StoreResult>;
  read(key: string): Promise<{ data: Buffer; size: number; mime: string }>;
  delete(key: string): Promise<void>;
  exists(key: string): Promise<boolean>;
  size(key: string): Promise<number | null>;
  mime(key: string): Promise<string | null>;
}