<?php
namespace Contact\ZohoCRM\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Contact\ZohoCRM\Model\Config;
use Contact\ZohoCRM\Logger\Logger;

class ZohoAuth
{
    /**
     * Paths on the accounts domain, which depends on the configured data center
     */
    const TOKEN_PATH = '/oauth/v2/token';
    const AUTH_PATH = '/oauth/v2/auth';

    /**
     * @var Config
     */
    protected $config;

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
    protected $tokenData = [];

    public function __construct(
        Config $config,
        Curl $curl,
        Json $jsonSerializer,
        Logger $logger
    ) {
        $this->config = $config;
        $this->curl = $curl;
        $this->jsonSerializer = $jsonSerializer;
        $this->logger = $logger;
    }

    /**
     * Exchange Authorization Code for Tokens
     *
     * @param string $code
     * @return array
     * @throws LocalizedException
     */
    public function exchangeCode(string $code): array
    {
        try {
            $params = [
                'code' => $code,
                'client_id' => $this->config->getClientId(),
                'client_secret' => $this->config->getClientSecret(),
                'redirect_uri' => $this->config->getRedirectUri(),
                'grant_type' => 'authorization_code'
            ];

            // Make the request
            $this->curl->setOption(CURLOPT_POST, true);
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->setOption(CURLOPT_SSL_VERIFYPEER, false);
            $this->curl->setOption(CURLOPT_POSTFIELDS, http_build_query($params));

            $this->curl->post($this->config->getAccountsDomain() . self::TOKEN_PATH, http_build_query($params));

            $responseBody = $this->curl->getBody();

            if (empty($responseBody)) {
                throw new LocalizedException(__('Empty response from Zoho.'));
            }

            $response = $this->jsonSerializer->unserialize($responseBody);

            if ($this->config->isLogEnabled()) {
                $this->logger->info('Zoho Auth Code Exchange Response', ['response' => $response]);
            }

            if (isset($response['access_token'])) {
                return $response;
            }

            $error = $response['error'] ?? 'Unknown error';
            $errorDescription = $response['error_description'] ?? '';

            throw new LocalizedException(__(
                'Failed to exchange authorization code: %1 %2',
                $error,
                $errorDescription
            ));

        } catch (\Exception $e) {
            $this->logger->error('Zoho Auth Code Exchange Error', [
                'error' => $e->getMessage()
            ]);
            throw new LocalizedException(__('Zoho Auth Error: %1', $e->getMessage()));
        }
    }

    /**
     * Get Access Token
     *
     * @return string
     * @throws LocalizedException
     */
    public function getAccessToken(): string
    {
        if (!$this->isTokenValid()) {
            $this->refreshToken();
        }

        return $this->tokenData['access_token'] ?? '';
    }

    /**
     * Check if token is valid
     *
     * @return bool
     */
    protected function isTokenValid(): bool
    {
        return !empty($this->tokenData['access_token'])
            && isset($this->tokenData['expires_at'])
            && $this->tokenData['expires_at'] > time() + 300;
    }

    /**
     * Refresh Access Token
     *
     * @return void
     * @throws LocalizedException
     */
    protected function refreshToken(): void
    {
        try {
            $refreshToken = $this->config->getRefreshToken();

            if (empty($refreshToken)) {
                throw new LocalizedException(__('Zoho CRM: Refresh token is not configured.'));
            }

            $params = [
                'refresh_token' => $refreshToken,
                'client_id' => $this->config->getClientId(),
                'client_secret' => $this->config->getClientSecret(),
                'grant_type' => 'refresh_token'
            ];

            $this->curl->setOption(CURLOPT_POST, true);
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->setOption(CURLOPT_SSL_VERIFYPEER, false);
            $this->curl->setOption(CURLOPT_POSTFIELDS, http_build_query($params));

            $this->curl->post($this->config->getAccountsDomain() . self::TOKEN_PATH, http_build_query($params));

            $responseBody = $this->curl->getBody();

            if (empty($responseBody)) {
                throw new LocalizedException(__('Empty response from Zoho.'));
            }

            $response = $this->jsonSerializer->unserialize($responseBody);

            if (isset($response['access_token'])) {
                $this->tokenData = [
                    'access_token' => $response['access_token'],
                    'expires_at' => time() + ($response['expires_in'] ?? 3600)
                ];

                // Save new refresh token if provided
                if (isset($response['refresh_token'])) {
                    $this->config->setRefreshToken($response['refresh_token']);
                }
            } else {
                $error = $response['error'] ?? 'Unknown error';
                throw new LocalizedException(__('Zoho CRM: Failed to refresh token - %1', $error));
            }
        } catch (\Exception $e) {
            $this->logger->error('Zoho CRM Auth Error', ['error' => $e->getMessage()]);
            throw new LocalizedException(__('Zoho CRM Auth Error: %1', $e->getMessage()));
        }
    }

    /**
     * Generate Authorization URL
     *
     * @return string
     */
    public function getAuthorizationUrl(): string
    {
        $params = [
            'client_id' => $this->config->getClientId(),
            'redirect_uri' => $this->config->getRedirectUri(),
            'response_type' => 'code',
            'scope' => 'ZohoCRM.modules.ALL,ZohoCRM.settings.ALL,ZohoCRM.users.ALL',
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];

        return $this->config->getAccountsDomain() . self::AUTH_PATH . '?' . http_build_query($params);
    }
}
