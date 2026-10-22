/** The HS Water Billing server the app talks to. */
export const BASE_URL = 'https://water-billing.humsols.com';

export const HOST = 'water-billing.humsols.com';

export const colors = {
  navy: '#071a33',
  navySoft: '#0b2545',
  brand: '#14b8a6',
  brandDark: '#0e7c86',
  brandLight: '#ccfbf1',
  text: '#0f172a',
  muted: '#64748b',
  border: '#e2e8f0',
  danger: '#e11d48',
  white: '#ffffff',
};

/** Pages that mean "you are signed out" — the app shows its own login screen instead. */
export function isSignedOutUrl(url: string): boolean {
  try {
    const { hostname, pathname } = new URL(url);
    return hostname === HOST && (pathname === '/' || pathname === '/login');
  } catch {
    return false;
  }
}
