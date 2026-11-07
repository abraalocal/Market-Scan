// Mirrors docs/openapi.yaml. Keep in sync with the backend.
export type Role = 'field_sales' | 'admin' | 'owner';
export type Locale = 'en' | 'ar';

export interface User { id: number; name: string; phone: string; role: Role; area: string | null; locale: Locale; supplier_id: number | null }

export interface ShopHit {
  id: number; company_name: string; area: string | null; distance_m: number | null; last_visit_at: string | null;
  badge: 'on_abraa' | 'in_ghl' | 'self_reported' | 'new'; phones: string[];
}

export type FieldKey = 'company_name' | 'contact_name' | 'whatsapp' | 'landline' | 'email' | 'website' | 'area';
export interface Field { key: FieldKey; value: string; confidence: number }

export type ProductStatus = 'on_abraa' | 'new' | 'check';
export interface FoundProduct {
  id: string; category: string; brand: string; line: string | null; name: string; size: string | null; barcode: string | null;
  confidence: number; status: ProductStatus; closeup_photo_id: number | null;
}

export interface Candidate {
  key: string; company_name: string; contact_name: string | null; phones: string[]; email: string | null; area: string | null;
  on_abraa: boolean; in_ghl: boolean; match: 'selected' | 'phone' | 'email' | 'name_area' | 'near'; score: number;
}

export type PhotoType = 'card' | 'storefront' | 'shelf' | 'closeup';
export type VisitStatus = 'draft' | 'processing' | 'review' | 'needs_review' | 'submitted' | 'pending_approval' | 'live';

export interface Visit {
  uuid: string; shop_name: string; supplier_id: number | null; source: 'field_visit' | 'owner'; status: VisitStatus;
  photo_counts: Partial<Record<PhotoType, number>>; fields: Field[]; products: FoundProduct[];
  totals: { found: number; on_abraa: number; new: number; check: number };
  candidates: Candidate[]; captured_at: string; error: string | null;
}

export interface CreateVisitInput { uuid: string; supplier_id: number | null; shop_name: string; lat: number | null; lng: number | null; captured_at: string }

export type SupplierChoice = { choice: 'new' } | { choice: 'existing'; key: string };

export interface SubmitResult {
  visit: Visit; outcome: 'supplier_created' | 'update_pending'; supplier_id: number | null; change_id: number | null; keywords: number; new_products: number;
}

export interface Welcome { join_url: string; message: string; sent: boolean }
export interface Targets { visits: number; visit_target: number; suppliers: number; supplier_target: number }
export interface OwnerShop { supplier_id: number; company_name: string; area: string; products: number; self_reported: boolean; pending_approval: boolean; on_abraa: boolean }

export interface AdminSummary {
  waiting: { supplier_changes: number; products_ready: number; products_need_person: number; visits_need_review: number };
  auto_publish: boolean; totals: Targets; members: (Targets & { user_id: number; name: string; area: string })[];
}

export interface SupplierChange {
  id: number; visit_uuid: string; requested_by: string; source: 'field_visit' | 'owner'; company_name: string; on_abraa: boolean; in_ghl: boolean;
  current: Record<string, string | null>; proposed: Record<string, string | null>; keywords_to_add: string[];
  status: 'pending' | 'approved' | 'rejected'; note: string | null; created_at: string;
}

export type NewProductStatus = 'searching' | 'ready' | 'needs_person' | 'published' | 'rejected';
export interface NewProduct {
  id: number; status: NewProductStatus;
  seen: { brand: string | null; line: string | null; name: string; size: string | null; barcode: string | null };
  match_confidence: number | null; match_reasons: string | null; source_name: string | null; source_url: string | null;
  title_en: string | null; title_ar: string | null; desc_en: string | null; desc_ar: string | null;
  specs: Record<string, string>; images: string[]; shelf_photo_url: string | null; moq: number; abraa_product_id: number | null;
}
