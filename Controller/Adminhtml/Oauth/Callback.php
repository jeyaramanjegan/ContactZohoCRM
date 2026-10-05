<?php
namespace Contact\ZohoCRM\Controller\Adminhtml\Oauth;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Contact\ZohoCRM\Model\Api\ZohoAuth;

class Callback extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'Contact_ZohoCRM::config';

    /**
     * @var ZohoAuth
     */
    protected $zohoAuth;

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var RedirectFactory
     */
    protected $redirectFactory;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    public function __construct(
        Context $context,
        ZohoAuth $zohoAuth,
        WriterInterface $configWriter,
        ScopeConfigInterface $scopeConfig,
        ManagerInterface $messageManager,
        RedirectFactory $redirectFactory,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->zohoAuth = $zohoAuth;
        $this->configWriter = $configWriter;
        $this->scopeConfig = $scopeConfig;
        $this->messageManager = $messageManager;
        $this->redirectFactory = $redirectFactory;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * Execute OAuth Callback
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        try {
            // Get the authorization code from request
            $code = $this->getRequest()->getParam('code');

            if (!$code) {
                throw new \Exception(__('Authorization code not found in the request.'));
            }

            // Exchange code for access token
            $tokenData = $this->zohoAuth->exchangeCode($code);

            // Process the token data
            if (isset($tokenData['access_token'])) {
                // Save refresh token
                if (isset($tokenData['refresh_token'])) {
                    $this->saveRefreshToken($tokenData['refresh_token']);
                    $this->messageManager->addSuccess(__('Zoho CRM authentication successful!'));
                } else {
                    $this->messageManager->addNotice(__('Authentication successful but no refresh token received.'));
                }

                // Return success response
                return $this->getSuccessResponse();
            } else {
                throw new \Exception(__('No access token received from Zoho.'));
            }

        } catch (\Exception $e) {
            $this->messageManager->addError(__('Zoho CRM Authentication failed: %1', $e->getMessage()));
            return $this->getErrorResponse($e->getMessage());
        }
    }

    /**
     * Save refresh token to configuration
     *
     * @param string $refreshToken
     * @return void
     */
    protected function saveRefreshToken(string $refreshToken): void
    {
        try {
            $this->configWriter->save(
                'contact_zohocrm/general/refresh_token',
                $refreshToken,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                0
            );
        } catch (\Exception $e) {
            $this->messageManager->addWarning(__('Failed to save refresh token: %1', $e->getMessage()));
        }
    }

    /**
     * Get success response
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function getSuccessResponse()
    {
        $result = $this->resultJsonFactory->create();
        return $result->setData([
            'success' => true,
            'message' => __('Authentication successful')
        ]);
    }

    /**
     * Get error response
     *
     * @param string $message
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function getErrorResponse(string $message = '')
    {
        $result = $this->resultJsonFactory->create();
        return $result->setData([
            'success' => false,
            'message' => $message ?: __('Authentication failed')
        ]);
    }
}
