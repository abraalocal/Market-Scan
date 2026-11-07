'use client';
import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { homeFor, useApp } from '@/lib/app-context';

export default function Index() {
  const { user, ready } = useApp();
  const router = useRouter();
  useEffect(() => { if (ready) router.replace(user ? homeFor(user) : '/login'); }, [ready, user, router]);
  return <div className="shell" aria-busy="true" />;
}
