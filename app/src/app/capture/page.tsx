'use client';
import { Suspense, useCallback, useEffect, useMemo, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { homeFor, useApp } from '@/lib/app-context';
import { compress } from '@/lib/image';
import { fmt } from '@/lib/i18n';
import { local, type Draft } from '@/lib/local';
import type { PhotoType } from '@/lib/types';
import { AppBar, Guard, Icon, OfflineBar, Sheet } from '@/components/ui';

// FR-05: card → shop front → shelves → close-ups. Owners may skip the shop front, and the card when their shop is listed.
const STEPS: { type: PhotoType; min: (d: Draft) => number; hint: 'hintCard' | 'hintStorefront' | 'hintShelf' | 'hintCloseup' }[] = [
  { type: 'card', min: d => (d.owner && d.supplier_id ? 0 : 1), hint: 'hintCard' },
  { type: 'storefront', min: d => (d.owner ? 0 : 1), hint: 'hintStorefront' },
  { type: 'shelf', min: () => 1, hint: 'hintShelf' },
  { type: 'closeup', min: () => 0, hint: 'hintCloseup' },
];

function Capture() {
  const { t, online, workOffline, refreshQueue, user } = useApp();
  const router = useRouter();
  const params = useSearchParams();
  const uuid = params.get('v') ?? '';
  const [d, setD] = useState<Draft | null>(null);
  const [step, setStep] = useState(Number(params.get('step') ?? 0));
  const [busy, setBusy] = useState(false);
  const [leaving, setLeaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [urls, setUrls] = useState<Record<string, string>>({});

  useEffect(() => { local.get(uuid).then(x => (x ? setD(x) : router.replace(homeFor(user)))); }, [uuid, router, user]);
  useEffect(() => {
    if (!d) return;
    const next: Record<string, string> = {};
    d.photos.forEach(p => { next[p.id] = urls[p.id] ?? URL.createObjectURL(p.blob); });
    setUrls(next);
  }, [d]); // eslint-disable-line react-hooks/exhaustive-deps

  const photos = useMemo(() => (d ? d.photos.filter(p => p.type === STEPS[step].type) : []), [d, step]);
  const add = useCallback(async (files: FileList | null) => {
    if (!d || !files?.length) return;
    setBusy(true);
    const next = { ...d, photos: [...d.photos] };
    for (const f of Array.from(files)) next.photos.push({ id: crypto.randomUUID().replace(/-/g, ''), type: STEPS[step].type, blob: await compress(f) });
    await local.put(next); setD(next); setBusy(false);
  }, [d, step]);
  async function remove(id: string) {
    if (!d) return;
    const next = { ...d, photos: d.photos.filter(p => p.id !== id) };
    await local.put(next); setD(next);
  }
  async function finish() {
    if (!d) return;
    await local.put({ ...d, state: 'queued' });
    await refreshQueue();
    if (!online || workOffline) { setSaved(true); return; }
    router.replace(`/processing?v=${d.uuid}`);
  }
  async function leave() {
    if (d) await local.remove(d.uuid);
    router.replace(homeFor(user));
  }

  if (!d) return <div className="shell" aria-busy="true" />;
  if (saved) return (
    <div className="shell"><div className="body" style={{ justifyContent: 'center' }}>
      <div className="okc" style={{ background: 'var(--yellow-soft)', color: 'var(--yellow-ink)' }}><Icon n="cloud" size={36} /></div>
      <div className="center"><div className="h" style={{ fontSize: 22 }}>{t.savedOffline}</div><p className="s">{t.savedOfflineBody}</p></div>
      <button className="btn p" onClick={() => router.replace(homeFor(user))}>{t.backHome}</button>
    </div></div>
  );

  const s = STEPS[step];
  const ok = photos.length >= s.min(d);
  const last = step === STEPS.length - 1;
  return (
    <div className="shell" style={{ paddingBottom: 16 }}>
      <OfflineBar />
      <AppBar title={d.owner ? t.myShop : d.shop_name} sub={`${fmt(t.stepOf, { n: step + 1 })} · ${t[s.type]}`} back={() => (step > 0 ? setStep(step - 1) : setLeaving(true))} />
      <div className="body">
        <div className="steps">{STEPS.map((x, i) => <div key={x.type} className={i < step ? 'done' : i === step ? 'cur' : ''}><i />{t[x.type]}</div>)}</div>
        <div className="note b"><Icon n="camera" />{t[s.hint]}</div>
        <div className="grid">
          {photos.length ? photos.map(p => (
            <div className="ph" key={p.id}>
              {urls[p.id] && <img src={urls[p.id]} alt="" />}
              <button className="del" aria-label="Delete photo" onClick={() => remove(p.id)}><Icon n="x" size={12} /></button>
            </div>
          )) : <span className="empty">{step === 3 ? t.optionalCloseups : t.noPhotos}</span>}
        </div>
        <div className="cap">
          <label className="round" aria-label="Upload from gallery"><Icon n="upload" /><input type="file" accept="image/*" multiple onChange={e => { add(e.target.files); e.target.value = ''; }} /></label>
          <label className="shutter" aria-label="Take photo"><i style={busy ? { opacity: .5 } : undefined} /><input type="file" accept="image/*" capture="environment" onChange={e => { add(e.target.files); e.target.value = ''; }} /></label>
          <span style={{ width: 52 }} />
        </div>
        <button className={`btn ${ok ? 'y' : 'sec'}`} disabled={!ok || busy} onClick={() => (last ? finish() : setStep(step + 1))}>
          {last ? (photos.length ? t.finishReview : t.skipCloseups) : `${t.next}: ${t[STEPS[step + 1].type]}`}
        </button>
      </div>
      {leaving && (
        <Sheet onClose={() => setLeaving(false)}>
          <h3>{t.leaveTitle}</h3>
          <button className="btn p" onClick={() => setLeaving(false)}>{t.keepCapturing}</button>
          <button className="btn danger" onClick={leave}>{t.leaveVisit}</button>
        </Sheet>
      )}
    </div>
  );
}

export default function CapturePage() {
  return <Guard><Suspense fallback={<div className="shell" />}><Capture /></Suspense></Guard>;
}
