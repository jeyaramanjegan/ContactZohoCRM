<?php
namespace Contact\ZohoCRM\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ZohoModules implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'Contacts', 'label' => __('Contacts')],
            ['value' => 'Leads', 'label' => __('Leads')],
            ['value' => 'Accounts', 'label' => __('Accounts')],
            ['value' => 'Deals', 'label' => __('Deals')]
        ];
    }
}
