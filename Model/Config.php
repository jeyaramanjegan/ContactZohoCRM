<?php
namespace Contact\ZohoCRM\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    /**
     * Configuration paths
     */
    const XML_PATH_ENABLED = 'contact_zohocrm/general/enabled';
    const XML_PATH_API_DOMAIN = 'contact_zohocrm/general/api_domain';
    const XML_PATH_API_VERSION = 'contact_zohocrm/general/api_version';
    const XML_PATH_CLIENT_ID = 'contact_zohocrm/general/client_id';
    const XML_PATH_CLIENT_SECRET = 'contact_zohocrm/general/client_secret';
    const XML_PATH_REDIRECT_URI = 'contact_zohocrm/general/redirect_uri';
    const XML_PATH_MODULE_NAME = 'contact_zohocrm/general/module_name';
    const XML_PATH_SYNC_ON_REGISTER = 'contact_zohocrm/general/sync_on_register';
    const XML_PATH_SYNC_ON_UPDATE = 'contact_zohocrm/general/sync_on_update';
    const XML_PATH_SYNC_ON_LOGIN = 'contact_zohocrm/general/sync_on_login';
    const XML_PATH_LOG_ENABLED = 'contact_zohocrm/general/log_enabled';
    const XML_PATH_LOG_LEVEL = 'contact_zohocrm/general/log_level';
    const XML_PATH_RETRY_ATTEMPTS = 'contact_zohocrm/general/retry_attempts';
    const XML_PATH_RETRY_DELAY = 'contact_zohocrm/general/retry_delay';
    const XML_PATH_REFRESH_TOKEN = 'contact_zohocrm/general/refresh_token';
    const XML_PATH_FIELD_MAPPING = 'contact_zohocrm/field_mapping';
    const XML_PATH_ADDRESS_MAPPING = 'contact_zohocrm/address_mapping';
    const XML_PATH_BATCH_SIZE = 'contact_zohocrm/advanced/batch_size';
    const XML_PATH_QUEUE_ENABLED = 'contact_zohocrm/advanced/queue_enabled';
    const XML_PATH_WEBHOOK_ENABLED = 'contact_zohocrm/advanced/webhook_enabled';

    /**
     * Map of Zoho CRM API data-center domains to their matching accounts (OAuth) domains
     */
    const ACCOUNTS_DOMAIN_MAP = [
        'https://www.zohoapis.com' => 'https://accounts.zoho.com',
        'https://www.zohoapis.eu' => 'https://accounts.zoho.eu',
        'https://www.zohoapis.in' => 'https://accounts.zoho.in',
        'https://www.zohoapis.com.au' => 'https://accounts.zoho.com.au',
        'https://www.zohoapis.jp' => 'https://accounts.zoho.jp',
    ];

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $configWriter
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
        $this->encryptor = $encryptor;
    }

    /**
     * Decrypt a value saved through the Encrypted backend model
     *
     * Values set without the admin form (config.xml defaults, config:set) are stored
     * in plain text, so only values in Magento's "version:key:ciphertext" format are decrypted.
     *
     * @param string $value
     * @return string
     */
    protected function decrypt(string $value): string
    {
        if ($value === '' || !preg_match('/^\d+:\d+:/', $value)) {
            return $value;
        }

        return (string)$this->encryptor->decrypt($value);
    }

    /**
     * Get configuration value
     *
     * @param string $path
     * @param int|null $storeId
     * @return mixed
     */
    protected function getValue(string $path, ?int $storeId = null)
    {
        return $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if module is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return (bool)$this->getValue(self::XML_PATH_ENABLED, $storeId);
    }

    /**
     * Get API Domain
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApiDomain(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_API_DOMAIN, $storeId);
    }

    /**
     * Get Accounts (OAuth) Domain matching the configured API data center
     *
     * @param int|null $storeId
     * @return string
     */
    public function getAccountsDomain(?int $storeId = null): string
    {
        $apiDomain = rtrim($this->getApiDomain($storeId), '/');

        return self::ACCOUNTS_DOMAIN_MAP[$apiDomain] ?? self::ACCOUNTS_DOMAIN_MAP['https://www.zohoapis.com'];
    }

    /**
     * Get API Version
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApiVersion(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_API_VERSION, $storeId);
    }

    /**
     * Get Client ID
     *
     * @param int|null $storeId
     * @return string
     */
    public function getClientId(?int $storeId = null): string
    {
        return $this->decrypt((string)$this->getValue(self::XML_PATH_CLIENT_ID, $storeId));
    }

    /**
     * Get Client Secret
     *
     * @param int|null $storeId
     * @return string
     */
    public function getClientSecret(?int $storeId = null): string
    {
        return $this->decrypt((string)$this->getValue(self::XML_PATH_CLIENT_SECRET, $storeId));
    }

    /**
     * Get Redirect URI
     *
     * @param int|null $storeId
     * @return string
     */
    public function getRedirectUri(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_REDIRECT_URI, $storeId);
    }

    /**
     * Get Module Name (Contacts/Leads/etc)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getModuleName(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_MODULE_NAME, $storeId);
    }

    /**
     * Check if sync on registration enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isSyncOnRegister(?int $storeId = null): bool
    {
        return (bool)$this->getValue(self::XML_PATH_SYNC_ON_REGISTER, $storeId);
    }

    /**
     * Check if sync on update enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isSyncOnUpdate(?int $storeId = null): bool
    {
        return (bool)$this->getValue(self::XML_PATH_SYNC_ON_UPDATE, $storeId);
    }

    /**
     * Check if sync on login enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isSyncOnLogin(?int $storeId = null): bool
    {
        return (bool)$this->getValue(self::XML_PATH_SYNC_ON_LOGIN, $storeId);
    }

    /**
     * Check if logging is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isLogEnabled(?int $storeId = null): bool
    {
        return (bool)$this->getValue(self::XML_PATH_LOG_ENABLED, $storeId);
    }

    /**
     * Get Log Level
     *
     * @param int|null $storeId
     * @return string
     */
    public function getLogLevel(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_LOG_LEVEL, $storeId);
    }

    /**
     * Get Retry Attempts
     *
     * @param int|null $storeId
     * @return int
     */
    public function getRetryAttempts(?int $storeId = null): int
    {
        return (int)$this->getValue(self::XML_PATH_RETRY_ATTEMPTS, $storeId);
    }

    /**
     * Get Retry Delay in seconds
     *
     * @param int|null $storeId
     * @return int
     */
    public function getRetryDelay(?int $storeId = null): int
    {
        return (int)$this->getValue(self::XML_PATH_RETRY_DELAY, $storeId);
    }

    /**
     * Get Refresh Token
     *
     * @param int|null $storeId
     * @return string
     */
    public function getRefreshToken(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_REFRESH_TOKEN, $storeId);
    }

    /**
     * Set Refresh Token
     *
     * @param string $refreshToken
     * @param int|null $storeId
     * @return void
     */
    public function setRefreshToken(string $refreshToken, ?int $storeId = null): void
    {
        $this->configWriter->save(
            self::XML_PATH_REFRESH_TOKEN,
            $refreshToken,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Field Mapping
     *
     * @param int|null $storeId
     * @return array
     */
    public function getFieldMapping(?int $storeId = null): array
    {
        $mapping = $this->getValue(self::XML_PATH_FIELD_MAPPING, $storeId);
        return is_array($mapping) ? $mapping : [];
    }

    /**
     * Get Address Mapping
     *
     * @param int|null $storeId
     * @return array
     */
    public function getAddressMapping(?int $storeId = null): array
    {
        $mapping = $this->getValue(self::XML_PATH_ADDRESS_MAPPING, $storeId);
        return is_array($mapping) ? $mapping : [];
    }

    /**
     * Get Batch Size
     *
     * @param int|null $storeId
     * @return int
     */
    public function getBatchSize(?int $storeId = null): int
    {
        return (int)$this->getValue(self::XML_PATH_BATCH_SIZE, $storeId);
    }

    /**
     * Check if queue processing is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isQueueEnabled(?int $storeId = null): bool
    {
        return (bool)$this->getValue(self::XML_PATH_QUEUE_ENABLED, $storeId);
    }

    /**
     * Check if webhooks are enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isWebhookEnabled(?int $storeId = null): bool
    {
        return (bool)$this->getValue(self::XML_PATH_WEBHOOK_ENABLED, $storeId);
    }

    /**
     * Get all configuration as array
     *
     * @param int|null $storeId
     * @return array
     */
    public function getAllConfig(?int $storeId = null): array
    {
        return [
            'enabled' => $this->isEnabled($storeId),
            'api_domain' => $this->getApiDomain($storeId),
            'api_version' => $this->getApiVersion($storeId),
            'client_id' => $this->getClientId($storeId),
            'client_secret' => $this->getClientSecret($storeId),
            'redirect_uri' => $this->getRedirectUri($storeId),
            'module_name' => $this->getModuleName($storeId),
            'sync_on_register' => $this->isSyncOnRegister($storeId),
            'sync_on_update' => $this->isSyncOnUpdate($storeId),
            'sync_on_login' => $this->isSyncOnLogin($storeId),
            'log_enabled' => $this->isLogEnabled($storeId),
            'log_level' => $this->getLogLevel($storeId),
            'retry_attempts' => $this->getRetryAttempts($storeId),
            'retry_delay' => $this->getRetryDelay($storeId),
            'refresh_token' => $this->getRefreshToken($storeId),
            'field_mapping' => $this->getFieldMapping($storeId),
            'address_mapping' => $this->getAddressMapping($storeId),
            'batch_size' => $this->getBatchSize($storeId),
            'queue_enabled' => $this->isQueueEnabled($storeId),
            'webhook_enabled' => $this->isWebhookEnabled($storeId)
        ];
    }
}
