<?php
namespace Contact\ZohoCRM\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Contact\ZohoCRM\Model\Config;
use Contact\ZohoCRM\Model\Api\ZohoClient;
use Contact\ZohoCRM\Logger\Logger;
use Contact\ZohoCRM\Model\SyncLogFactory;
use Contact\ZohoCRM\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Framework\Serialize\Serializer\Json;

class CustomerRegister implements ObserverInterface
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

    public function __construct(
        Config $config,
        ZohoClient $zohoClient,
        Logger $logger,
        SyncLogFactory $syncLogFactory,
        SyncLogResource $syncLogResource,
        Json $jsonSerializer
    ) {
        $this->config = $config;
        $this->zohoClient = $zohoClient;
        $this->logger = $logger;
        $this->syncLogFactory = $syncLogFactory;
        $this->syncLogResource = $syncLogResource;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            if (!$this->config->isEnabled()) {
                return;
            }

            /** @var \Magento\Customer\Model\Customer $customer */
            $customer = $observer->getEvent()->getCustomer();

            if (!$customer || !$customer->getId()) {
                return;
            }

            if (!$this->config->isSyncOnRegister()) {
                return;
            }

            // Prepare customer data
            $customerData = $this->prepareCustomerData($customer);

            // Check if contact exists in Zoho
            $existingContact = $this->zohoClient->searchContactByEmail($customer->getEmail());

            if ($existingContact) {
                $zohoId = $existingContact['id'] ?? null;
                if ($zohoId) {
                    $response = $this->zohoClient->updateContact($zohoId, $customerData);
                    $this->logSync($customer->getId(), 'update', 'success', $response);

                    $this->logger->info('Zoho CRM Contact Updated via Observer', [
                        'customer_id' => $customer->getId(),
                        'zoho_id' => $zohoId
                    ]);
                }
            } else {
                $response = $this->zohoClient->createContact($customerData);
                $this->logSync($customer->getId(), 'create', 'success', $response);

                $this->logger->info('Zoho CRM Contact Created via Observer', [
                    'customer_id' => $customer->getId()
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->error('Zoho CRM Observer Error', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Prepare customer data for Zoho CRM
     *
     * @param \Magento\Customer\Model\Customer $customer
     * @return array
     */
    protected function prepareCustomerData($customer): array
    {
        $data = [
            'customer_id' => $customer->getId(),
            'website_id' => $customer->getWebsiteId(),
            'store_id' => $customer->getStoreId(),
            'firstname' => $customer->getFirstname(),
            'lastname' => $customer->getLastname(),
            'email' => $customer->getEmail(),
            'telephone' => $customer->getTelephone() ?? '',
            'dob' => $customer->getDob() ?? '',
            'gender' => $this->getGenderLabel($customer->getGender()),
            'taxvat' => $customer->getTaxvat() ?? '',
            'company' => $customer->getCompany() ?? '',
            'created_at' => $customer->getCreatedAt(),
            'updated_at' => $customer->getUpdatedAt()
        ];

        // Add address data
        $address = $customer->getDefaultBillingAddress();
        if ($address) {
            $data['address'] = [
                'street' => $address->getStreetFull() ?? '',
                'city' => $address->getCity() ?? '',
                'region' => $address->getRegion() ?? '',
                'country_id' => $address->getCountryId() ?? '',
                'postcode' => $address->getPostcode() ?? ''
            ];
        }

        return $data;
    }

    /**
     * Get gender label
     *
     * @param int|null $gender
     * @return string
     */
    protected function getGenderLabel(?int $gender): string
    {
        $options = [
            1 => 'Male',
            2 => 'Female',
            3 => 'Not Specified'
        ];

        return $options[$gender] ?? 'Not Specified';
    }

    /**
     * Log sync activity
     *
     * @param int $customerId
     * @param string $action
     * @param string $status
     * @param array $response
     * @param string|null $errorMessage
     * @return void
     */
    protected function logSync(
        int $customerId,
        string $action,
        string $status,
        array $response = [],
        ?string $errorMessage = null
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

            $syncLog->setCreatedAt(date('Y-m-d H:i:s'));

            $this->syncLogResource->save($syncLog);
        } catch (\Exception $e) {
            $this->logger->error('Failed to log Zoho sync', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
