<?php
namespace Contact\ZohoCRM\Model\Queue;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Publishes customers to the zoho.customer.sync topic
 */
class Publisher
{
    const TOPIC = 'zoho.customer.sync';

    /**
     * @var PublisherInterface
     */
    protected $publisher;

    /**
     * @var Json
     */
    protected $jsonSerializer;

    /**
     * @var ResourceConnection
     */
    protected $resource;

    public function __construct(
        PublisherInterface $publisher,
        Json $jsonSerializer,
        ResourceConnection $resource
    ) {
        $this->publisher = $publisher;
        $this->jsonSerializer = $jsonSerializer;
        $this->resource = $resource;
    }

    /**
     * Publish one message per customer
     *
     * @param int[] $customerIds
     * @param int $attempt Number of failed attempts so far (0 for a fresh sync)
     * @return int Number of messages published
     */
    public function publish(array $customerIds, int $attempt = 0): int
    {
        $count = 0;
        foreach (array_unique(array_map('intval', $customerIds)) as $customerId) {
            if ($customerId <= 0) {
                continue;
            }

            $this->publisher->publish(self::TOPIC, $this->jsonSerializer->serialize([
                'customer_id' => $customerId,
                'attempt' => $attempt
            ]));
            $count++;
        }

        return $count;
    }

    /**
     * Publish every customer (bulk/initial sync), reading IDs in chunks
     *
     * @param int|null $websiteId
     * @return int Number of messages published
     */
    public function publishAll(?int $websiteId = null): int
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('customer_entity');
        $lastId = 0;
        $total = 0;

        do {
            $select = $connection->select()
                ->from($table, ['entity_id'])
                ->where('entity_id > ?', $lastId)
                ->order('entity_id ASC')
                ->limit(1000);

            if ($websiteId !== null) {
                $select->where('website_id = ?', $websiteId);
            }

            $ids = $connection->fetchCol($select);
            if ($ids) {
                $total += $this->publish($ids);
                $lastId = (int)end($ids);
            }
        } while (count($ids) === 1000);

        return $total;
    }
}
