'use client';
import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useApp } from '@/lib/app-context';
import { api } from '@/lib/api';
import { local, newDraft } from '@/lib/local';
import type { OwnerShop } from '@/lib/types';
import { Chip, Guard, Icon, OfflineBar } from '@/components/ui';

function Shop() {
  const { t } = useApp();
  const router = useRouter();
  const [shop, setShop] = useState<OwnerShop | null | undefined>(undefined);
  useEffect(() => { api.ownerShop().then(setShop).catch(() => setShop(null)); }, []);

  async function capture() {
    const d = newDraft({ shop_name: shop?.company_name ?? t.myShop, supplier_id: shop?.supplier_id ?? null, owner: true, lat: null, lng: null });
    await local.put(d);
    // A listed shop goes straight to the shelves; a first listing starts with the card.
    router.push(`/capture?v=${d.uuid}${shop ? '&step=2' : ''}`);
  }

  if (shop === undefined) return <div className="shell" aria-busy="true" />;
  return (
    <div className="shell" style={{ paddingBottom: 16 }}>
      <OfflineBar />
      <header className="bar">
        <div className="grow"><h1>{t.myShop}</h1>{shop && <div className="s">{shop.company_name}{shop.area ? ` · ${shop.area}` : ''}</div>}</div>
        <Link href="/profile" className="ibtn" aria-label={t.profile}><Icon n="user" /></Link>
      </header>
      <div className="body">
        {!shop ? (
          <>
            <div className="note b"><Icon n="store" />{t.ownerWelcomeSub}</div>
            <button className="btn p" onClick={capture}><Icon n="camera" />{t.ownerStart}</button>
          </>
        ) : (
          <>
            <div className="kpis">
              <div className="kpi"><span className="s">{t.productsListed}</span><b>{shop.products}</b>{shop.self_reported && <Chip tone="yellow">{t.selfReported}</Chip>}</div>
              <div className="kpi"><span className="s">abraa.com</span><b style={{ fontSize: 16 }}>{shop.on_abraa ? t.liveOnAbraa : t.pendingApproval}</b>
                {shop.pending_approval && shop.on_abraa && <Chip tone="yellow">{t.pendingApproval}</Chip>}</div>
            </div>
            {shop.self_reported && <div className="note y"><Icon n="shield" />{t.selfReportedNote}</div>}
            <button className="btn p" onClick={capture}><Icon n="camera" />{t.addMore}</button>
          </>
        )}
      </div>
    </div>
  );
}

export default function OwnerShopPage() {
  return <Guard roles={['owner']}><Shop /></Guard>;
}
