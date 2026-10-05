<?php
namespace Contact\ZohoCRM\Logger;

use Monolog\Logger as MonologLogger;
use Magento\Framework\Logger\Handler\Base;

class Handler extends Base
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/contact_zohocrm.log';

    /**
     * @var int
     */
    protected $loggerType = MonologLogger::INFO;
}
