'use client';
import { useEffect, useState } from 'react';
import { useApp } from '@/lib/app-context';
import { api } from '@/lib/api';
import type { Targets } from '@/lib/types';
import { Chip, Guard, Progress, TabBar } from '@/components/ui';

function TargetsView() {
  const { t } = useApp();
  const [x, setX] = useState<Targets | null>(null);
  useEffect(() => { api.targets().then(setX).catch(() => {}); }, []);
  const item = (label: string, v: number, max: number) => (
    <div className="card">
      <div className="row"><div className="grow h">{label}</div><Chip tone={v >= max ? 'green' : 'blue'}>{v >= max ? t.done : t.onTrack}</Chip></div>
      <div style={{ fontSize: 30, fontWeight: 700 }}>{v}<span className="s"> / {max}</span></div>
      <Progress value={v} max={max} />
    </div>
  );
  return (
    <div className="shell">
      <header className="bar"><div className="grow"><h1>{t.targets}</h1><div className="s">{t.targetToday}</div></div></header>
      <div className="body">{x && <>{item(t.visitsToday, x.visits, x.visit_target)}{item(t.newSuppliers, x.suppliers, x.supplier_target)}</>}</div>
      <TabBar />
    </div>
  );
}

export default function TargetsPage() {
  return <Guard roles={['field_sales', 'admin']}><TargetsView /></Guard>;
}
