<?php
namespace Contact\ZohoCRM\Api;

interface ZohoClientInterface
{
    public function createContact(array $data): array;
    public function updateContact(string $recordId, array $data): array;
    public function searchContactByEmail(string $email): ?array;
    public function getContact(string $recordId): ?array;
    public function deleteContact(string $recordId): bool;
}
