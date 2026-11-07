# Market Scan

Photos of shop shelves and business cards → suppliers on abraa.com and GoHighLevel, tagged with the products they stock, and new products listed on abraa.com.

| | |
|---|---|
| Requirements | [`docs/requirements.md`](docs/requirements.md) |
| Database | [`docs/schema.sql`](docs/schema.sql) |
| API contract | [`docs/openapi.yaml`](docs/openapi.yaml) |
| Backend plan | [`backend/BUILD.md`](backend/BUILD.md) |
| abraa.com API needed | [`docs/abraa-api.md`](docs/abraa-api.md) (for Yasser) |
| Instructions for Claude Code | [`CLAUDE.md`](CLAUDE.md) |

## Status
| Part | State |
|---|---|
| Backend (`backend/`) | Built, steps 0–11 of `backend/BUILD.md`; 85 feature tests. Runs on stand-in drivers until the keys and APIs below exist. |
| Capture App (`app/`) | Built: field sales, owner and admin screens, offline queue, English/Arabic. Tested end to end against the backend. |

## Going live needs
- **Anthropic API key** → `EXTRACTION_DRIVER=claude`, `PRODUCT_MATCH_DRIVER=claude`
- **Brave Search API or SerpAPI key** → `PRODUCT_SEARCH_DRIVER=brave` / `serpapi`
- **abraa.com API** per [`docs/abraa-api.md`](docs/abraa-api.md) (Yasser) → `ABRAA_DRIVER=http`; abraa.com category ids in `backend/config/abraa.php`
- **GHL** API key, location id, custom field for the abraa.com id (Mostafa) → `GHL_DRIVER=http`
- **OTP channel** (GHL or WhatsApp Business API) → `OTP_DRIVER=whatsapp`

## Run locally
```bash
cd backend && composer install && cp .env.example .env && php artisan key:generate
php artisan migrate --seed && php artisan serve          # http://localhost:8000 (stand-in abraa.com and GHL)
cd ../app && cp .env.example .env.local && npm install && npm run dev   # http://localhost:3000
```
Logins in local mode (code `123456`): admin `50 000 0001`, field sales `50 000 0002`–`0004`, any other number as Business owner.
