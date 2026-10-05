<?php
namespace Contact\ZohoCRM\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Contact\ZohoCRM\Model\Config;
use Contact\ZohoCRM\Logger\Logger;

class ZohoClient
{
    /**
     * Maximum records per Zoho CRM insert/update/upsert call
     */
    const UPSERT_LIMIT = 100;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var ZohoAuth
     */
    protected $auth;

    /**
     * @var Curl
     */
    protected $curl;

    /**
     * @var Json
     */
    protected $jsonSerializer;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var array
     */
    protected $headers = [];

    public function __construct(
        Config $config,
        ZohoAuth $auth,
        Curl $curl,
        Json $jsonSerializer,
        Logger $logger
    ) {
        $this->config = $config;
        $this->auth = $auth;
        $this->curl = $curl;
        $this->jsonSerializer = $jsonSerializer;
        $this->logger = $logger;
    }

    /**
     * Create Contact in Zoho CRM
     *
     * @param array $data
     * @return array
     * @throws LocalizedException
     */
    public function createContact(array $data): array
    {
        return $this->makeRequest('POST', $this->getModuleUrl(), ['data' => [$this->mapFields($data)]]);
    }

    /**
     * Update Contact in Zoho CRM
     *
     * @param string $recordId
     * @param array $data
     * @return array
     * @throws LocalizedException
     */
    public function updateContact(string $recordId, array $data): array
    {
        $url = $this->getModuleUrl() . '/' . $recordId;
        return $this->makeRequest('PUT', $url, ['data' => [$this->mapFields($data)]]);
    }

    /**
     * Bulk upsert records in Zoho CRM, matched on Email
     *
     * Zoho returns one result per record, in the same order as the input.
     *
     * @param array $records Customer data arrays (as built by CustomerDataBuilder)
     * @return array List of per-record results from Zoho
     * @throws LocalizedException
     */
    public function upsertContacts(array $records): array
    {
        if (count($records) > self::UPSERT_LIMIT) {
            throw new LocalizedException(__('Zoho CRM upsert accepts at most %1 records per call.', self::UPSERT_LIMIT));
        }

        $payload = [
            'data' => array_map([$this, 'mapFields'], array_values($records)),
            'duplicate_check_fields' => ['Email']
        ];

        $response = $this->makeRequest('POST', $this->getModuleUrl() . '/upsert', $payload, false);

        return $response['data'] ?? [];
    }

