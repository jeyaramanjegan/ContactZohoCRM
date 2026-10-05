<?php
namespace Contact\ZohoCRM\Model;

use Magento\Framework\Model\AbstractModel;
use Contact\ZohoCRM\Model\ResourceModel\SyncLog as SyncLogResource;

class SyncLog extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(SyncLogResource::class);
    }

    /**
     * Get Log ID
     *
     * @return int
     */
    public function getLogId(): int
    {
        return (int)$this->getData('log_id');
    }

    /**
     * Get Customer ID
     *
     * @return int
     */
    public function getCustomerId(): int
    {
        return (int)$this->getData('customer_id');
    }

    /**
     * Set Customer ID
     *
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId(int $customerId): self
    {
        return $this->setData('customer_id', $customerId);
    }

    /**
     * Get Action
     *
     * @return string
     */
    public function getAction(): string
    {
        return (string)$this->getData('action');
    }

    /**
     * Set Action
     *
     * @param string $action
     * @return $this
     */
    public function setAction(string $action): self
    {
        return $this->setData('action', $action);
    }

    /**
     * Get Status
     *
     * @return string
     */
    public function getStatus(): string
    {
        return (string)$this->getData('status');
    }

    /**
     * Set Status
     *
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): self
    {
        return $this->setData('status', $status);
    }

    /**
     * Get Response
     *
     * @return string|null
     */
    public function getResponse(): ?string
    {
        return $this->getData('response');
    }

    /**
     * Set Response
     *
     * @param string $response
     * @return $this
     */
    public function setResponse(string $response): self
    {
        return $this->setData('response', $response);
    }

    /**
     * Get Error Message
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        return $this->getData('error_message');
    }

    /**
     * Set Error Message
     *
     * @param string|null $errorMessage
     * @return $this
     */
    public function setErrorMessage(?string $errorMessage): self
    {
        return $this->setData('error_message', $errorMessage);
    }

    /**
     * Get Zoho ID
     *
     * @return string|null
     */
    public function getZohoId(): ?string
    {
        return $this->getData('zoho_id');
    }

    /**
     * Set Zoho ID
     *
     * @param string $zohoId
     * @return $this
     */
    public function setZohoId(string $zohoId): self
    {
        return $this->setData('zoho_id', $zohoId);
    }
}
