'use client';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useApp } from '@/lib/app-context';
import { api } from '@/lib/api';
import { fmt } from '@/lib/i18n';
import type { SupplierChange } from '@/lib/types';
import { AppBar, Chip, Guard, Icon, Sheet } from '@/components/ui';

const KEYS = ['company_name', 'contact_name', 'whatsapp', 'landline', 'email', 'website', 'area'];

function Changes() {
  const { t, toast } = useApp();
  const router = useRouter();
  const [rows, setRows] = useState<SupplierChange[] | null>(null);
  const [open, setOpen] = useState<SupplierChange | null>(null);
  const [edit, setEdit] = useState<Record<string, string>>({});
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const load = useCallback(() => { api.admin.changes().then(setRows).catch(() => toast(t.error)); }, [t.error, toast]);
  useEffect(load, [load]);

  function openRow(c: SupplierChange) {
    setOpen(c); setNote('');
    setEdit(Object.fromEntries(KEYS.map(k => [k, c.proposed[k] ?? ''])));
  }
  async function decide(ok: boolean) {
    if (!open) return;
    setBusy(true);
    try {
      if (ok) {
        const changed = Object.fromEntries(Object.entries(edit).filter(([k, v]) => v !== (open.proposed[k] ?? '')));
        await api.admin.approve(open.id, Object.keys(changed).length ? changed : undefined);
        toast(t.approved);
      } else { await api.admin.reject(open.id, note); toast(t.rejected); }
      setOpen(null); load();
    } catch { toast(t.error); } finally { setBusy(false); }
  }

  return (
    <div className="shell" style={{ paddingBottom: 16 }}>
      <AppBar title={t.supplierUpdates} back={() => router.push('/admin')} />
      <div className="body">
        {rows && !rows.length && <div className="note n">{t.nothingWaiting}</div>}
        {rows?.map(c => (
          <button key={c.id} className="cand" onClick={() => openRow(c)}>
            <span className="thumb"><Icon n="store" /></span>
            <span className="grow" style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
              <span className="h">{c.company_name}</span>
              <span className="s">{fmt(t.requestedBy, { name: c.requested_by })} · {new Date(c.created_at).toLocaleString()}</span>
              <span className="row" style={{ gap: 6, flexWrap: 'wrap' }}>
                {c.source === 'owner' && <Chip tone="yellow">{t.ownerListing}</Chip>}
                {c.on_abraa ? <Chip tone="green">{t.badge.on_abraa}</Chip> : <Chip>{t.badge.new}</Chip>}
                {c.in_ghl && <Chip>{t.badge.in_ghl}</Chip>}
                <Chip tone="blue">+{c.keywords_to_add.length} {t.keywords}</Chip>
              </span>
            </span>
          </button>
        ))}
      </div>
      {open && (
        <Sheet onClose={() => setOpen(null)}>
          <h3>{open.company_name}</h3>
          <div className="diff">
            <span className="hd" /><span className="hd">{t.current}</span><span className="hd">{t.proposed}</span>
            {KEYS.map(k => (
              <div key={k} style={{ display: 'contents' }}>
                <span className="s">{t.fieldLabels[k]}</span>
                <span dir="auto">{open.current[k] ?? '–'}</span>
                <input className={`field ${edit[k] !== (open.current[k] ?? '') ? 'chg' : ''}`} style={{ minHeight: 34, padding: '0 8px', fontSize: 13 }} dir="auto"
                  value={edit[k]} onChange={e => setEdit(x => ({ ...x, [k]: e.target.value }))} aria-label={t.fieldLabels[k]} />
              </div>
            ))}
          </div>
          {open.keywords_to_add.length > 0 && (
            <><div className="lbl">{t.keywordsToAdd}</div><div className="row" style={{ flexWrap: 'wrap', gap: 6 }}>{open.keywords_to_add.map(k => <Chip key={k} tone="blue">{k}</Chip>)}</div></>
          )}
          <button className="btn p" disabled={busy} onClick={() => decide(true)}><Icon n="check" />{t.approve}</button>
          <div className="field"><input placeholder={t.rejectReason} value={note} onChange={e => setNote(e.target.value)} /></div>
          <button className="btn danger" disabled={busy} onClick={() => decide(false)}>{t.reject}</button>
        </Sheet>
      )}
    </div>
  );
}

export default function ChangesPage() {
  return <Guard roles={['admin']}><Changes /></Guard>;
}