    /**
     * Search Contact by Email
     *
     * @param string $email
     * @return array|null
     */
    public function searchContactByEmail(string $email): ?array
    {
        try {
            $criteria = 'Email:equals:' . $email;
            $url = $this->getModuleUrl() . '/search?criteria=(' . urlencode($criteria) . ')';

            $response = $this->makeRequest('GET', $url);

            if (isset($response['data']) && !empty($response['data'])) {
                return $response['data'][0];
            }

            return null;
        } catch (\Exception $e) {
            $this->logger->error('Zoho CRM Search Error', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Make API Request with retry logic
     *
     * @param string $method
     * @param string $url
     * @param array|null $payload
     * @param bool $failOnRecordError Throw if the first record has status "error" (single-record calls)
     * @return array
     * @throws LocalizedException
     */
    protected function makeRequest(
        string $method,
        string $url,
        ?array $payload = null,
        bool $failOnRecordError = true
    ): array {
        $this->prepareHeaders();
        $attempts = 0;
        $maxAttempts = max(1, $this->config->getRetryAttempts());
        $delay = $this->config->getRetryDelay();

        while ($attempts < $maxAttempts) {
            try {
                // Curl user options persist between calls and override the request method,
                // so start every request from a clean slate.
                $this->curl->setOptions([]);
                $this->curl->setHeaders($this->headers);
                $body = $payload ? $this->jsonSerializer->serialize($payload) : '';

                switch (strtoupper($method)) {
                    case 'GET':
                        $this->curl->get($url);
                        break;
                    case 'POST':
                        $this->curl->post($url, $body);
                        break;
                    case 'PUT':
                    case 'DELETE':
                        // Magento's Curl client has no put()/delete(); send via post() with a custom verb
                        $this->curl->setOption(CURLOPT_CUSTOMREQUEST, strtoupper($method));
                        $this->curl->post($url, $body);
                        break;
                    default:
                        throw new LocalizedException(__('Unsupported HTTP method: %1', $method));
                }

                $rawBody = $this->curl->getBody();
                if ($rawBody === '') {
                    // e.g. 204 No Content from search with no matches
                    return [];
                }

                $response = $this->jsonSerializer->unserialize($rawBody);

                if (isset($response['code']) && $response['code'] === 'INVALID_TOKEN') {
                    throw new LocalizedException(__('Zoho CRM Error: invalid OAuth token'));
                }

                if (isset($response['status'], $response['code']) && $response['status'] === 'error') {
                    // Request-level error (bad module, bad payload, auth, etc.)
                    throw new LocalizedException(__(
                        'Zoho CRM Error: %1 (Code: %2)',
                        $response['message'] ?? 'Unknown error',
                        $response['code']
                    ));
                }

                if ($this->config->isLogEnabled()) {
                    $this->logger->info('Zoho CRM API Request', [
                        'method' => $method,
                        'url' => $url,
                        'payload' => $payload,
                        'response' => $response
                    ]);
                }

                if ($failOnRecordError
                    && isset($response['data'][0]['status'])
                    && $response['data'][0]['status'] === 'error'
                ) {
                    $errorCode = $response['data'][0]['code'] ?? 'UNKNOWN';
                    $errorMessage = $response['data'][0]['message'] ?? 'Unknown error';

                    if ($errorCode === 'API_LIMIT_EXCEEDED') {
                        $attempts++;

                        if ($attempts >= $maxAttempts) {
                            throw new LocalizedException(__(
                                'Zoho CRM Error: %1 (Code: %2)',
                                $errorMessage,
                                $errorCode
                            ));
                        }

                        $this->logger->warning('Zoho API rate limit exceeded', [
                            'attempt' => $attempts,
                            'max_attempts' => $maxAttempts
                        ]);
                        sleep($delay * $attempts);
                        continue;
                    }

                    throw new LocalizedException(__('Zoho CRM Error: %1 (Code: %2)', $errorMessage, $errorCode));
                }

                if (isset($response['data'][0]['status']) && $response['data'][0]['status'] === 'success') {
                    return $response;
                }

                return $response;

            } catch (\Exception $e) {
                $attempts++;

                if ($attempts >= $maxAttempts) {
                    $this->logger->error('Zoho CRM Request Failed', [
                        'method' => $method,
                        'url' => $url,
                        'error' => $e->getMessage()
                    ]);
                    throw new LocalizedException(__('Zoho CRM Request Failed: %1', $e->getMessage()));
                }

                $this->logger->warning('Zoho CRM Request Retry', [
                    'attempt' => $attempts,
                    'error' => $e->getMessage()
                ]);

                sleep($delay * $attempts);
            }
        }

        throw new LocalizedException(__('Zoho CRM Request failed after maximum retries'));
    }

    /**
     * Prepare Headers for API Request
     *
     * @return void
     * @throws LocalizedException
     */
    protected function prepareHeaders(): void
    {
        $accessToken = $this->auth->getAccessToken();

        $this->headers = [
            'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
    }

    /**
     * Get Module URL
     *
     * @return string
     */
    protected function getModuleUrl(): string
    {
        $baseUrl = rtrim($this->config->getApiDomain(), '/');
        $version = $this->config->getApiVersion();
        $module = $this->config->getModuleName();

        return $baseUrl . '/crm/' . $version . '/' . $module;
    }

    /**
     * Map Magento Customer Fields to Zoho CRM Fields
     *
     * @param array $data
     * @return array
     */
    protected function mapFields(array $data): array
    {
        $fieldMap = $this->config->getFieldMapping();
        $addressMap = $this->config->getAddressMapping();
        $mappedData = [];

        foreach ($fieldMap as $mageField => $zohoField) {
            if (isset($data[$mageField]) && !empty($data[$mageField])) {
                $mappedData[$zohoField] = $data[$mageField];
            }
        }

        if (isset($data['address']) && is_array($data['address'])) {
            foreach ($addressMap as $mageField => $zohoField) {
                if (isset($data['address'][$mageField]) && !empty($data['address'][$mageField])) {
                    $mappedData[$zohoField] = $data['address'][$mageField];
                }
            }
        }

        if (isset($data['customer_id'])) {
            $mappedData['Magento_Customer_ID'] = (string)$data['customer_id'];
        }

        $mappedData['Lead_Source'] = 'Magento Store';
        $mappedData['Magento_Sync_Date'] = date('Y-m-d H:i:s');

        return $mappedData;
    }
}
