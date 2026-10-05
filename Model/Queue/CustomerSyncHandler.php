<?php
namespace Contact\ZohoCRM\Model\Queue;

use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Contact\ZohoCRM\Logger\Logger;
use Contact\ZohoCRM\Model\Api\ZohoClient;
use Contact\ZohoCRM\Model\Config;
use Contact\ZohoCRM\Model\CustomerDataBuilder;
use Contact\ZohoCRM\Model\ResourceModel\SyncLog as SyncLogResource;
use Contact\ZohoCRM\Model\SyncLogFactory;

/**
 * Consumer handler for zoho.customer.sync: pushes merged customers to Zoho via bulk upsert
 *
 * Never throws. A thrown exception makes Magento reject the whole batch, and AMQP
 * redelivers rejected messages immediately and indefinitely. Failures are instead
 * republished with an incremented attempt count until retry_attempts is reached.
 */
class CustomerSyncHandler
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var ZohoClient
     */
    protected $zohoClient;

    /**
     * @var CustomerDataBuilder
     */
    protected $customerDataBuilder;

    /**
     * @var CustomerCollectionFactory
     */
    protected $customerCollectionFactory;

    /**
     * @var Publisher
     */
    protected $publisher;

    /**
     * @var SyncLogFactory
     */
    protected $syncLogFactory;

    /**
     * @var SyncLogResource
     */
    protected $syncLogResource;

    /**
     * @var Json
     */
    protected $jsonSerializer;

    /**
     * @var Logger
     */
    protected $logger;

    public function __construct(
        Config $config,
        ZohoClient $zohoClient,
        CustomerDataBuilder $customerDataBuilder,
        CustomerCollectionFactory $customerCollectionFactory,
        Publisher $publisher,
        SyncLogFactory $syncLogFactory,
        SyncLogResource $syncLogResource,
        Json $jsonSerializer,
        Logger $logger
    ) {
        $this->config = $config;
        $this->zohoClient = $zohoClient;
        $this->customerDataBuilder = $customerDataBuilder;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->publisher = $publisher;
        $this->syncLogFactory = $syncLogFactory;
        $this->syncLogResource = $syncLogResource;
        $this->jsonSerializer = $jsonSerializer;
        $this->logger = $logger;
    }

    /**
     * Handle a merged batch
     *
     * @param array $items List of ['customer_id' => int, 'attempt' => int] (see Merger)
     * @return void
     */
    public function execute(array $items): void
    {
        if (!$items) {
            return;
        }

        if (!$this->config->isEnabled()) {
            // Acknowledge and drop: syncing was switched off after these were queued
            $this->logger->info('Zoho CRM disabled; dropping queued customer syncs', ['count' => count($items)]);
            return;
        }

        $stats = ['success' => 0, 'retry' => 0, 'failed' => 0];
        $chunkSize = min(ZohoClient::UPSERT_LIMIT, $this->config->getBatchSize() ?: ZohoClient::UPSERT_LIMIT);

        foreach (array_chunk($items, $chunkSize) as $chunk) {
            try {
                $this->processChunk($chunk, $stats);
            } catch (\Throwable $e) {
                // Unexpected error (e.g. DB): retry the whole chunk later rather than lose it
                foreach ($chunk as $item) {
                    $this->fail($item, $e->getMessage(), $stats);
                }
            }
        }

        $this->logger->info('Zoho CRM queue batch processed', $stats);
    }

    /**
     * Load, build and upsert one chunk (<= 100 customers)
     *
     * @param array $chunk
     * @param array $stats
     * @return void
     */
    protected function processChunk(array $chunk, array &$stats): void
    {
        $collection = $this->customerCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->addFieldToFilter('entity_id', ['in' => array_column($chunk, 'customer_id')]);

        $sent = [];
        $records = [];
        foreach ($chunk as $item) {
            $customer = $collection->getItemById($item['customer_id']);

            if (!$customer || !$customer->getEmail()) {
                // Deleted since it was queued, or no email to match on: retrying won't help.
                // Not written to the sync log: its customer_id FK would reject a deleted customer.
                $this->logger->warning('Zoho CRM queue: customer not found or has no email, skipped', [
                    'customer_id' => $item['customer_id']
                ]);
                $stats['failed']++;
                continue;
            }

            try {
                $records[] = $this->customerDataBuilder->build($customer);
                $sent[] = $item;
            } catch (\Exception $e) {
                $this->fail($item, $e->getMessage(), $stats);
            }
        }

        if (!$records) {
            return;
        }

        try {
            $results = $this->zohoClient->upsertContacts($records);
        } catch (\Exception $e) {
            // Whole request failed (auth, network, rate limit after HTTP retries)
            foreach ($sent as $item) {
                $this->fail($item, $e->getMessage(), $stats);
            }
            return;
        }

        // Zoho returns results in the same order as the submitted records
        foreach ($sent as $index => $item) {
            $result = $results[$index] ?? null;

            if (isset($result['status']) && $result['status'] === 'success') {
                $zohoId = isset($result['details']['id']) ? (string)$result['details']['id'] : null;
                // Zoho reports "insert" or "update" for upserts
                $this->logSync($item['customer_id'], $result['action'] ?? 'upsert', 'success', $result, null, $zohoId);
                $stats['success']++;
                continue;
            }

            $error = $result
                ? sprintf(
                    '%s (Code: %s) %s',
                    $result['message'] ?? 'Unknown error',
                    $result['code'] ?? 'UNKNOWN',
                    isset($result['details']) ? $this->jsonSerializer->serialize($result['details']) : ''
                )
                : 'No result returned by Zoho for this record';

            $this->fail($item, trim($error), $stats, $result ?: []);
        }
    }

    /**
     * Republish for another attempt, or give up once retry_attempts is reached
     *
     * @param array $item
     * @param string $error
     * @param array $stats
     * @param array $response
     * @return void
     */
    protected function fail(array $item, string $error, array &$stats, array $response = []): void
    {
        $customerId = (int)$item['customer_id'];
        $attempt = (int)$item['attempt'] + 1;
        $maxAttempts = max(1, $this->config->getRetryAttempts());

        if ($attempt < $maxAttempts) {
            try {
                $this->publisher->publish([$customerId], $attempt);
                $stats['retry']++;
                $status = 'retry';
            } catch (\Exception $e) {
                $error .= ' | Republish failed: ' . $e->getMessage();
                $stats['failed']++;
                $status = 'error';
            }
        } else {
            $stats['failed']++;
            $status = 'error';
        }

        $this->logSync($customerId, 'upsert', $status, $response, sprintf('[attempt %d/%d] %s', $attempt, $maxAttempts, $error));

        $this->logger->error('Zoho CRM queue sync failed', [
            'customer_id' => $customerId,
            'attempt' => $attempt,
            'max_attempts' => $maxAttempts,
            'will_retry' => $status === 'retry',
            'error' => $error
        ]);
    }

    /**
     * Write an entry to the sync log table
     *
     * @param int $customerId
     * @param string $action
     * @param string $status
     * @param array $response
     * @param string|null $errorMessage
     * @param string|null $zohoId
     * @return void
     */
    protected function logSync(
        int $customerId,
        string $action,
        string $status,
        array $response = [],
        ?string $errorMessage = null,
        ?string $zohoId = null
    ): void {
        try {
            $syncLog = $this->syncLogFactory->create();
            $syncLog->setCustomerId($customerId);
            $syncLog->setAction($action);
            $syncLog->setStatus($status);

            if (!empty($response)) {
                $syncLog->setResponse($this->jsonSerializer->serialize($response));
            }

            if ($errorMessage) {
                $syncLog->setErrorMessage($errorMessage);
            }

            if ($zohoId) {
                $syncLog->setZohoId($zohoId);
            }

            $this->syncLogResource->save($syncLog);
        } catch (\Exception $e) {
            $this->logger->error('Failed to log Zoho sync', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
