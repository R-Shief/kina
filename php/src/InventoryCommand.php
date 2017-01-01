<?php

use Aws\AwsClientInterface;
use Aws\Ec2\Ec2Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * Class InventoryCommand
 */
class InventoryCommand extends Command
{
    const SEARCH_EXPRESSION = 'Reservations[].Instances[?State.Name==\'running\'][]';

    /**
     * @var AwsClientInterface|Ec2Client
     */
    private $client;

    /**
     * @var CamelCaseToSnakeCaseNameConverter
     */
    private $converter;

    /**
     * InventoryCommand constructor.
     * @param AwsClientInterface $client
     */
    public function __construct(AwsClientInterface $client)
    {
        $this->client = $client;
        $this->converter = new CamelCaseToSnakeCaseNameConverter();
        parent::__construct('ec2:inventory');
    }

    /**
     *
     */
    protected function configure()
    {
        $this->setName('ec2:inventory');
        $this->addOption('list');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var \Aws\Result $result */
        $result = $this->client->describeInstances();
        $instances = (array) $result->search(self::SEARCH_EXPRESSION);

        $inventory = ['_meta' => ['hostvars' => []]];

        foreach ($instances as $instance) {
            $instanceVars = $instance;
            $hostname = $this->isBastion($instance) ? $instance['PublicDnsName'] : $instance['PrivateDnsName'];
            $inventory['_meta']['hostvars'][$hostname] = $instanceVars;
            $inventory['ec2'][] = $hostname;

            foreach ($instance['Tags'] as $tag) {
                $combinedKey = $tag['Key'] .'_'. $tag['Value'];
                $inventory[$tag['Key']]['children'][] = $combinedKey;
                $inventory[$combinedKey][] = $hostname;
                if ($tag['Key'] === 'service') {
                    $inventory['_meta']['hostvars'][$hostname]['CustomHostname'] = $tag['Value'];
                }
            }
        }

        $output->writeln(json_encode($inventory));
    }

    /**
     * @param $instance
     * @return bool
     */
    private function isBastion($instance)
    {
        return !empty(array_filter($instance['Tags'], function ($tag) {
            return $tag['Key'] === 'bastion' && filter_var($tag['Value'], FILTER_VALIDATE_BOOLEAN) === true;
        }));
    }
}
