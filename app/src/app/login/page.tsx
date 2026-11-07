'use client';
import { Suspense, useEffect, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { homeFor, useApp } from '@/lib/app-context';
import { api, ApiError } from '@/lib/api';
import { AppBar, Icon } from '@/components/ui';

function Login() {
  const { t, locale, setLocale, setSession, user } = useApp();
  const router = useRouter();
  const params = useSearchParams();
  const invite = params.get('invite') ?? undefined;
  const next = params.get('next');
  const [role, setRole] = useState<'field_sales' | 'owner'>(params.get('role') === 'owner' || invite ? 'owner' : 'field_sales');
  const [phone, setPhone] = useState('');
  const [code, setCode] = useState('');
  const [step, setStep] = useState<'phone' | 'code'>('phone');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => { if (user) router.replace(next && next !== '/login' ? next : homeFor(user)); }, [user, router, next]);

  const digits = phone.replace(/\D/g, '');
  const full = '+971' + digits.replace(/^0/, '');
  async function send() {
    setBusy(true); setErr(null);
    try { await api.requestOtp(full, role, invite); setStep('code'); setCode(''); }
    catch (e) { setErr(e instanceof ApiError && e.status === 422 ? e.message : e instanceof ApiError && e.status === 429 ? e.message : t.error); }
    finally { setBusy(false); }
  }
  async function verify(c = code) {
    setBusy(true); setErr(null);
    try { const r = await api.verifyOtp(full, c); setSession(r.token, r.user); }
    catch { setErr(t.wrongCode); } finally { setBusy(false); }
  }

  if (step === 'code') return (
    <div className="shell" style={{ paddingBottom: 0 }}>
      <AppBar title={t.enterCode} sub={`${t.sentTo} +971 ${phone}`} back={() => setStep('phone')} />
      <div className="body">
        <p className="s" style={{ margin: 0, fontSize: 14 }}>{t.codeHelp}</p>
        <input className="otp" inputMode="numeric" autoComplete="one-time-code" maxLength={6} aria-label="6-digit code" placeholder="••••••" autoFocus dir="ltr"
          value={code} onChange={e => { const v = e.target.value.replace(/\D/g, '').slice(0, 6); setCode(v); if (v.length === 6) verify(v); }} />
        {err && <div className="note r" role="alert"><Icon n="alert" />{err}</div>}
        <button className="btn p" disabled={code.length < 6 || busy} onClick={() => verify()}>{t.verify}</button>
        <button className="link center" onClick={send} disabled={busy}>{t.resend}</button>
      </div>
    </div>
  );

  return (
    <div className="shell" style={{ paddingBottom: 0 }}>
      <div className="body" style={{ paddingTop: 'calc(32px + env(safe-area-inset-top))', paddingInline: 22, gap: 16 }}>
        <div className="row"><div className="mark" aria-hidden="true">M</div><div><div className="h" style={{ fontSize: 19 }}>{t.appName}</div><div className="s">{t.tagline}</div></div></div>
        <h2 className="hero-t">{t.heroLogin}</h2>
        <div className="seg" role="group" aria-label={t.language}>
          <button className={`pill ${locale === 'en' ? 'on' : ''}`} onClick={() => setLocale('en')}>English</button>
          <button className={`pill ${locale === 'ar' ? 'on' : ''}`} onClick={() => setLocale('ar')}>العربية</button>
        </div>
        <div className="lbl">{t.iAm}</div>
        <div className="roles">
          <button className={`role ${role === 'field_sales' ? 'on' : ''}`} aria-pressed={role === 'field_sales'} onClick={() => setRole('field_sales')}>
            <Icon n="user" /><span className="h" style={{ fontSize: 14 }}>{t.roleSales}</span><span className="s">{t.roleSalesSub}</span>
          </button>
          <button className={`role ${role === 'owner' ? 'on' : ''}`} aria-pressed={role === 'owner'} onClick={() => setRole('owner')}>
            <Icon n="store" /><span className="h" style={{ fontSize: 14 }}>{t.roleOwner}</span><span className="s">{t.roleOwnerSub}</span>
          </button>
        </div>
        {invite && <div className="note b"><Icon n="link" />{t.invited}</div>}
        <label className="lbl" htmlFor="ph">{t.mobile}</label>
        <div className="field" dir="ltr">
          <span className="h">🇦🇪 +971</span>
          <input id="ph" inputMode="tel" autoComplete="tel-national" placeholder="50 123 4567" value={phone} onChange={e => setPhone(e.target.value)}
            onKeyDown={e => { if (e.key === 'Enter' && digits.length >= 9) send(); }} />
        </div>
        {err && <div className="note r" role="alert"><Icon n="alert" />{err}</div>}
        <button className="btn p" disabled={digits.length < 9 || busy} onClick={send}>{t.sendCode} <span className="flip"><Icon n="arrow" size={18} /></span></button>
        <div className="s center">{t.codeNote}</div>
      </div>
    </div>
  );
}

export default function LoginPage() {
  return <Suspense fallback={<div className="shell" />}><Login /></Suspense>;
}
