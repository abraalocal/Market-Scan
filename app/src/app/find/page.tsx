'use client';
import { useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useApp } from '@/lib/app-context';
import { api } from '@/lib/api';
import { getPosition, type Pos } from '@/lib/geo';
import { fmt } from '@/lib/i18n';
import { local, newDraft } from '@/lib/local';
import type { ShopHit } from '@/lib/types';
import { AppBar, Chip, Guard, Icon, OfflineBar } from '@/components/ui';

const TONE = { on_abraa: 'green', in_ghl: 'blue', self_reported: 'yellow', new: 'gray' } as const;

function Find() {
  const { t } = useApp();
  const router = useRouter();
  const [q, setQ] = useState('');
  const [hits, setHits] = useState<ShopHit[] | null>(null);
  const [dup, setDup] = useState<ShopHit | null>(null);
  const [offline, setOffline] = useState(false);
  const pos = useRef<Pos | null>(null);

  useEffect(() => { getPosition().then(p => { pos.current = p; if (!q) setQ(''); }); }, []); // eslint-disable-line react-hooks/exhaustive-deps
  useEffect(() => {
    const h = setTimeout(async () => {
      try {
        setHits(await api.searchShops(q, pos.current?.lat, pos.current?.lng));
        setDup(q.replace(/\D/g, '').length >= 9 ? await api.checkPhone(q) : null);
        setOffline(false);
      } catch { setOffline(true); setHits([]); }
    }, 300);
    return () => clearTimeout(h);
  }, [q]);

  async function start(s: ShopHit) {
    const d = newDraft({ shop_name: s.company_name, supplier_id: s.id, owner: false, lat: pos.current?.lat ?? null, lng: pos.current?.lng ?? null });
    await local.put(d);
    router.push(`/capture?v=${d.uuid}`);
  }

  return (
    <div className="shell">
      <OfflineBar />
      <AppBar title={t.newVisit} sub={t.newVisitSub} back={() => router.push('/home')} />
      <div className="body">
        <div className="field"><Icon n="search" /><input autoFocus value={q} onChange={e => setQ(e.target.value)} placeholder={t.searchShops} aria-label={t.searchShops} /></div>
        {dup && (
          <div className="note y" style={{ flexDirection: 'column' }}>
            <div className="row h"><Icon n="alert" />{t.dupTitle}</div>
            <div>{fmt(t.dupBody, { phone: dup.phones[0] ?? q, name: dup.company_name })}</div>
            <div className="row"><button className="btn p sm" onClick={() => start(dup)}>{t.updateShop}</button><button className="btn sec sm" onClick={() => setDup(null)}>{t.notSame}</button></div>
          </div>
        )}
        {offline ? <div className="note n"><Icon n="off" />{t.error}</div>
          : <div className="row s h"><Icon n="pin" size={16} />{q ? fmt(t.matches, { n: hits?.length ?? 0 }) : t.nearby}</div>}
        {!!hits?.length && (
          <div className="list">
            {hits.map(s => (
              <button key={s.id} className="li" onClick={() => start(s)}>
                <span className="thumb"><Icon n="store" /></span>
                <span className="grow"><span className="h" style={{ display: 'block' }}>{s.company_name}</span>
                  <span className="s">{[s.area, s.distance_m != null ? `${s.distance_m} m` : null].filter(Boolean).join(' · ')}</span></span>
                <Chip tone={TONE[s.badge]}>{t.badge[s.badge]}</Chip>
              </button>
            ))}
          </div>
        )}
        <Link href={`/new-shop${q && !/\d{5}/.test(q) ? `?name=${encodeURIComponent(q)}` : ''}`} className="btn sec"><Icon n="store" />{t.addShop}</Link>
      </div>
    </div>
  );
}

export default function FindPage() {
  return <Guard roles={['field_sales', 'admin']}><Find /></Guard>;
}
