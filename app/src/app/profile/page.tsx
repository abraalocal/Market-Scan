'use client';
import { useRouter } from 'next/navigation';
import { useApp } from '@/lib/app-context';
import { Guard, Icon, TabBar } from '@/components/ui';

function Profile() {
  const { t, user, locale, setLocale, workOffline, setWorkOffline, signOut } = useApp();
  const router = useRouter();
  return (
    <div className="shell">
      <header className="bar"><div className="grow"><h1>{t.profile}</h1></div></header>
      <div className="body">
        <div className="card">
          <div className="h" style={{ fontSize: 18 }}>{user?.name}</div>
          <div className="s" dir="ltr" style={{ textAlign: 'start' }}>{user?.phone}</div>
          <div className="s">{user?.role === 'admin' ? t.admin : user?.role === 'owner' ? t.roleOwner : t.roleSales}{user?.area ? ` · ${user.area}` : ''}</div>
        </div>
        <div className="lbl">{t.language}</div>
        <div className="seg">
          <button className={`pill ${locale === 'en' ? 'on' : ''}`} onClick={() => setLocale('en')}>English</button>
          <button className={`pill ${locale === 'ar' ? 'on' : ''}`} onClick={() => setLocale('ar')}>العربية</button>
        </div>
        {user?.role !== 'owner' && (
          <div className="card"><div className="row">
            <div className="grow"><div className="h">{t.workOffline}</div><div className="s">{t.workOfflineSub}</div></div>
            <button className={`switch ${workOffline ? 'on' : ''}`} role="switch" aria-checked={workOffline} aria-label={t.workOffline} onClick={() => setWorkOffline(!workOffline)} />
          </div></div>
        )}
        <button className="btn danger" onClick={() => { signOut(); router.replace('/login'); }}><Icon n="back" />{t.signOut}</button>
      </div>
      {user?.role !== 'owner' && <TabBar />}
    </div>
  );
}

export default function ProfilePage() {
  return <Guard><Profile /></Guard>;
}
