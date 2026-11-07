'use client';
import { useApp } from '@/lib/app-context';
import { fmt } from '@/lib/i18n';
import type { Draft } from '@/lib/local';
import type { Visit } from '@/lib/types';
import { Chip, Icon } from './ui';

export type Row = { kind: 'server'; v: Visit; at: string } | { kind: 'local'; d: Draft; at: string };

const TONE: Record<string, 'gray' | 'blue' | 'green' | 'yellow' | 'red'> = {
  processing: 'blue', review: 'yellow', needs_review: 'red', submitted: 'blue', pending_approval: 'yellow', live: 'green', draft: 'gray', waiting: 'gray',
};

export function routeFor(r: Row): string | null {
  if (r.kind === 'local') return `/processing?v=${r.d.uuid}`;
  if (r.v.status === 'review' || r.v.status === 'needs_review') return `/review?v=${r.v.uuid}`;
  if (r.v.status === 'processing') return `/processing?v=${r.v.uuid}`;
  if (['submitted', 'pending_approval', 'live'].includes(r.v.status)) return `/done?v=${r.v.uuid}`;
  return null;
}

export function VisitRow({ r, onClick }: { r: Row; onClick(): void }) {
  const { t, locale } = useApp();
  const status = r.kind === 'local' ? 'waiting' : r.v.status;
  const name = r.kind === 'local' ? r.d.shop_name : r.v.shop_name;
  const time = new Date(r.at).toLocaleTimeString(locale === 'ar' ? 'ar-AE' : 'en-AE', { hour: '2-digit', minute: '2-digit' });
  const sub = r.kind === 'local' ? `${r.d.photos.length} 📷 · ${time}` : `${fmt(t.nProducts, { n: r.v.totals.found })} · ${time}`;
  return (
    <button className="li" onClick={onClick}>
      <span className="thumb"><Icon n={r.kind === 'local' ? 'cloud' : 'store'} /></span>
      <span className="grow"><span className="h" style={{ display: 'block' }}>{name}</span><span className="s">{sub}</span></span>
      <Chip tone={TONE[status]}>{t.statusLabel[status]}</Chip>
    </button>
  );
}
