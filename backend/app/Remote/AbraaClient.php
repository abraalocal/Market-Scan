<?php

namespace App\Remote;

/**
 * abraa.com, reached only through its API (NFR-06). Suppliers are plain arrays:
 * ['id' => int, 'company_name', 'contact_name', 'phones' => string[], 'email', 'website', 'area', 'ghl_contact_id', 'keywords' => string[]]
 */
interface AbraaClient
{
    /** Suppliers matching any of: phones (E.164), email, company name (contains). */
    public function searchSuppliers(array $phones = [], ?string $email = null, ?string $name = null): array;

    public function getSupplier(int $id): ?array;

    /** @return int the new abraa.com supplier id */
    public function createSupplier(array $data): int;

    public function updateSupplier(int $id, array $data): void;

    /** Adds keywords (category, brand, line, product names) to the supplier. */
    public function addKeywords(int $id, array $keywords): void;

    /** abraa.com product id for a product (barcode, then brand + line + name + size), or null. */
    public function findProduct(array $product): ?int;

    /** @return int the new abraa.com product id */
    public function createProduct(array $data): int;
}
