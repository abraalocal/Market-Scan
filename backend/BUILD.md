# Backend build plan (Laravel 11 + MySQL 8)

One step at a time; tests green and a commit (message starting with the FR id) after each step.
Contract: `docs/openapi.yaml`. Schema: `docs/schema.sql`. Tests run on SQLite in memory.

Every outside system sits behind an interface with a **fake** driver, so the whole flow runs and is tested
before the real APIs exist:

| Interface | Drivers | Config |
|---|---|---|
| `OtpSender` | `log`, `whatsapp` | `OTP_DRIVER` |
| `Extractor` (card + shelves) | `fake`, `claude` (default in production), `n8n` | `EXTRACTION_DRIVER`, `ANTHROPIC_API_KEY` |
| `AbraaClient` (suppliers, keywords, products) | `fake`, `http` | `ABRAA_DRIVER`, `ABRAA_API_URL`, `ABRAA_API_TOKEN` |
| `GhlClient` (contacts, tags) | `fake`, `http` | `GHL_DRIVER`, `GHL_API_KEY`, `GHL_LOCATION_ID` |
| `ProductSearch` (web lookup) | `fake`, `brave` (first), `serpapi`, later `amazon` | `PRODUCT_SEARCH_DRIVER`, `BRAVE_API_KEY`, `SERPAPI_KEY` |
| `ProductMatcher` (exact match + details) | `fake`, `claude` | `PRODUCT_MATCH_DRIVER`, `ANTHROPIC_API_KEY` |

The `fake` abraa/GHL drivers keep their data in local tables (`ms_fake_*`, testing only) so search,
create and update behave like the real systems.

## Steps
0. **Project:** Laravel 11, Sanctum, Pest; every table `ms_` prefixed (Laravel's too); CORS for the app.
1. **Schema + models** from `docs/schema.sql`; seeder: 1 admin, 3 field sales, product tree, a few suppliers on the fake abraa/GHL.
2. **Phone + name normalisation:** E.164 (UAE rules), brand/line/company keys (accents, Arabic, aliases, LLC/FZE/Trading).
3. **OTP login (FR-01):** rate limit 3/phone/10 min, hashed codes, 5 tries, team-only for sales, owners created on first verify, invite links. `123456` only when `APP_ENV=local`.
4. **Shops (FR-03, FR-04):** search local + abraa.com + GHL, nearby by GPS, phone check.
5. **Visits + photos (FR-05..FR-09):** idempotent create/upload, 1600 px JPEG, extract with missing-photo 422.
6. **AI reading (FR-20..FR-23):** drivers; product status computed on the server (`on_abraa` via abraa.com product search, `check` < 0.7).
7. **Supplier candidates (FR-30):** on review, search abraa.com + GHL + local: phone → email → name + area, plus near matches (name similarity ≥ 0.85 in the same area); merge the same supplier seen on both sides into one candidate.
8. **Submit (FR-31..FR-35, FR-40..FR-43):** product refs + tags; no candidates or "new" → create on abraa.com and GHL (linked) with keywords; "existing" → `ms_supplier_changes` pending; owners → pending change on their shop. Pushes are queued jobs logged in `ms_sync_log` and retried.
9. **Admin (FR-33, FR-60, FR-61):** list/approve (with edits)/reject supplier changes; approval pushes to both sides.
10. **New products (FR-50..FR-56):** search → AI match → `ready` / `needs_person`; auto-publish switch; publish via `AbraaClient::createProduct` with images (downloaded and stored), MOQ rule; admin edit/publish/reject.
11. **Targets, owner shop, welcome (FR-10, FR-11, FR-12).**
12. **Live run** of the Capture App against the backend; fix anything that does not match the contract.
