<?php
namespace Contact\ZohoCRM\Plugin;

use Magento\Customer\Model\Customer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Contact\ZohoCRM\Model\Config;
use Contact\ZohoCRM\Model\CustomerDataBuilder;
use Contact\ZohoCRM\Model\Queue\Publisher;
use Contact\ZohoCRM\Model\Api\ZohoClient;
use Contact\ZohoCRM\Logger\Logger;
use Contact\ZohoCRM\Model\SyncLogFactory;
use Contact\ZohoCRM\Model\ResourceModel\SyncLog as SyncLogResource;

class CustomerSync
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
     * @var Logger
     */
    protected $logger;

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
     * @var Publisher
     */
    protected $publisher;

    /**
     * @var CustomerDataBuilder
     */
    protected $customerDataBuilder;

    /**
     * Whether each customer being saved was new, keyed by spl_object_id
     *
     * @var bool[]
     */
    protected $isNew = [];

    public function __construct(
        Config $config,
        ZohoClient $zohoClient,
        Logger $logger,
        SyncLogFactory $syncLogFactory,
        SyncLogResource $syncLogResource,
        Json $jsonSerializer,
        Publisher $publisher,
        CustomerDataBuilder $customerDataBuilder
    ) {
        $this->config = $config;
        $this->zohoClient = $zohoClient;
        $this->logger = $logger;
        $this->syncLogFactory = $syncLogFactory;
        $this->syncLogResource = $syncLogResource;
        $this->jsonSerializer = $jsonSerializer;
        $this->publisher = $publisher;
        $this->customerDataBuilder = $customerDataBuilder;
    }

    /**
     * Remember whether the customer is new; isObjectNew() is false once the ID is assigned
     *
     * @param Customer $subject
     * @return null
     */
    public function beforeSave(Customer $subject)
    {
        $this->isNew[spl_object_id($subject)] = !$subject->getId();
        return null;
    }

    /**
     * After customer save - queue or sync to Zoho CRM
     *
     * @param Customer $subject
     * @param Customer $result
     * @return Customer
     */
    public function afterSave(Customer $subject, Customer $result): Customer
    {
        $objectId = spl_object_id($subject);
        $isNew = $this->isNew[$objectId] ?? false;
        unset($this->isNew[$objectId]);

        if (!$this->config->isEnabled()) {
            return $result;
        }

        try {
            if ($isNew && !$this->config->isSyncOnRegister()) {
                return $result;
            }

            if (!$isNew && !$this->config->isSyncOnUpdate()) {
                return $result;
            }

            if ($this->config->isQueueEnabled()) {
                // Async: the zohoCustomerSync consumer pushes customers to Zoho in bulk
                $this->publisher->publish([(int)$result->getId()]);
                return $result;
            }

            $this->syncCustomer($result);

        } catch (\Exception $e) {
            $this->logger->error('Zoho CRM Sync Error', [
                'customer_id' => $result->getId(),
                'email' => $result->getEmail(),
                'error' => $e->getMessage()
            ]);

            $this->logSync(
                (int)$result->getId(),
                'sync',
                'error',
                [],
                $e->getMessage()
            );
        }

        return $result;
    }

    /**
     * Sync customer to Zoho CRM in real time (upsert matched on Email)
     *
     * @param Customer $customer
     * @return void
     * @throws LocalizedException
     */
    protected function syncCustomer(Customer $customer): void
    {
        $results = $this->zohoClient->upsertContacts([$this->customerDataBuilder->build($customer)]);
        $result = $results[0] ?? [];

        if (($result['status'] ?? '') !== 'success') {
            throw new LocalizedException(__(
                'Zoho CRM Error: %1 (Code: %2)',
                $result['message'] ?? 'No result returned',
                $result['code'] ?? 'UNKNOWN'
            ));
        }

        $zohoId = isset($result['details']['id']) ? (string)$result['details']['id'] : null;
        $this->logSync((int)$customer->getId(), $result['action'] ?? 'upsert', 'success', $result, null, $zohoId);

        $this->logger->info('Zoho CRM Contact Synced', [
            'customer_id' => $customer->getId(),
            'zoho_id' => $zohoId,
            'action' => $result['action'] ?? 'upsert'
        ]);
    }

    /**
     * Log sync activity
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
