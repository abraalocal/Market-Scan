import { api } from './api';
import { local } from './local';
import type { Row } from '@/components/VisitRow';

/** Server visits plus visits still on the phone, newest first. Works offline. */
export async function loadRows(): Promise<{ rows: Row[]; offline: boolean }> {
  const drafts = (await local.all().catch(() => [])).filter(d => d.state !== 'capturing');
  let offline = false;
  let server: Row[] = [];
  try {
    const ids = new Set(drafts.map(d => d.uuid));
    server = (await api.listVisits()).filter(v => !ids.has(v.uuid) && v.status !== 'draft').map(v => ({ kind: 'server' as const, v, at: v.captured_at }));
  } catch { offline = true; }
  const rows: Row[] = [...drafts.map(d => ({ kind: 'local' as const, d, at: d.captured_at })), ...server];
  rows.sort((a, b) => b.at.localeCompare(a.at));
  return { rows, offline };
}
