'use client';
import { useEffect, type ReactNode } from 'react';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { homeFor, useApp } from '@/lib/app-context';
import type { Role } from '@/lib/types';

const PATHS: Record<string, string> = {
  back: 'M15 18l-6-6 6-6', arrow: 'M5 12h14M13 6l6 6-6 6', check: 'M5 13l4 4L19 7', x: 'M6 6l12 12M18 6L6 18',
  search: 'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zM20 20l-4-4', store: 'M3 9l2-5h14l2 5M4 9v11h16V9M9 20v-6h6v6',
  camera: 'M4 8h3l2-3h6l2 3h3v11H4zM12 17a4 4 0 1 0 0-8 4 4 0 0 0 0 8z', upload: 'M12 16V4M7 9l5-5 5 5M4 20h16',
  home: 'M3 11l9-7 9 7v9H3z', list: 'M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01', chart: 'M4 20V10M10 20V4M16 20v-7M22 20H2',
  user: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0', shield: 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z',
  alert: 'M12 8v5M12 17h.01M10.3 3.9L2 18a2 2 0 0 0 1.7 3h16.6a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z', pin: 'M12 21s7-6 7-12a7 7 0 1 0-14 0c0 6 7 12 7 12zM12 11a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
  msg: 'M4 4h16v12H8l-4 4z', qr: 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 18h2v2h-2zM14 18h2', copy: 'M8 8h12v12H8zM4 16V4h12',
  edit: 'M4 20h4L19 9l-4-4L4 16zM14 6l4 4', cloud: 'M7 18a5 5 0 0 1-.6-10A6 6 0 0 1 18 9a4.5 4.5 0 0 1-.5 9z', off: 'M2 2l20 20M8.5 16.5a5 5 0 0 1 7 0M5 12.5a10 10 0 0 1 4-2.4M12 20h.01',
  box: 'M3 7l9-4 9 4v10l-9 4-9-4zM3 7l9 4 9-4M12 11v10', link: 'M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1',
};

export function Icon({ n, size = 20 }: { n: keyof typeof PATHS | string; size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" className="ic">
      <path d={PATHS[n] ?? ''} />
    </svg>
  );
}

export function AppBar({ title, sub, back, right }: { title: string; sub?: string; back?: () => void; right?: ReactNode }) {
  return (
    <header className="bar">
      {back && <button className="ibtn flip" onClick={back} aria-label="Back"><Icon n="back" /></button>}
      <div className="grow"><h1>{title}</h1>{sub && <div className="s">{sub}</div>}</div>
      {right}
    </header>
  );
}

export function Chip({ tone = 'gray', children }: { tone?: 'gray' | 'blue' | 'green' | 'yellow' | 'red'; children: ReactNode }) {
  return <span className={`chip ${tone}`}>{children}</span>;
}

export function Sheet({ onClose, children }: { onClose(): void; children: ReactNode }) {
  useEffect(() => {
    const k = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    window.addEventListener('keydown', k); return () => window.removeEventListener('keydown', k);
  }, [onClose]);
  return (
    <div className="scrim" onClick={onClose}>
      <div className="sheet" role="dialog" aria-modal="true" onClick={e => e.stopPropagation()}>{children}</div>
    </div>
  );
}

export function OfflineBar() {
  const { online, workOffline, t } = useApp();
  if (online && !workOffline) return null;
  return <div className="offline"><Icon n="off" size={16} />{t.offlineBar}</div>;
}

export function Toast() {
  const { toastMsg } = useApp();
  return toastMsg ? <div className="toast" role="status">{toastMsg}</div> : null;
}

/** Signed-in screens, optionally limited to roles. */
export function Guard({ roles, children }: { roles?: Role[]; children: ReactNode }) {
  const { user, ready } = useApp();
  const router = useRouter();
  const path = usePathname();
  const ok = !!user && (!roles || roles.includes(user.role));
  useEffect(() => {
    if (!ready) return;
    if (!user) router.replace(`/login?next=${encodeURIComponent(path)}`);
    else if (!ok) router.replace(homeFor(user));
  }, [ready, user, ok, router, path]);
  if (!ready || !ok) return <div className="shell" aria-busy="true" />;
  return <>{children}</>;
}

export function TabBar() {
  const { t, user } = useApp();
  const path = usePathname();
  const tabs = [
    { href: '/home', n: 'home', label: t.today },
    { href: '/visits', n: 'list', label: t.visits },
    { href: '/find', n: 'camera', label: t.newVisit, main: true },
    user?.role === 'admin' ? { href: '/admin', n: 'shield', label: t.admin } : { href: '/targets', n: 'chart', label: t.targets },
    { href: '/profile', n: 'user', label: t.profile },
  ];
  return (
    <nav className="tabs">
      {tabs.map(x => (
        <Link key={x.href} href={x.href} className={`tab ${x.main ? 'main' : ''} ${path.startsWith(x.href) ? 'on' : ''}`} aria-label={x.label}>
          <Icon n={x.n} size={x.main ? 24 : 20} />{!x.main && <span>{x.label}</span>}
        </Link>
      ))}
    </nav>
  );
}

export function Progress({ value, max }: { value: number; max: number }) {
  return <div className="prog" role="progressbar" aria-valuenow={value} aria-valuemax={max}><i style={{ width: `${Math.min(100, max ? (value / max) * 100 : 0)}%` }} /></div>;
}
