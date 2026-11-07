<?php

namespace App\Remote;

/**
 * GoHighLevel contacts. Contacts are plain arrays:
 * ['id' => string, 'company_name', 'contact_name', 'phones' => string[], 'email', 'website', 'area', 'abraa_supplier_id', 'tags' => string[]]
 */
interface GhlClient
{
    public function searchContacts(array $phones = [], ?string $email = null, ?string $name = null): array;

    public function getContact(string $id): ?array;

    /** @return string the new GHL contact id */
    public function createContact(array $data, array $tags): string;

    public function updateContact(string $id, array $data): void;

    public function addTags(string $id, array $tags): void;

    public function sendWhatsApp(string $contactId, string $text): void;
}
