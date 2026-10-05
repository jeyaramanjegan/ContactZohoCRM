<?php
namespace Contact\ZohoCRM\Api;

interface ZohoAuthInterface
{
    public function getAccessToken(): string;
    public function getAuthorizationUrl(): string;
    public function exchangeCode(string $code): array;
}
