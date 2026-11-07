'use client';
import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useApp } from '@/lib/app-context';
import { api } from '@/lib/api';
import type { AdminSummary } from '@/lib/types';
import { Guard, Icon, Progress, TabBar } from '@/components/ui';

function Admin() {
  const { t, toast } = useApp();
  const [s, setS] = useState<AdminSummary | null>(null);
  useEffect(() => { api.admin.summary().then(setS).catch(() => toast(t.error)); }, [t.error, toast]);
  async function toggle() {
    if (!s) return;
    try { const r = await api.admin.saveSettings(!s.auto_publish); setS({ ...s, auto_publish: r.auto_publish }); } catch { toast(t.error); }
  }
  if (!s) return <div className="shell" aria-busy="true"><TabBar /></div>;
  const tile = (href: string, label: string, n: number, icon: string) => (
    <Link href={href} className="kpi"><span className="row s"><Icon n={icon} size={16} />{label}</span><b style={{ color: n ? 'var(--accent)' : undefined }}>{n}</b></Link>
  );
  return (
    <div className="shell">
      <header className="bar"><div className="grow"><h1>{t.admin}</h1><div className="s">{t.waitingApproval}</div></div></header>
      <div className="body">
        <div className="kpis">
          {tile('/admin/changes', t.supplierUpdates, s.waiting.supplier_changes, 'store')}
          {tile('/admin/products?status=ready', t.productsReady, s.waiting.products_ready, 'box')}
          {tile('/admin/products?status=needs_person', t.productsNeedPerson, s.waiting.products_need_person, 'alert')}
          {tile('/visits', t.visitsNeedReview, s.waiting.visits_need_review, 'list')}
        </div>
        <div className="card"><div className="row">
          <div className="grow"><div className="h">{t.autoPublish}</div><div className="s">{t.autoPublishSub}</div></div>
          <button className={`switch ${s.auto_publish ? 'on' : ''}`} role="switch" aria-checked={s.auto_publish} aria-label={t.autoPublish} onClick={toggle} />
        </div></div>
        <div className="h">{t.team}</div>
        <div className="list">
          {s.members.map(m => (
            <div key={m.user_id} className="li" style={{ cursor: 'default' }}>
              <span className="thumb">{m.name[0]}</span>
              <span className="grow"><span className="h" style={{ display: 'block' }}>{m.name}</span><span className="s">{m.area}</span><Progress value={m.visits} max={m.visit_target} /></span>
              <span className="h">{m.visits}/{m.visit_target} · {m.suppliers}</span>
            </div>
          ))}
        </div>
      </div>
      <TabBar />
    </div>
  );
}

export default function AdminPage() {
  return <Guard roles={['admin']}><Admin /></Guard>;
}
