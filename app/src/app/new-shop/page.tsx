'use client';
import { Suspense, useEffect, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useApp } from '@/lib/app-context';
import { getPosition, type Pos } from '@/lib/geo';
import { local, newDraft } from '@/lib/local';
import { AppBar, Guard, Icon } from '@/components/ui';

function NewShop() {
  const { t } = useApp();
  const router = useRouter();
  const [name, setName] = useState(useSearchParams().get('name') ?? '');
  const [pos, setPos] = useState<Pos | null | undefined>(undefined);
  useEffect(() => { getPosition().then(setPos); }, []);
  const ok = name.trim().length >= 2;
  async function start() {
    const d = newDraft({ shop_name: name.trim(), supplier_id: null, owner: false, lat: pos?.lat ?? null, lng: pos?.lng ?? null });
    await local.put(d);
    router.push(`/capture?v=${d.uuid}`);
  }
  return (
    <div className="shell">
      <AppBar title={t.addShop} sub={t.addShopSub} back={() => router.push('/find')} />
      <div className="body">
        <label className="lbl" htmlFor="sn">{t.shopName}</label>
        <div className="field"><Icon n="store" /><input id="sn" autoFocus value={name} onChange={e => setName(e.target.value)} onKeyDown={e => { if (e.key === 'Enter' && ok) start(); }} /></div>
        <div className="lbl">{t.location}</div>
        <div className="field s"><Icon n="pin" />{pos === undefined ? '…' : pos ? t.gpsSaved : t.gpsOff}</div>
        <button className="btn p" disabled={!ok} onClick={start}>{t.startCapture}</button>
      </div>
    </div>
  );
}

export default function NewShopPage() {
  return <Guard roles={['field_sales', 'admin']}><Suspense fallback={<div className="shell" />}><NewShop /></Suspense></Guard>;
}
