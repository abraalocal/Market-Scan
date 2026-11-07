# abraa.com API needed by Market Scan (proposal for Yasser)

Market Scan never touches the abraa.com database (NFR-06). It calls these endpoints with
`Authorization: Bearer <ABRAA_API_TOKEN>` on `ABRAA_API_URL` (e.g. `https://abraa.com/api/market-scan`).
`backend/app/Remote/HttpAbraaClient.php` implements exactly this; change both together if the shape differs.

## Suppliers
| Call | Body / query | Answer |
|---|---|---|
| `GET /suppliers/search` | `phone` (E.164, repeatable `phone[]`), `email`, `name` (contains, case-insensitive) | `{"data": [Supplier]}` - any match on any given key |
| `GET /suppliers/{id}` | | `{"data": Supplier}` or 404 |
| `POST /suppliers` | Supplier fields without `id` | `{"data": {"id": 123}}` |
| `PATCH /suppliers/{id}` | only the fields to change | `{"data": Supplier}` |
| `POST /suppliers/{id}/keywords` | `{"keywords": ["Hair Care", "Kérastase", ...]}` - add, never remove | `{"data": {"keywords": [...]}}` |

`Supplier`: `{"id", "company_name", "contact_name", "phones": ["+9715..."], "email", "website", "area", "ghl_contact_id", "keywords": []}`.
Phones are E.164. New phones are added to the supplier, existing ones are never removed.

## Products
| Call | Body / query | Answer |
|---|---|---|
| `GET /products/search` | `barcode`, or `brand`, `line`, `name`, `size` | `{"data": [{"id": 1, ...}]}` - exact product only |
| `POST /products` | see below | `{"data": {"id": 456}}` |

`POST /products` body: `title_en`, `title_ar`, `desc_en`, `desc_ar`, `specs` (object), `category_id` (abraa.com category),
`category`, `brand`, `line`, `name`, `size`, `barcode`, `moq`, `price: null`, `images` (HTTPS URLs valid for 24 h: download them),
`source_url`, `market_scan` (`new_product_id`, `visit_uuid`, `supplier_id` = abraa.com supplier that stocks it).

Errors: JSON `{"message": "..."}` with 4xx/5xx. Market Scan retries 5xx and timeouts.
