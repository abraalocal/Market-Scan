'use client';
import { Suspense, useEffect } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useApp } from '@/lib/app-context';
import { Icon } from '@/components/ui';

/** Landing for the owner invite link / QR (FR-11): abraa.com/join/{code} redirects here as /owner?invite={code}. */
function Owner() {
  const { t, user, ready } = useApp();
  const router = useRouter();
  const invite = useSearchParams().get('invite');
  useEffect(() => { if (ready && user?.role === 'owner') router.replace('/owner/shop'); }, [ready, user, router]);
  return (
    <div className="shell" style={{ paddingBottom: 0 }}>
      <div className="body" style={{ justifyContent: 'center', paddingInline: 24, gap: 18 }}>
        <div className="mark" aria-hidden="true">M</div>
        <h2 className="hero-t">{t.ownerWelcome}</h2>
        <p className="s" style={{ fontSize: 15, margin: 0 }}>{t.ownerWelcomeSub}</p>
        {invite && <div className="note b"><Icon n="link" />{t.invited}</div>}
        <button className="btn p" onClick={() => router.push(`/login?role=owner${invite ? `&invite=${encodeURIComponent(invite)}` : ''}`)}>{t.ownerStart}</button>
      </div>
    </div>
  );
}

export default function OwnerPage() {
  return <Suspense fallback={<div className="shell" />}><Owner /></Suspense>;
}
