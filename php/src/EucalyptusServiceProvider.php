<?php

use Aws\Endpoint\PatternEndpointProvider;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\Console\Application;

/**
 * Class EucalyptusServiceProvider.
 */
class EucalyptusServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $pimple
     */
    public function register(Container $pimple)
    {
        /*
         * @return PatternEndpointProvider
         */
        $pimple['eucalyptus.provider.pattern_endpoint'] = function () {
            $pattern = [
                'endpoint' => '{service}.{region}:8773',
                'port' => 8773,
            ];

            return new PatternEndpointProvider([
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
            $application->setDefaultCommand('ec2:inventory', true);
            $application->setDispatcher($c['dispatcher']);

            return $application;
        };

        /*
         * @param Container $c
         * @return Command
         */
        $pimple['ansible.command.dynamic_inventory'] = function (Container $c) {
            return new InventoryCommand($c['ec2.client']);
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
