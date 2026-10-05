<?php
namespace Contact\ZohoCRM\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ApiDomains implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'https://www.zohoapis.com', 'label' => __('US Data Center')],
            ['value' => 'https://www.zohoapis.eu', 'label' => __('EU Data Center')],
            ['value' => 'https://www.zohoapis.in', 'label' => __('IN Data Center')],
            ['value' => 'https://www.zohoapis.com.au', 'label' => __('AU Data Center')],
            ['value' => 'https://www.zohoapis.jp', 'label' => __('JP Data Center')]
        ];
    }
}
