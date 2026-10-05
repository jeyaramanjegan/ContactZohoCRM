<?php
namespace Contact\ZohoCRM\Api\Data;

interface SyncLogInterface
{
    public function getLogId(): int;
    public function getCustomerId(): int;
    public function getAction(): string;
    public function getStatus(): string;
    public function getResponse(): ?string;
    public function getErrorMessage(): ?string;
    public function getZohoId(): ?string;
    public function getCreatedAt(): string;
    public function getRetryCount(): int;
}
