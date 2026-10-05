<?php
namespace Contact\ZohoCRM\Controller\Adminhtml\Oauth;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\RedirectFactory;
use Contact\ZohoCRM\Model\Api\ZohoAuth;
use Contact\ZohoCRM\Model\Config;

class Authorize extends Action
{
    /**
     * @var ZohoAuth
     */
    private $zohoAuth;

    /**
     * @var RedirectFactory
     */
    private $redirectFactory;

    public function __construct(
        Context $context,
        ZohoAuth $zohoAuth,
        RedirectFactory $redirectFactory
    ) {
        parent::__construct($context);
        $this->zohoAuth = $zohoAuth;
        $this->redirectFactory = $redirectFactory;
    }

    public function execute()
    {
        $authUrl = $this->zohoAuth->getAuthorizationUrl();
        return $this->redirectFactory->create()->setUrl($authUrl);
    }
}
