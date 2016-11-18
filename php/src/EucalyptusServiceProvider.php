<?php

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class EucalyptusServiceProvider.
 */
class EucalyptusServiceProvider implements ServiceProviderInterface
{
    const SEARCH_EXPRESSION = 'Reservations[].Instances[?State.Name==\'running\'][].{ service: Tags[?Key==\'service\'].Value[] | [0], public_dns_name: PublicDnsName, private_dns_name: PrivateDnsName }';

    /**
     * @param Container $pimple
     */
    public function register(Container $pimple)
    {
        /*
         * @param Container $c
         * @return mixed
         */
        $pimple['ec2.ini'] = function (Container $c) {
            return $c['eucalyptus.loader']->load('ec2.ini');
        };

        /*
         * @param Container $c
         * @return mixed
         */
        $pimple['euca.ini'] = function (Container $c) {
            return $c['eucalyptus.loader']->load('ucsb-eucalyptus/bjd.ini');
        };

        /*
         * @return
         */
        $pimple['eucalyptus.locator.config'] = function () {
            return new FileLocator([getcwd()]);
        };

        /*
         * @param Container $c
         * @return mixed
         */
        $pimple['eucalyptus.loader'] = function (Container $c) {

            /**
             * @var FileLocatorInterface
             */
            $locator = $c['eucalyptus.locator.config'];

            /*
             * @var LoaderInterface $loader
             */
            return new class($locator) extends FileLoader {

                /**
                 * @param mixed $resource
                 * @param null  $type
                 *
                 * @return array
                 */
                public function load($resource, $type = null)
                {
                    $path = $this->locator->locate($resource);

                    // first pass to catch parsing errors
                    $result = parse_ini_file($path, true);
                    if (false === $result || array() === $result) {
                        throw new InvalidArgumentException(sprintf('The "%s" file is not valid.', $resource));
                    }
                    // real raw parsing
                    $result = parse_ini_file($path, true, INI_SCANNER_RAW);

                    return $result;
                }

                /**
                 * @param mixed $resource
                 * @param null  $type
                 *
                 * @return bool
                 */
                public function supports($resource, $type = null)
                {
                    return is_string($resource) && 'ini' === pathinfo($resource, PATHINFO_EXTENSION);
                }
            };
        };

        /*
         * @return \Aws\Endpoint\PatternEndpointProvider
         */
        $pimple['eucalyptus.provider.pattern_endpoint'] = function () {
            $pattern = [
              'endpoint' => '{service}.{region}:8773',
              'port' => 8773,
            ];

            return new \Aws\Endpoint\PatternEndpointProvider([
              'cloud.aristotle.ucsb.edu/autoscaling' => $pattern,
              'cloud.aristotle.ucsb.edu/cloudformation' => $pattern,
              'cloud.aristotle.ucsb.edu/ec2' => $pattern,
              'cloud.aristotle.ucsb.edu/elasticloadbalancing' => $pattern,
              'cloud.aristotle.ucsb.edu/iam' => $pattern,
              'cloud.aristotle.ucsb.edu/monitoring' => $pattern,
              'cloud.aristotle.ucsb.edu/s3' => $pattern,
              'cloud.aristotle.ucsb.edu/sts' => $pattern,
            ]);
        };

        /*
         * @param Container $c
         * @return Application
         */
        $pimple['ansible.application'] = function (Container $c) {
            $application = new Application();
            $application->add($c['ansible.command.dynamic_inventory']);
            $application->add($c['ansible.command.load_balancers']);

            return $application;
        };

        /*
         * @param Container $c
         * @return \Symfony\Component\Console\Command\Command
         */
        $pimple['ansible.command.dynamic_inventory'] = function (Container $c) {
            $command = new Command('ec2:inventory');

            // Add private DNS option.
            $definition = $command->getDefinition();
            $definition->addOption(new InputOption('private', null, InputOption::VALUE_NONE));

            $command->setCode(function (InputInterface $input, OutputInterface $output) use ($c) {
                $result = $c['ec2.client']->describeInstances();
                $key = $input->getOption('private') ? 'private_dns_name' : 'public_dns_name';

                $hosts = [];
                foreach ($result->search(self::SEARCH_EXPRESSION) as $host) {
                    $hosts['ec2'][] = $host[$key];
                    if (isset($host['service'])) {
                        $hosts[$host['service']][] = $host[$key];
                    }
                }

                $output->writeln(json_encode($hosts));
            });

            return $command;
        };

        /*
         * @param Container $c
         * @return \Symfony\Component\Console\Command\Command
         */
        $pimple['ansible.command.load_balancers'] = function (Container $c) {
            $command = new Command('ec2:load-balancers');

            $command->setCode(function (InputInterface $input, OutputInterface $output) use ($c) {
                $result = $c['elb.client']->describeLoadBalancers();

                dump($result);

                $output->writeln(json_encode([]));
            });

            return $command;
        };

        /*
         * @param Container $c
         * @return \Aws\Ec2\Ec2Client
         */
        $pimple['ec2.client'] = function (Container $c) {
            return $c['aws']->createEc2();
        };

        /*
         * @param Container $c
         * @return \Aws\Ec2\Ec2Client
         */
        $pimple['elb.client'] = function (Container $c) {
            return $c['aws']->createElasticLoadBalancing();
        };
    }
}
