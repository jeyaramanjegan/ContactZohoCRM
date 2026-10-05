<?php
namespace Contact\ZohoCRM\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Encryption\EncryptorInterface;

class Encrypted extends Value
{
    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param EncryptorInterface $encryptor
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        EncryptorInterface $encryptor,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->encryptor = $encryptor;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Encrypt value before saving
     *
     * @return $this
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        if (!empty($value)) {
            $this->setValue($this->encryptor->encrypt($value));
        }
        return parent::beforeSave();
    }

    /**
     * Decrypt value after loading
     *
     * @return $this
     */
    public function afterLoad()
    {
        $value = $this->getValue();
        if (!empty($value)) {
            try {
                $this->setValue($this->encryptor->decrypt($value));
            } catch (\Exception $e) {
                $this->setValue(null);
            }
        }
        return parent::afterLoad();
    }
}
