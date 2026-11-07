// Visits being captured or waiting to upload live on the phone (FR-07, NFR-02). IndexedDB because photos are Blobs.
import type { PhotoType } from './types';

export interface LocalPhoto { id: string; type: PhotoType; blob: Blob; uploaded?: boolean }
export type DraftState = 'capturing' | 'queued' | 'uploading' | 'uploaded';

export interface Draft {
  uuid: string; shop_name: string; supplier_id: number | null; owner: boolean; lat: number | null; lng: number | null; captured_at: string;
  photos: LocalPhoto[]; state: DraftState; error?: string; created_server?: boolean; extraction_started?: boolean;
}

const DB = 'market-scan';
const STORE = 'drafts';

function open(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB, 1);
    req.onupgradeneeded = () => req.result.createObjectStore(STORE, { keyPath: 'uuid' });
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

async function tx<T>(mode: IDBTransactionMode, fn: (s: IDBObjectStore) => IDBRequest<T>): Promise<T> {
  const db = await open();
  return new Promise((resolve, reject) => {
    const r = fn(db.transaction(STORE, mode).objectStore(STORE));
    r.onsuccess = () => resolve(r.result);
    r.onerror = () => reject(r.error);
  });
}

export const local = {
  get: (uuid: string) => tx<Draft | undefined>('readonly', s => s.get(uuid) as IDBRequest<Draft | undefined>),
  all: () => tx<Draft[]>('readonly', s => s.getAll() as IDBRequest<Draft[]>),
  put: (d: Draft) => tx<IDBValidKey>('readwrite', s => s.put(d)),
  remove: (uuid: string) => tx<undefined>('readwrite', s => s.delete(uuid) as IDBRequest<undefined>),
};

export function newDraft(p: Pick<Draft, 'shop_name' | 'supplier_id' | 'owner' | 'lat' | 'lng'>): Draft {
  return { uuid: crypto.randomUUID(), captured_at: new Date().toISOString(), photos: [], state: 'capturing', ...p };
}
