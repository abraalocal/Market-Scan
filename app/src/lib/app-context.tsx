'use client';
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { api, tokenStore } from './api';
import { dict, type T } from './i18n';
import { local } from './local';
import { syncAll } from './sync';
import type { Locale, User } from './types';

interface Ctx {
  user: User | null; ready: boolean; locale: Locale; t: T; online: boolean; workOffline: boolean; queued: number; toastMsg: string | null;
  setSession(token: string, user: User): void; signOut(): void; setLocale(l: Locale): void; setWorkOffline(v: boolean): void;
  refreshQueue(): Promise<void>; sync(): Promise<void>; toast(msg: string): void;
}

const AppCtx = createContext<Ctx | null>(null);
const USER = 'ms-user';
const pref = {
  get: (k: string) => { try { return localStorage.getItem(k); } catch { return null; } },
  set: (k: string, v: string | null) => { try { if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, v); } catch { /* blocked */ } },
};

export function AppProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [ready, setReady] = useState(false);
  const [locale, setLocaleState] = useState<Locale>('en');
  const [online, setOnline] = useState(true);
  const [workOffline, setWorkOfflineState] = useState(false);
  const [queued, setQueued] = useState(0);
  const [toastMsg, setToast] = useState<string | null>(null);

  const refreshQueue = useCallback(async () => {
    try { setQueued((await local.all()).filter(d => d.state === 'queued' || d.state === 'uploading').length); } catch { setQueued(0); }
  }, []);
  const canSync = online && !workOffline;
  const sync = useCallback(async () => {
    if (!canSync || !tokenStore.get()) return;
    await syncAll(); await refreshQueue();
  }, [canSync, refreshQueue]);
  const toast = useCallback((m: string) => { setToast(m); setTimeout(() => setToast(null), 2600); }, []);

  useEffect(() => {
    const l = pref.get('ms-locale');
    if (l === 'en' || l === 'ar') setLocaleState(l);
    setWorkOfflineState(pref.get('ms-work-offline') === '1');
    setOnline(navigator.onLine);
    const on = () => setOnline(true), off = () => setOnline(false);
    window.addEventListener('online', on); window.addEventListener('offline', off);
    (async () => {
      if (tokenStore.get()) {
        try {
          const u = await api.me();
          pref.set(USER, JSON.stringify(u)); setUser(u);
        } catch (e) {
          // Only a 401 means the session is over. Offline or a server hiccup keeps the last known user (FR-07).
          if ((e as { status?: number })?.status === 401) { tokenStore.set(null); pref.set(USER, null); }
          else { try { const c = pref.get(USER); if (c) setUser(JSON.parse(c) as User); } catch { /* ignore */ } }
        }
      }
      await refreshQueue();
      setReady(true);
    })();
    if ('serviceWorker' in navigator && process.env.NODE_ENV === 'production') navigator.serviceWorker.register('/sw.js').catch(() => {});
    return () => { window.removeEventListener('online', on); window.removeEventListener('offline', off); };
  }, [refreshQueue]);

  useEffect(() => {
    document.documentElement.lang = locale;
    document.documentElement.dir = locale === 'ar' ? 'rtl' : 'ltr';
  }, [locale]);

  useEffect(() => { if (user && canSync) sync().catch(() => {}); }, [user, canSync, sync]);

  const value = useMemo<Ctx>(() => ({
    user, ready, locale, t: dict(locale), online, workOffline, queued, toastMsg, refreshQueue, sync, toast,
    setSession: (token, u) => { tokenStore.set(token); pref.set(USER, JSON.stringify(u)); setUser(u); },
    signOut: () => { tokenStore.set(null); pref.set(USER, null); setUser(null); },
    setLocale: l => { pref.set('ms-locale', l); setLocaleState(l); },
    setWorkOffline: v => { pref.set('ms-work-offline', v ? '1' : '0'); setWorkOfflineState(v); },
  }), [user, ready, locale, online, workOffline, queued, toastMsg, refreshQueue, sync, toast]);

  return <AppCtx.Provider value={value}>{children}</AppCtx.Provider>;
}

export function useApp(): Ctx {
  const c = useContext(AppCtx);
  if (!c) throw new Error('useApp outside AppProvider');
  return c;
}

export const homeFor = (u: User | null) => (u?.role === 'owner' ? '/owner/shop' : '/home');
