'use client';
import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useApp } from '@/lib/app-context';
import { api } from '@/lib/api';
import { fmt } from '@/lib/i18n';
import { loadRows } from '@/lib/visits';
import type { Targets } from '@/lib/types';
import { Guard, Icon, OfflineBar, Progress, TabBar } from '@/components/ui';
import { routeFor, VisitRow, type Row } from '@/components/VisitRow';

function Home() {
  const { t, user, queued } = useApp();
  const router = useRouter();
  const [targets, setTargets] = useState<Targets | null>(null);
  const [rows, setRows] = useState<Row[]>([]);
  useEffect(() => {
    api.targets().then(setTargets).catch(() => {});
    loadRows().then(r => setRows(r.rows.slice(0, 5)));
  }, [queued]);

  return (
    <div className="shell">
      <OfflineBar />
      <header className="bar"><div className="grow"><h1>{fmt(t.hello, { name: user?.name ?? '' })}</h1><div className="s">{user?.area}</div></div></header>
      <div className="body">
        {targets && (
          <div className="kpis">
            <div className="kpi"><span className="s">{t.visitsToday}</span><b>{targets.visits}<span className="s"> / {targets.visit_target}</span></b><Progress value={targets.visits} max={targets.visit_target} /></div>
            <div className="kpi"><span className="s">{t.newSuppliers}</span><b>{targets.suppliers}<span className="s"> / {targets.supplier_target}</span></b><Progress value={targets.suppliers} max={targets.supplier_target} /></div>
          </div>
        )}
        <Link href="/find" className="btn p" style={{ minHeight: 64, flexDirection: 'column', gap: 2 }}>
          <span className="row"><Icon n="camera" />{t.startVisit}</span><span style={{ fontWeight: 400, fontSize: 13, opacity: .85 }}>{t.startVisitSub}</span>
        </Link>
        {queued > 0 && <div className="note y"><Icon n="cloud" />{fmt(t.waitingUpload, { n: queued })}</div>}
        <div className="row"><div className="grow h">{t.recent}</div><Link href="/visits" className="link">{t.seeAll}</Link></div>
        {rows.length ? <div className="list">{rows.map(r => <VisitRow key={r.kind === 'local' ? r.d.uuid : r.v.uuid} r={r} onClick={() => { const to = routeFor(r); if (to) router.push(to); }} />)}</div>
          : <div className="note n">{t.noVisits}</div>}
      </div>
      <TabBar />
    </div>
  );
}

export default function HomePage() {
  return <Guard roles={['field_sales', 'admin']}><Home /></Guard>;
}
