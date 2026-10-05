<?php
namespace Contact\ZohoCRM\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class SyncLog extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('contact_zohocrm_sync_log', 'log_id');
    }
}
