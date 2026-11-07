# Market Scan · Capture App

Next.js 14 (App Router, TypeScript), installable mobile web app, English and Arabic (RTL). Talks to the Laravel backend in `../backend` (contract: `../docs/openapi.yaml`).

```bash
cp .env.example .env.local      # NEXT_PUBLIC_API_URL=http://localhost:8000/api
npm install && npm run dev      # http://localhost:3000
npm run lint && npx tsc --noEmit
npm run build && npm start      # production build; the service worker (offline shell) runs only here
```

| Path | What |
|---|---|
| `src/app/*` | Screens: login, home, find, new-shop, capture, processing, review, done, visits, targets, profile, owner, owner/shop, admin, admin/changes, admin/products |
| `src/lib/api.ts` | API client |
| `src/lib/local.ts`, `sync.ts` | Visits on the phone (IndexedDB) and their idempotent upload (FR-07) |
| `src/lib/i18n.ts` | Every string in English and Arabic |
| `public/sw.js` | Offline app shell |

Visit screens take the visit id as `?v=<uuid>` so the service worker serves them offline from one cached page.
