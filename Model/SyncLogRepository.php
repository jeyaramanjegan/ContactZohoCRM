<?php
namespace Contact\ZohoCRM\Model;

use Contact\ZohoCRM\Api\Data\SyncLogInterface;
use Contact\ZohoCRM\Api\SyncLogRepositoryInterface;
use Contact\ZohoCRM\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Framework\Exception\NoSuchEntityException;

class SyncLogRepository implements SyncLogRepositoryInterface
{
    /**
     * @var SyncLogResource
     */
    protected $resource;

    /**
     * @var SyncLogFactory
     */
    protected $syncLogFactory;

    public function __construct(
        SyncLogResource $resource,
        SyncLogFactory $syncLogFactory
    ) {
        $this->resource = $resource;
        $this->syncLogFactory = $syncLogFactory;
    }

    /**
     * Save sync log
     *
     * @param SyncLogInterface $syncLog
     * @return SyncLogInterface
     */
    public function save(SyncLogInterface $syncLog): SyncLogInterface
    {
        $this->resource->save($syncLog);
        return $syncLog;
    }

    /**
     * Get sync log by ID
     *
     * @param int $logId
     * @return SyncLogInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $logId): SyncLogInterface
    {
        $syncLog = $this->syncLogFactory->create();
        $this->resource->load($syncLog, $logId);

        if (!$syncLog->getLogId()) {
            throw new NoSuchEntityException(__('Sync log with id "%1" does not exist.', $logId));
        }

        return $syncLog;
    }

    /**
     * Delete sync log
     *
     * @param SyncLogInterface $syncLog
     * @return bool
     */
    public function delete(SyncLogInterface $syncLog): bool
    {
        return $this->resource->delete($syncLog);
    }

    /**
     * Delete sync log by ID
     *
     * @param int $logId
     * @return bool
     */
    public function deleteById(int $logId): bool
    {
        $syncLog = $this->getById($logId);
        return $this->delete($syncLog);
    }
}
