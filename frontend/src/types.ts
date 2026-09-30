export type User = {
  id: number;
  username: string;
  name: string;
  role: 'admin' | 'operator';
  active: boolean;
};

export type Process = {
  id: number;
  code: string;
  title: string;
  status: string;
  createdAt: string;
  _count?: { files: number; analyses: number };
};

export type FileItem = {
  id: number;
  originalName: string;
  mimeType: string;
  sizeBytes: number;
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