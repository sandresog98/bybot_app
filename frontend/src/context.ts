import { createContext, useContext } from 'react';
import type { ToastType, User } from './types';

export type AppContextValue = {
  user: User;
  toast: (type: ToastType, message: string) => void;
  logout: () => void;
};

export const AppContext = createContext<AppContextValue | null>(null);

export function useApp(): AppContextValue {
  const ctx = useContext(AppContext);
  if (!ctx) throw new Error('useApp debe usarse dentro de AppContext');
  return ctx;
}
