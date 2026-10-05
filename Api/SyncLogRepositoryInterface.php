<?php
namespace Contact\ZohoCRM\Api;

use Contact\ZohoCRM\Api\Data\SyncLogInterface;

interface SyncLogRepositoryInterface
{
    public function save(SyncLogInterface $syncLog): SyncLogInterface;
    public function getById(int $logId): SyncLogInterface;
    public function delete(SyncLogInterface $syncLog): bool;
    public function deleteById(int $logId): bool;
}
