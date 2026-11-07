# Market Scan · Requirements

Field sales staff (and shop owners) photograph shop shelves and business cards. The system turns those photos into:

1. **One supplier record** per shop, created or updated on **abraa.com and GoHighLevel (GHL) at the same time**.
2. **Product keywords (tags)** on that supplier: category, brand, line and product of everything on the shelves.
3. **New products on abraa.com**: products not yet on abraa.com are looked up on reliable web sources; an exact match is listed with its details and images, anything else waits for a person.

RFQ matching is **out of scope**: it is an existing system that reads the suppliers and keywords this project writes.

Requirement IDs are stable; reference them in commits and tests.

## 1. Roles

| Role | Who | Can |
|---|---|---|
| Field sales | Abraa sales team | Visit any shop, capture, review, choose which existing supplier a shop is, see own targets |
| Admin | Omar or a team lead | Everything field sales can, plus: approve or reject supplier updates and new products, switch auto-publishing on/off, team view |
| Business owner | Supplier owner invited by link or QR | List and photograph their own shop only |

## 2. Flow

```
Capture App visit ─► AI reads card + shelves ─► review on the phone ─► submit
   ├─ supplier: search abraa.com + GHL ─► no match: create on both now
   │                                     └► match / near match: sales picks it ─► update waits for admin ─► push to both
   ├─ keywords: product-level tags ─► pushed with the supplier to abraa.com + GHL
   └─ products not on abraa.com ─► web lookup ─► exact match: auto-publish (switch on) or one-click admin approval
                                             └► no exact match: pending for a person
```

## 3. Functional requirements

### 3.1 Capture App
| ID | Requirement |
|---|---|
| FR-01 | Login with mobile number and 6-digit OTP (WhatsApp/SMS). User picks Abraa sales team or Business owner. No passwords. |
| FR-02 | English and Arabic, right-to-left in Arabic, on every screen. |
| FR-03 | Find shop: search by name or phone; list shops within 200 m by GPS. |
| FR-04 | Duplicate warning when a typed phone already belongs to a supplier, with "Update that shop". |
| FR-05 | Guided capture: business card → shop front → shelves → close-ups. Minimum 1 card, 1 shop front, 1 shelf; close-ups optional. Owners may skip the shop front. |
| FR-06 | Photos from the camera or the gallery, compressed on the phone. |
| FR-07 | Offline: a full visit can be captured without signal and uploads by itself when back online. Opening the app offline keeps the session. |
| FR-08 | AI review before submit: card fields and products, low-confidence ones highlighted, every field editable, unclear product names confirmable. |
| FR-09 | Visit status: Waiting (on phone) → Processing → Review → Submitted → Pending approval / Live. |
| FR-10 | Daily targets per person (visits, new suppliers); admin team view with items waiting for approval. *(carried over — confirm)* |
| FR-11 | Owner mode: invite link/QR, same capture, "My shop" with product count and approval state. No buyer requests screen. |

### 3.2 AI reading
| ID | Requirement |
|---|---|
| FR-20 | Business card → company, contact, mobile/WhatsApp, landline, email, website, area, each with a confidence score. |
| FR-21 | Shelves → every product: category, brand, line, name, size, barcode when visible, with confidence. |
| FR-22 | Close-ups mapped to products; used as the product photo when no better image is found. |
| FR-23 | Brand and line names normalised (accents, spelling, Arabic, aliases) so one product from many shops is one product reference. |

### 3.3 Supplier: create or update on abraa.com and GHL
| ID | Requirement |
|---|---|
| FR-30 | On review, the backend searches for the supplier on **abraa.com and GHL** (and the local record): any phone, then email, then company name + area. Exact hits and near hits (similar name in the same area, phone differing by format) are returned as candidates. |
| FR-31 | **No candidate:** the supplier is created on abraa.com and on GHL immediately on submit, with its product keywords. Both ids are stored and linked to each other. |
| FR-32 | **Candidates found:** sales staff must choose one candidate or "It's a new supplier". Choosing a candidate creates a **supplier update waiting for admin approval**: old and new values side by side, plus keywords to add. Owners never see this step. |
| FR-33 | Admin approves or rejects each update (and may edit values first). Approved: the supplier is updated on abraa.com and GHL and gets the new keywords. Rejected: nothing is pushed; the reason is kept. |
| FR-34 | An existing record is never overwritten silently: filled fields change only through an approved update. New phone numbers are added, not replaced. |
| FR-35 | Owner listings of their own shop are also updates waiting for admin approval *(assumption — confirm)*. |
| FR-36 | abraa.com and GHL stay linked: the abraa.com supplier stores the GHL contact id and vice versa where the APIs allow; every push is logged with its result and retried on failure. |

