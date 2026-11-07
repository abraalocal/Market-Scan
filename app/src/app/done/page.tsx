'use client';
import { Suspense, useEffect, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import QRCode from 'qrcode';
import { homeFor, useApp } from '@/lib/app-context';
import { api } from '@/lib/api';
import { fmt } from '@/lib/i18n';
import type { SubmitResult, Visit, Welcome } from '@/lib/types';
import { Guard, Icon, Sheet } from '@/components/ui';

function Done() {
  const { t, user, toast } = useApp();
  const router = useRouter();
  const uuid = useSearchParams().get('v') ?? '';
  const [v, setV] = useState<Visit | null>(null);
  const [res, setRes] = useState<SubmitResult | null>(null);
  const [wa, setWa] = useState<Welcome | null>(null);
  const [qr, setQr] = useState<{ url: string; img: string } | null>(null);
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    try { const s = sessionStorage.getItem(`ms-submit-${uuid}`); if (s) setRes(JSON.parse(s)); } catch { /* ignore */ }
    api.getVisit(uuid).then(setV).catch(() => {});
  }, [uuid]);

  if (!v) return <div className="shell" aria-busy="true" />;
  const company = v.fields.find(f => f.key === 'company_name')?.value || v.shop_name;
  const contact = v.fields.find(f => f.key === 'contact_name')?.value || company;
  const pending = (res?.outcome ?? (v.status === 'pending_approval' ? 'update_pending' : 'supplier_created')) === 'update_pending';

  async function whatsapp() { try { setWa(await api.welcome(uuid, 'whatsapp')); } catch { toast(t.error); } }
  async function showQr() {
    try { const w = await api.welcome(uuid, 'qr'); setQr({ url: w.join_url, img: await QRCode.toDataURL(w.join_url, { width: 400, margin: 1, color: { dark: '#0d1b36', light: '#ffffff' } }) }); }
    catch { toast(t.error); }
  }
  async function copy(text: string) { try { await navigator.clipboard.writeText(text); setCopied(true); } catch { setCopied(false); } }

  return (
    <div className="shell" style={{ paddingBottom: 16 }}>
      <div className="body">
        <div className="okc" style={pending ? { background: 'var(--yellow-soft)', color: 'var(--yellow-ink)' } : undefined}><Icon n={pending ? 'shield' : 'check'} size={36} /></div>
        <div className="center">
          <div className="h" style={{ fontSize: 24 }}>{t.submittedTitle}</div>
          <div className="s" style={{ fontSize: 14, marginTop: 4 }}>{fmt(pending ? t.pendingBody : t.createdBody, { shop: company })}</div>
        </div>
        <div className="stats">
          <div className="stat"><b>{v.totals.found - v.totals.check}</b><span>{t.productsFound}</span></div>
          <div className="stat"><b>{res?.new_products ?? v.totals.new}</b><span>{t.newForListing}</span></div>
          <div className="stat"><b style={{ fontSize: 16, paddingTop: 6 }}>{pending ? '⏳' : '✓'}</b><span>{pending ? t.updatePending : t.supplierCreated}</span></div>
        </div>
        {v.source === 'owner' ? (
          <>
            <div className="note y"><Icon n="shield" />{t.selfReportedNote}</div>
            <button className="btn p" onClick={() => router.replace('/owner/shop')}>{t.myShop}</button>
          </>
        ) : (
          <>
            <div className="card">
              <div className="h">{t.welcomeTitle}</div>
              <div className="s">{fmt(t.welcomeBody, { name: contact })}</div>
              <button className="btn wa" onClick={whatsapp}><Icon n="msg" />{t.sendWelcome}</button>
              <button className="btn sec" onClick={showQr}><Icon n="qr" />{t.showQr}</button>
            </div>
            <button className="btn sec" onClick={() => router.replace(homeFor(user))}>{t.backHome}</button>
          </>
        )}
      </div>
      {wa && (
        <Sheet onClose={() => setWa(null)}>
          <h3>{t.welcomeTitle}</h3>
          <div className={`note ${wa.sent ? 'g' : 'y'}`}>{wa.sent ? t.welcomeSent : t.welcomeNotSent}</div>
          <div className="msg" dir="auto">{wa.message}</div>
          <button className="btn sec" onClick={() => copy(wa.message)}><Icon n={copied ? 'check' : 'copy'} />{copied ? t.copied : t.copyText}</button>
          <button className="btn p" onClick={() => setWa(null)}>{t.close}</button>
        </Sheet>
      )}
      {qr && (
        <Sheet onClose={() => setQr(null)}>
          <h3>{t.qrTitle}</h3>
          <div style={{ display: 'grid', placeItems: 'center' }}><img src={qr.img} alt={qr.url} width={220} height={220} /></div>
          <div className="s center" dir="ltr">{qr.url}</div>
          <button className="btn sec" onClick={() => setQr(null)}>{t.close}</button>
        </Sheet>
      )}
    </div>
  );
}

export default function DonePage() {
  return <Guard><Suspense fallback={<div className="shell" />}><Done /></Suspense></Guard>;
}
