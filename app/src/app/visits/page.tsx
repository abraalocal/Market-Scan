'use client';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useApp } from '@/lib/app-context';
import { loadRows } from '@/lib/visits';
import { Guard, Icon, OfflineBar, TabBar } from '@/components/ui';
import { routeFor, VisitRow, type Row } from '@/components/VisitRow';

const FILTERS = ['all', 'review', 'pending_approval', 'live'] as const;

function Visits() {
  const { t, queued } = useApp();
  const router = useRouter();
  const [rows, setRows] = useState<Row[]>([]);
  const [offline, setOffline] = useState(false);
  const [f, setF] = useState<(typeof FILTERS)[number]>('all');
  useEffect(() => { loadRows().then(r => { setRows(r.rows); setOffline(r.offline); }); }, [queued]);
  const shown = rows.filter(r => f === 'all' || (r.kind === 'server' && r.v.status === f));
  return (
    <div className="shell">
      <OfflineBar />
      <header className="bar"><div className="grow"><h1>{t.visits}</h1></div></header>
      <div className="body">
        <div className="seg">{FILTERS.map(x => <button key={x} className={`pill ${f === x ? 'on' : ''}`} onClick={() => setF(x)}>{x === 'all' ? t.all : t.statusLabel[x]}</button>)}</div>
        {offline && <div className="note n"><Icon n="off" />{t.offlineList}</div>}
        {shown.length ? <div className="list">{shown.map(r => <VisitRow key={r.kind === 'local' ? r.d.uuid : r.v.uuid} r={r} onClick={() => { const to = routeFor(r); if (to) router.push(to); }} />)}</div>
          : <div className="note n">{t.noVisits}</div>}
      </div>
      <TabBar />
    </div>
  );
}

export default function VisitsPage() {
  return <Guard roles={['field_sales', 'admin']}><Visits /></Guard>;
}
