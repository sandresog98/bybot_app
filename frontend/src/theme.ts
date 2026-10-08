export type Theme = 'light' | 'dark';

const STORAGE_KEY = 'bybot-theme';
const LEGACY_KEY = 'node2-theme';

export function getInitialTheme(): Theme {
  const stored = typeof localStorage !== 'undefined'
    ? (localStorage.getItem(STORAGE_KEY) ?? localStorage.getItem(LEGACY_KEY))
    : null;
  if (stored === 'light' || stored === 'dark') return stored;
  if (typeof window !== 'undefined' && typeof window.matchMedia === 'function' && window.matchMedia('(prefers-color-scheme: dark)').matches) return 'dark';
  return 'light';
}

export function applyTheme(theme: Theme) {
  if (typeof document !== 'undefined') document.documentElement.dataset.theme = theme;
  try { localStorage.setItem(STORAGE_KEY, theme); } catch { /* almacenamiento no disponible */ }
}
