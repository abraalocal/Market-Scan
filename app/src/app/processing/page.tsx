'use client';
import { Suspense, useEffect, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { homeFor, useApp } from '@/lib/app-context';
import { api } from '@/lib/api';
import { fmt } from '@/lib/i18n';
import { local } from '@/lib/local';
import { syncDraft } from '@/lib/sync';
import { Guard, Icon } from '@/components/ui';

function Processing() {
  const { t, refreshQueue, user } = useApp();
  const router = useRouter();
  const uuid = useSearchParams().get('v') ?? '';
  const [stage, setStage] = useState(0);
  const [info, setInfo] = useState<{ n: number; shop: string } | null>(null);
  const [state, setState] = useState<'working' | 'offline' | 'failed'>('working');

  useEffect(() => {
    // No run-once guard: React runs effects twice in development; each run polls on its own and the upload is
    // shared per visit inside syncDraft, so it still happens once.
    let alive = true;
    let tick: ReturnType<typeof setInterval> | undefined;
    (async () => {
      const d = await local.get(uuid);
      if (d) {
        if (alive) setInfo({ n: d.photos.length, shop: d.owner ? t.myShop : d.shop_name });
        try { await syncDraft(d); await refreshQueue(); } catch { if (alive) setState('offline'); await refreshQueue(); return; }
      }
      if (!alive) return;
      setStage(1);
      let n = 1;
      tick = setInterval(() => { n = Math.min(3, n + 1); if (alive) setStage(n); }, 1200);
      for (let i = 0; i < 120 && alive; i++) {
        try {
          const v = await api.getVisit(uuid);
          if (!d && alive) setInfo({ n: Object.values(v.photo_counts).reduce((a, b) => a + (b ?? 0), 0), shop: v.shop_name });
          if (v.status === 'review') { clearInterval(tick); if (alive) router.replace(`/review?v=${uuid}`); return; }
          if (v.status === 'needs_review') { clearInterval(tick); if (alive) setState('failed'); return; }
          if (['submitted', 'pending_approval', 'live'].includes(v.status)) { clearInterval(tick); if (alive) router.replace(`/done?v=${uuid}`); return; }
        } catch { /* keep polling */ }
        await new Promise(r => setTimeout(r, 2000));
      }
    })();
    return () => { alive = false; clearInterval(tick); };
  }, [uuid, router, refreshQueue, t.myShop]);

  if (state !== 'working') return (
    <div className="shell"><div className="body" style={{ justifyContent: 'center' }}>
      <div className="okc" style={state === 'offline' ? { background: 'var(--yellow-soft)', color: 'var(--yellow-ink)' } : { background: 'var(--red-soft)', color: 'var(--red)' }}><Icon n={state === 'offline' ? 'cloud' : 'alert'} size={36} /></div>
      <div className="center"><div className="h" style={{ fontSize: 22 }}>{state === 'offline' ? t.savedOffline : t.needsReview}</div><p className="s">{state === 'offline' ? t.savedOfflineBody : t.readFailed}</p></div>
      {state === 'failed' && <button className="btn p" onClick={() => router.replace(`/review?v=${uuid}`)}>{t.review}</button>}
      <button className="btn sec" onClick={() => router.replace(homeFor(user))}>{t.backHome}</button>
    </div></div>
  );

  const stages = [t.stUpload, t.stCard, t.stShelves, t.stCatalogue];
  return (
    <div className="shell"><div className="body" style={{ justifyContent: 'center', gap: 22, paddingInline: 28 }}>
      <div className="spin" aria-hidden="true" />
      <div><div className="h" style={{ fontSize: 22 }}>{t.reading}</div>{info && <div className="s">{fmt(t.readingSub, info)}</div>}</div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
        {stages.map((s, i) => <div key={s} className={`stage ${i < stage ? 'ok' : i === stage ? 'on' : ''}`}><span className="dot">{i < stage && <Icon n="check" size={12} />}</span>{s}</div>)}
      </div>
    </div></div>
  );
}

export default function ProcessingPage() {
  return <Guard><Suspense fallback={<div className="shell" />}><Processing /></Suspense></Guard>;
}
