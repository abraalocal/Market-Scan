'use client';
import { Suspense, useEffect, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { homeFor, useApp } from '@/lib/app-context';
import { api, ApiError } from '@/lib/api';
import { fmt } from '@/lib/i18n';
import type { Field, FoundProduct, Visit } from '@/lib/types';
import { AppBar, Chip, Guard, Icon } from '@/components/ui';

const LOW = 0.8;

function Review() {
  const { t, user, toast } = useApp();
  const router = useRouter();
  const uuid = useSearchParams().get('v') ?? '';
  const [v, setV] = useState<Visit | null>(null);
  const [fields, setFields] = useState<Field[]>([]);
  const [products, setProducts] = useState<FoundProduct[]>([]);
  const [editing, setEditing] = useState<number | null>(null);
  const [draft, setDraft] = useState('');
  const [names, setNames] = useState<Record<string, string>>({});
  const [choice, setChoice] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const [showAll, setShowAll] = useState(false);

  useEffect(() => {
    api.getVisit(uuid).then(x => {
      if (['submitted', 'pending_approval', 'live'].includes(x.status)) { router.replace(`/done?v=${uuid}`); return; }
      setV(x); setFields(x.fields); setProducts(x.products);
      if (x.candidates.length === 1 && x.candidates[0].match === 'selected') setChoice(x.candidates[0].key);
    }).catch(() => setErr(t.error));
  }, [uuid, router, t.error]);

  if (!v) return <div className="shell">{err && <div className="body"><div className="note r">{err}</div></div>}</div>;
  const staffChoice = v.source !== 'owner' && v.candidates.length > 0;
  const by = (s: string) => products.filter(p => p.status === s).length;
  const shown = showAll ? products : products.slice(0, 12);

  function saveField(i: number) {
    setFields(fs => fs.map((f, j) => (j === i ? { ...f, value: draft.trim() || f.value, confidence: 1 } : f)));
    setEditing(null);
  }
  function confirmName(p: FoundProduct) {
    const name = (names[p.id] ?? '').trim();
    if (!name) return;
    setProducts(ps => ps.map(x => (x.id === p.id ? { ...x, name, confidence: 1, status: 'new' } : x)));
  }
  async function submit() {
    if (staffChoice && !choice) { setErr(t.chooseOne); return; }
    setBusy(true); setErr(null);
    try {
      const supplier = !staffChoice ? null : choice === 'new' ? { choice: 'new' as const } : { choice: 'existing' as const, key: choice! };
      const r = await api.submitVisit(uuid, fields, products, supplier);
      try { sessionStorage.setItem(`ms-submit-${uuid}`, JSON.stringify(r)); } catch { /* ignore */ }
      router.replace(`/done?v=${uuid}`);
    } catch (e) { setErr(e instanceof ApiError && e.status === 422 ? e.message : t.error); toast(t.error); } finally { setBusy(false); }
  }

  return (
    <div className="shell" style={{ paddingBottom: 16 }}>
      <AppBar title={t.review} sub={fmt(t.reviewSub, { n: v.photo_counts.shelf ?? 0 })} back={() => router.replace(homeFor(user))} />
      <div className="body">
        {v.error && <div className="note r"><Icon n="alert" />{v.error}</div>}
        <div className="row"><div className="grow h">{t.supplierDetails}</div><Chip tone="green">{t.fromCard}</Chip></div>
        <div className="card" style={{ gap: 0 }}>
          {fields.map((f, i) => {
            const warn = f.confidence < LOW;
            return (
              <div key={f.key} className={`fld ${warn ? 'warn' : ''}`}>
                <div className="grow">
                  <div className="k">{t.fieldLabels[f.key]}{warn ? ` (${t.checkThis})` : ''}</div>
                  {editing === i
                    ? <input className="field" style={{ width: '100%', minHeight: 40 }} autoFocus value={draft} onChange={e => setDraft(e.target.value)} onKeyDown={e => { if (e.key === 'Enter') saveField(i); }} dir="auto" />
                    : <div className="v" dir="auto">{f.value}</div>}
                </div>
                <button className="ibtn" aria-label={`${t.edit} ${t.fieldLabels[f.key]}`} onClick={() => (editing === i ? saveField(i) : (setEditing(i), setDraft(f.value)))}>
                  <Icon n={editing === i ? 'check' : 'edit'} size={18} />
                </button>
              </div>
            );
          })}
        </div>

        {staffChoice && (
          <>
            <div className="h">{t.existingTitle}</div>
            <div className="s" style={{ marginTop: -8 }}>{t.existingSub}</div>
            {v.candidates.map(c => (
              <button key={c.key} className={`cand ${choice === c.key ? 'on' : ''}`} onClick={() => setChoice(c.key)} aria-pressed={choice === c.key}>
                <span className="radio" />
                <span className="grow" style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                  <span className="h">{c.company_name}</span>
                  <span className="s" dir="auto">{[c.contact_name, c.phones[0], c.email, c.area].filter(Boolean).join(' · ')}</span>
                  <span className="row" style={{ gap: 6, flexWrap: 'wrap' }}>
                    <Chip tone="blue">{t.match[c.match]}</Chip>
                    {c.on_abraa && <Chip tone="green">{t.badge.on_abraa}</Chip>}{c.in_ghl && <Chip tone="gray">{t.badge.in_ghl}</Chip>}
                  </span>
                </span>
              </button>
            ))}
            <button className={`cand ${choice === 'new' ? 'on' : ''}`} onClick={() => setChoice('new')} aria-pressed={choice === 'new'}>
              <span className="radio" /><span className="h">{t.itsNew}</span>
            </button>
            {choice && choice !== 'new' && <div className="note b"><Icon n="shield" />{t.pendingNote}</div>}
          </>
        )}

        <div className="row"><div className="grow h">{t.productsFound}</div><b style={{ color: 'var(--accent)' }}>{products.length}</b></div>
        <div className="row" style={{ flexWrap: 'wrap', gap: 6 }}>
          <Chip>{fmt(t.nOn, { n: by('on_abraa') })}</Chip><Chip tone="blue">{fmt(t.nNew, { n: by('new') })}</Chip>
          {by('check') > 0 && <Chip tone="yellow">{fmt(t.toCheck, { n: by('check') })}</Chip>}
        </div>
        <div className="list">
          {shown.map(p => (
            <div key={p.id} className="li" style={{ flexWrap: p.status === 'check' ? 'wrap' : undefined, cursor: 'default' }}>
              <span className="thumb"><Icon n="box" /></span>
              <span className="grow"><span className="h" style={{ display: 'block', fontSize: 14 }}>{[p.brand, p.line, p.name].filter(Boolean).join(' ')}</span><span className="s">{[p.size, p.category].filter(Boolean).join(' · ')}</span></span>
              <Chip tone={p.status === 'on_abraa' ? 'gray' : p.status === 'new' ? 'blue' : 'yellow'}>{p.status === 'on_abraa' ? t.onAbraa : p.status === 'new' ? t.newProduct : t.check}</Chip>
              {p.status === 'check' && (
                <div className="row" style={{ width: '100%' }}>
                  <div className="field grow" style={{ minHeight: 42 }}><input placeholder={t.typeName} value={names[p.id] ?? ''} onChange={e => setNames(n => ({ ...n, [p.id]: e.target.value }))} /></div>
                  <button className="btn p sm" onClick={() => confirmName(p)}>{t.confirm}</button>
                </div>
              )}
            </div>
          ))}
        </div>
        {products.length > 12 && !showAll && <button className="link center" onClick={() => setShowAll(true)}>{t.seeAll} ({products.length})</button>}
        {err && <div className="note r" role="alert"><Icon n="alert" />{err}</div>}
        <button className="btn p" disabled={busy} onClick={submit}>{t.submit}</button>
      </div>
    </div>
  );
}

export default function ReviewPage() {
  return <Guard><Suspense fallback={<div className="shell" />}><Review /></Suspense></Guard>;
}
