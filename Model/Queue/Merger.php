<?php
namespace Contact\ZohoCRM\Model\Queue;

use Magento\Framework\MessageQueue\MergedMessageFactory;
use Magento\Framework\MessageQueue\MergerInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Collapses a batch of zoho.customer.sync messages into one list, one entry per customer
 */
class Merger implements MergerInterface
{
    /**
     * @var MergedMessageFactory
     */
    protected $mergedMessageFactory;

    /**
     * @var Json
     */
    protected $jsonSerializer;

    public function __construct(
        MergedMessageFactory $mergedMessageFactory,
        Json $jsonSerializer
    ) {
        $this->mergedMessageFactory = $mergedMessageFactory;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * @param array $messages topic => [message_id => JSON string]
     * @return array topic => [MergedMessageInterface]
     */
    public function merge(array $messages)
    {
        $result = [];

        foreach ($messages as $topicName => $topicMessages) {
            $items = [];

            foreach ($topicMessages as $body) {
                try {
                    $data = $this->jsonSerializer->unserialize((string)$body);
                } catch (\InvalidArgumentException $e) {
                    continue;
                }

                $customerId = (int)($data['customer_id'] ?? 0);
                if ($customerId <= 0) {
                    continue;
                }

                $attempt = (int)($data['attempt'] ?? 0);
                // A fresh save (attempt 0) supersedes a pending retry for the same customer
                if (!isset($items[$customerId]) || $attempt < $items[$customerId]['attempt']) {
                    $items[$customerId] = ['customer_id' => $customerId, 'attempt' => $attempt];
                }
            }

            // Every original message is acknowledged once the merged one is handled,
            // including malformed ones skipped above
            $result[$topicName] = [
                $this->mergedMessageFactory->create([
                    'mergedMessage' => array_values($items),
                    'originalMessagesIds' => array_keys($topicMessages)
                ])
            ];
        }

        return $result;
    }
}
