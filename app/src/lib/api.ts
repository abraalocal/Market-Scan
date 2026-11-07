// Client for docs/openapi.yaml. NEXT_PUBLIC_API_URL points at the Laravel backend.
import type {
  AdminSummary, CreateVisitInput, Field, FoundProduct, NewProduct, OwnerShop, PhotoType, Role, ShopHit, SubmitResult,
  SupplierChange, SupplierChoice, Targets, User, Visit, Welcome,
} from './types';

export class ApiError extends Error {
  constructor(public status: number, message: string, public errors?: Record<string, string[]>) { super(message); }
}

const TOKEN = 'ms-token';
export const tokenStore = {
  get(): string | null { try { return localStorage.getItem(TOKEN); } catch { return null; } },
  set(t: string | null) { try { if (t) localStorage.setItem(TOKEN, t); else localStorage.removeItem(TOKEN); } catch { /* blocked */ } },
};

const BASE = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';

async function call<T>(method: string, path: string, body?: unknown, form?: FormData): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' };
  const t = tokenStore.get();
  if (t) headers.Authorization = `Bearer ${t}`;
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  const res = await fetch(BASE + path, { method, headers, body: form ?? (body !== undefined ? JSON.stringify(body) : undefined) });
  if (!res.ok) {
    let msg = res.statusText; let errors: Record<string, string[]> | undefined;
    try { const j = await res.json(); msg = j.message ?? msg; errors = j.errors; } catch { /* not JSON */ }
    throw new ApiError(res.status, msg, errors);
  }
  const text = await res.text();
  return (text ? JSON.parse(text) : undefined) as T;
}

const qs = (o: Record<string, unknown>) => {
  const p = new URLSearchParams();
  Object.entries(o).forEach(([k, v]) => { if (v !== undefined && v !== null && v !== '') p.set(k, String(v)); });
  const s = p.toString();
  return s ? `?${s}` : '';
};

export const api = {
  requestOtp: (phone: string, role: Exclude<Role, 'admin'>, invite?: string) => call<{ sent: boolean }>('POST', '/auth/otp', { phone, role, invite }),
  verifyOtp: (phone: string, code: string) => call<{ token: string; user: User }>('POST', '/auth/verify', { phone, code }),
  me: () => call<User>('GET', '/me'),
  searchShops: (q: string, lat?: number | null, lng?: number | null) => call<ShopHit[]>('GET', '/shops/search' + qs({ q, lat, lng })),
  checkPhone: async (phone: string) => (await call<{ match: ShopHit | null }>('GET', '/shops/check-phone' + qs({ phone }))).match,
  listVisits: (status?: string) => call<Visit[]>('GET', '/visits' + qs({ status })),
  createVisit: (input: CreateVisitInput) => call<Visit>('POST', '/visits', input),
  uploadPhoto: (uuid: string, type: PhotoType, file: Blob, clientId: string) => {
    const f = new FormData();
    f.set('type', type); f.set('client_id', clientId); f.set('file', file, `${clientId}.jpg`);
    return call<{ id: number }>('POST', `/visits/${uuid}/photos`, undefined, f);
  },
  startExtraction: (uuid: string) => call<Visit>('POST', `/visits/${uuid}/extract`),
  getVisit: (uuid: string) => call<Visit>('GET', `/visits/${uuid}`),
  submitVisit: (uuid: string, fields: Field[], products: FoundProduct[], supplier: SupplierChoice | null) =>
    call<SubmitResult>('POST', `/visits/${uuid}/submit`, { fields, products, ...(supplier ? { supplier } : {}) }),
  welcome: (uuid: string, channel: 'whatsapp' | 'qr') => call<Welcome>('POST', `/visits/${uuid}/welcome`, { channel }),
  targets: () => call<Targets>('GET', '/targets/me'),
  ownerShop: () => call<OwnerShop | null>('GET', '/owner/shop'),
  admin: {
    summary: () => call<AdminSummary>('GET', '/admin/summary'),
    changes: (status = 'pending') => call<SupplierChange[]>('GET', '/admin/supplier-changes' + qs({ status })),
    approve: (id: number, proposed?: Record<string, string>) => call<SupplierChange>('POST', `/admin/supplier-changes/${id}/approve`, proposed ? { proposed } : {}),
    reject: (id: number, note: string) => call<SupplierChange>('POST', `/admin/supplier-changes/${id}/reject`, { note }),
    products: (status?: string) => call<NewProduct[]>('GET', '/admin/products' + qs({ status })),
    saveProduct: (id: number, p: Partial<NewProduct>) => call<NewProduct>('PATCH', `/admin/products/${id}`, p),
    publish: (id: number) => call<NewProduct>('POST', `/admin/products/${id}/publish`),
    rejectProduct: (id: number, note: string) => call<NewProduct>('POST', `/admin/products/${id}/reject`, { note }),
    settings: () => call<{ auto_publish: boolean }>('GET', '/admin/settings'),
    saveSettings: (auto_publish: boolean) => call<{ auto_publish: boolean }>('PUT', '/admin/settings', { auto_publish }),
  },
};
