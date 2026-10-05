<?php
namespace Contact\ZohoCRM\Plugin;

use Magento\Customer\Model\ResourceModel\Customer;
use Magento\Framework\Model\AbstractModel;
use Contact\ZohoCRM\Model\Config;
use Contact\ZohoCRM\Logger\Logger;

class CustomerResourceSync
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Logger
     */
    protected $logger;

    public function __construct(
        Config $config,
        Logger $logger
    ) {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * After customer save in resource model
     *
     * @param Customer $subject
     * @param Customer $result
     * @param AbstractModel $customer
     * @return Customer
     */
    public function afterSave(Customer $subject, Customer $result, AbstractModel $customer)
    {
        if (!$this->config->isEnabled()) {
            return $result;
        }

        try {
            // Additional sync logic if needed
            // This is called after the customer is saved to the database
            if ($this->config->isLogEnabled()) {
                $this->logger->debug('Customer saved in resource model', [
                    'customer_id' => $customer->getId(),
                    'email' => $customer->getEmail()
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Customer resource sync error', [
                'error' => $e->getMessage()
            ]);
        }

        return $result;
    }
}
