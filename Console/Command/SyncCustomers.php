<?php
namespace Contact\ZohoCRM\Console\Command;

use Contact\ZohoCRM\Model\Config;
use Contact\ZohoCRM\Model\Queue\Publisher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento zoho:customers:sync [--all [--website=ID]] [--customer=ID ...]
 *
 * Publishes to zoho.customer.sync; the zohoCustomerSync consumer does the actual sync.
 */
class SyncCustomers extends Command
{
    const OPTION_ALL = 'all';
    const OPTION_WEBSITE = 'website';
    const OPTION_CUSTOMER = 'customer';

    /**
     * @var Publisher
     */
    protected $publisher;

    /**
     * @var Config
     */
    protected $config;

    public function __construct(
        Publisher $publisher,
        Config $config
    ) {
        $this->publisher = $publisher;
        $this->config = $config;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('zoho:customers:sync')
            ->setDescription('Publish customers to the Zoho CRM sync queue (zoho.customer.sync)')
            ->addOption(self::OPTION_ALL, null, InputOption::VALUE_NONE, 'Publish every customer (bulk/initial sync)')
            ->addOption(self::OPTION_WEBSITE, null, InputOption::VALUE_REQUIRED, 'With --all, limit to a website ID')
            ->addOption(
                self::OPTION_CUSTOMER,
                'c',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Publish specific customer ID(s)'
            );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->config->isEnabled()) {
            $output->writeln('<error>Zoho CRM integration is disabled (contact_zohocrm/general/enabled).</error>');
            return Command::FAILURE;
        }

        $customerIds = array_filter(array_map('intval', (array)$input->getOption(self::OPTION_CUSTOMER)));

        if (!$input->getOption(self::OPTION_ALL) && !$customerIds) {
            $output->writeln('<error>Specify --all or --customer=ID.</error>');
            return Command::INVALID;
        }

        $published = 0;

        if ($input->getOption(self::OPTION_ALL)) {
            $websiteId = $input->getOption(self::OPTION_WEBSITE);
            $published += $this->publisher->publishAll($websiteId !== null ? (int)$websiteId : null);
        }

        if ($customerIds) {
            $published += $this->publisher->publish($customerIds);
        }

        $output->writeln(sprintf('<info>Published %d customer(s) to zoho.customer.sync.</info>', $published));
        $output->writeln('Process now with: bin/magento queue:consumers:start zohoCustomerSync --single-thread');

        return Command::SUCCESS;
    }
}
