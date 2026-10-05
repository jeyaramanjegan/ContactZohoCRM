<?php
namespace Contact\ZohoCRM\Model\Queue;

use Magento\Framework\MessageQueue\BatchConsumer;

/**
 * Batch consumer for zoho.customer.sync
 *
 * Exists only so di.xml can set batchSize for this consumer alone: queue_consumer.xml
 * requires a real class for consumerInstance, so a virtualType can't be used.
 */
class Consumer extends BatchConsumer
{
}
