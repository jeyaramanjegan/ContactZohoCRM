<?php
namespace Contact\ZohoCRM\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class LogLevel implements OptionSourceInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'debug', 'label' => __('Debug')],
            ['value' => 'info', 'label' => __('Info')],
            ['value' => 'notice', 'label' => __('Notice')],
            ['value' => 'warning', 'label' => __('Warning')],
            ['value' => 'error', 'label' => __('Error')],
            ['value' => 'critical', 'label' => __('Critical')],
            ['value' => 'alert', 'label' => __('Alert')],
            ['value' => 'emergency', 'label' => __('Emergency')]
        ];
    }
}
