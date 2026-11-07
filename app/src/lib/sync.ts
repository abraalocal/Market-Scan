// Uploads visits saved on the phone (FR-07). Every step is idempotent (visit uuid, photo client_id), so a sync can
// stop anywhere and the next run carries on. One upload per visit at a time, shared by every caller.
import { api } from './api';
import { local, type Draft } from './local';

const inflight = new Map<string, Promise<Draft>>();

export function syncDraft(d: Draft): Promise<Draft> {
  let p = inflight.get(d.uuid);
  if (!p) {
    p = run(d).finally(() => inflight.delete(d.uuid));
    inflight.set(d.uuid, p);
  }
  return p;
}

async function run(d: Draft): Promise<Draft> {
  d.state = 'uploading';
  await local.put(d);
  try {
    if (!d.created_server) {
      await api.createVisit({ uuid: d.uuid, supplier_id: d.supplier_id, shop_name: d.shop_name, lat: d.lat, lng: d.lng, captured_at: d.captured_at });
      d.created_server = true; await local.put(d);
    }
    for (const p of d.photos) {
      if (p.uploaded) continue;
      await api.uploadPhoto(d.uuid, p.type, p.blob, p.id);
      p.uploaded = true; await local.put(d);
    }
    if (!d.extraction_started) {
      await api.startExtraction(d.uuid);
      d.extraction_started = true;
    }
    await local.remove(d.uuid); // photos now on Abraa storage (NFR-07)
    d.state = 'uploaded';
    return d;
  } catch (e) {
    d.state = 'queued';
    d.error = e instanceof Error ? e.message : String(e);
    await local.put(d);
    throw e;
  }
}

let running: Promise<void> | null = null;
export function syncAll(): Promise<void> {
  if (running) return running;
  running = (async () => {
    for (const d of (await local.all()).filter(x => x.state === 'queued' || x.state === 'uploading')) {
      try { await syncDraft(d); } catch { break; } // still offline: retry on the next 'online' event
    }
  })().finally(() => { running = null; });
  return running;
}
