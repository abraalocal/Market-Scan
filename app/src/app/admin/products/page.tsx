'use client';
import { Suspense, useCallback, useEffect, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useApp } from '@/lib/app-context';
import { api, ApiError } from '@/lib/api';
import { fmt } from '@/lib/i18n';
import type { NewProduct } from '@/lib/types';
import { AppBar, Chip, Guard, Icon, Sheet } from '@/components/ui';

const TABS = ['ready', 'needs_person', 'published'] as const;

function Products() {
  const { t, toast } = useApp();
  const router = useRouter();
  const params = useSearchParams();
  const [tab, setTab] = useState<(typeof TABS)[number]>((params.get('status') as (typeof TABS)[number]) || 'ready');
  const [rows, setRows] = useState<NewProduct[] | null>(null);
  const [open, setOpen] = useState<NewProduct | null>(null);
  const [busy, setBusy] = useState(false);
  const load = useCallback(() => { setRows(null); api.admin.products(tab).then(setRows).catch(() => toast(t.error)); }, [tab, t.error, toast]);
  useEffect(load, [load]);

  async function save(p: NewProduct) {
    return api.admin.saveProduct(p.id, { title_en: p.title_en, title_ar: p.title_ar, desc_en: p.desc_en, desc_ar: p.desc_ar, source_url: p.source_url || undefined, moq: p.moq } as Partial<NewProduct>);
  }
  async function act(kind: 'publish' | 'reject') {
    if (!open) return;
    setBusy(true);
    try {
      if (kind === 'publish') {
        if (!open.title_en || !open.title_ar) { toast(t.publishNeedsTitles); return; }
        await save(open); await api.admin.publish(open.id); toast(t.published);
      } else { await api.admin.rejectProduct(open.id, ''); toast(t.rejected); }
      setOpen(null); load();
    } catch (e) { toast(e instanceof ApiError ? e.message : t.error); } finally { setBusy(false); }
  }
  const set = (k: keyof NewProduct, v: string | number) => setOpen(o => (o ? { ...o, [k]: v } : o));
  const label = (p: NewProduct) => [p.seen.brand, p.seen.line, p.seen.name].filter(Boolean).join(' ');

  return (
    <div className="shell" style={{ paddingBottom: 16 }}>
      <AppBar title={t.products} back={() => router.push('/admin')} />
      <div className="body">
        <div className="seg">{TABS.map(x => <button key={x} className={`pill ${tab === x ? 'on' : ''}`} onClick={() => setTab(x)}>{x === 'ready' ? t.ready : x === 'needs_person' ? t.needsPerson : t.published}</button>)}</div>
        {rows && !rows.length && <div className="note n">{t.nothingWaiting}</div>}
        {rows?.map(p => (
          <button key={p.id} className="cand" onClick={() => setOpen(p)}>
            {p.images[0] || p.shelf_photo_url ? <img src={p.images[0] ?? p.shelf_photo_url!} alt="" style={{ width: 48, height: 48, borderRadius: 10, objectFit: 'cover' }} /> : <span className="thumb"><Icon n="box" /></span>}
            <span className="grow" style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
              <span className="h">{p.title_en ?? label(p)}</span>
              <span className="s">{label(p)}{p.seen.size ? ` · ${p.seen.size}` : ''}</span>
              <span className="row" style={{ gap: 6, flexWrap: 'wrap' }}>
                {p.match_confidence != null && p.match_confidence > 0 && <Chip tone={p.status === 'ready' ? 'green' : 'yellow'}>{fmt(t.confidence, { n: Math.round(p.match_confidence * 100) })}</Chip>}
                {p.source_name && <Chip>{p.source_name}</Chip>}
              </span>
            </span>
          </button>
        ))}
      </div>
      {open && (
        <Sheet onClose={() => setOpen(null)}>
          <h3>{label(open)}</h3>
          <div className="imgs">
            {open.shelf_photo_url && <img src={open.shelf_photo_url} alt={t.seenOnShelf} title={t.seenOnShelf} />}
            {open.images.map(u => <img key={u} src={u} alt="" />)}
          </div>
          {open.match_reasons && <div className={`note ${open.status === 'ready' ? 'g' : 'y'}`}>{open.match_reasons}</div>}
          {open.source_url && <a className="link" href={open.source_url} target="_blank" rel="noreferrer noopener">{t.source}: {open.source_name ?? open.source_url}</a>}
          {open.status !== 'published' && (
            <>
              <label className="lbl">{t.titleEn}</label><div className="field"><input value={open.title_en ?? ''} onChange={e => set('title_en', e.target.value)} /></div>
              <label className="lbl">{t.titleAr}</label><div className="field"><input dir="rtl" value={open.title_ar ?? ''} onChange={e => set('title_ar', e.target.value)} /></div>
              <label className="lbl">{t.descEn}</label><textarea className="ta" value={open.desc_en ?? ''} onChange={e => set('desc_en', e.target.value)} />
              <label className="lbl">{t.descAr}</label><textarea className="ta" dir="rtl" value={open.desc_ar ?? ''} onChange={e => set('desc_ar', e.target.value)} />
              <label className="lbl">{t.sourceUrl}</label><div className="field"><input dir="ltr" value={open.source_url ?? ''} onChange={e => set('source_url', e.target.value)} /></div>
              <label className="lbl">{t.moq}</label><div className="field"><input inputMode="numeric" value={open.moq} onChange={e => set('moq', Number(e.target.value.replace(/\D/g, '')) || 1)} /></div>
              <button className="btn p" disabled={busy} onClick={() => act('publish')}><Icon n="upload" />{t.publish}</button>
              <button className="btn danger" disabled={busy} onClick={() => act('reject')}>{t.reject}</button>
            </>
          )}
        </Sheet>
      )}
    </div>
  );
}

export default function ProductsPage() {
  return <Guard roles={['admin']}><Suspense fallback={<div className="shell" />}><Products /></Suspense></Guard>;
}
