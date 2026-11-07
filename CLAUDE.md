# Market Scan

Field sales and shop owners photograph shelves and business cards. Market Scan:
1. creates or updates the **supplier on abraa.com and GoHighLevel together** (updates to existing suppliers wait for admin approval),
2. tags the supplier with **product keywords** (category, brand, line, product),
3. lists **new products** on abraa.com after an exact match on a reliable web source (auto-publish switch), or leaves them for a person.

RFQ matching is a separate, existing system. Do not build it here.

Source of truth: `docs/requirements.md` (FR/NFR ids), `docs/schema.sql`, `docs/openapi.yaml`. If code and docs disagree, fix the code or update the doc in the same commit.

## Layout
| Folder | What |
|---|---|
| `app/` | Capture App: Next.js 14 (App Router, TypeScript), installable mobile web app |
| `backend/` | Laravel 11 API on its own MySQL 8 database; plan in `backend/BUILD.md` |
| `docs/` | Requirements, schema, API contract |

## Rules
- **Never connect to or write into the abraa.com database.** abraa.com is reached only through its API (`AbraaClient`). Do not touch the old abraa.com repository or the earlier `abraa-market-scan` repo.
- Every outside system has a `fake` driver; tests never make real HTTP calls (`Http::preventStrayRequests`).
- All tables start with `ms_`. Phones stored as E.164. Phone-retried endpoints are idempotent (visit `uuid`, photo `client_id`).
- English and Arabic everywhere user-facing (strings in `app/src/lib/i18n.ts`, RTL in Arabic).
- Commit small, one requirement per commit, message starting with the FR id. Run `php artisan test` before every commit.
- Branches: work on `staging`. Do not push to `main`.

## People
- **Omar** — product owner; approves scope and stack changes.
- **Yasser** — backend, abraa.com API, Capture App.
- **Mostafa** — AI, n8n, GoHighLevel.