### 3.4 Product keywords (tags)
| ID | Requirement |
|---|---|
| FR-40 | Every confirmed product becomes a product-level tag on the supplier; its category, brand and line come from the product tree. |
| FR-41 | Keywords sent to abraa.com and GHL: category, brand, line and product names (English; Arabic where known), de-duplicated. GHL tag count capped by config. |
| FR-42 | Each tag stores the visit, evidence photo, first and last seen. A new field visit refreshes tags seen and marks the others "not seen on last visit" (kept, not deleted). |
| FR-43 | Owner-added tags are marked self-reported until a field visit confirms them. |

### 3.5 New products on abraa.com
| ID | Requirement |
|---|---|
| FR-50 | Each confirmed product is checked against abraa.com (barcode, then brand + line + name + size). Listed: tag only. Not listed: a new-product record is created. |
| FR-51 | The new product is looked up on reliable sources through a pluggable search driver (web search API now; Amazon Product Advertising API later; brand sites). Marketplaces' terms are respected: official APIs or licensed data, no scraping that a site forbids. |
| FR-52 | AI compares candidates with what was seen on the shelf (brand, line, name, size, barcode, packaging photo) and decides **exact match / not exact**, with confidence and reasons. |
| FR-53 | Exact match: title and description (EN + AR), key specs, category, size, images and the source URL are filled in. MOQ 12 (24 for items ≤ 100 ml/g); no price. |
| FR-54 | **Auto-publish switch** (admin, on/off): on → exact matches are listed on abraa.com straight away; off → they wait for one-click admin approval. Default **off**. |
| FR-55 | Not exact or not found: status "Needs a person"; admin can edit, attach a source, publish or reject. |
| FR-56 | Every published product links to the visit, the shelf/close-up photo and the source it came from. |

### 3.6 Admin
| ID | Requirement |
|---|---|
| FR-60 | Admin screen in the Capture App (admin role only): supplier updates waiting, products waiting (ready / needs a person), auto-publish switch. |
| FR-61 | Every approve, reject and edit is recorded with who and when. |

## 4. Non-functional
| ID | Requirement |
|---|---|
| NFR-01 | Installable mobile web app (Android + iPhone). |
| NFR-02 | Full visit capturable offline; photos compressed on the phone (max 1600 px). |
| NFR-03 | AI review ready within ~1 minute of the last photo. |
| NFR-04 | English and Arabic everywhere user-facing. |
| NFR-05 | Least access: owners see only their shop; only admins approve; OTP login. |
| NFR-06 | Nothing writes into the abraa.com database directly: abraa.com is reached only through its API. Market Scan keeps its own `ms_` tables. |
| NFR-07 | Avoid faces in photos; photos kept on Abraa storage and removed from phones after upload. |
| NFR-08 | Every supplier change, tag and product links to its visit and photos; every push to abraa.com/GHL is logged. |
| NFR-09 | AI and search cost tracked per visit. |

## 5. Open items
- **Yasser:** abraa.com API for suppliers (search, create, update, keywords) and products (search, create with images). Until it exists the backend runs a stand-in driver.
- **Search provider** for product lookup (Brave Search API, SerpAPI or similar) and its key. Google's Custom Search API is closed to new customers and ends on 1 Jan 2027.
- **Amazon Product Advertising API** account (later).
- **Mostafa:** OTP channel (GHL or WhatsApp Business API); GHL custom field for the abraa.com supplier id.

## 6. Acceptance criteria
1. A visit to a shop not on abraa.com or GHL creates the supplier on both, linked, with its keywords.
2. A visit to a shop that exists on either side shows it as a candidate; choosing it creates an update that changes nothing until an admin approves; after approval both sides show the new data and keywords.
3. No duplicate supplier is created when the phone already exists on abraa.com, GHL or locally.
4. An offline visit uploads by itself once back online.
5. A product already on abraa.com becomes a tag only; a new product with an exact web match is published (switch on) or waits for one click (switch off); a product with no exact match waits as "Needs a person".
6. Owner-listed products show as self-reported until a field visit confirms them.
7. Every push to abraa.com and GHL appears in the sync log with its result.
