<?php

use Silex\Application;
use Silex\Provider\AssetServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\HttpFragmentServiceProvider;

$app = new Application();
$app->register(new EucalyptusServiceProvider());
//$app->register(new ServiceControllerServiceProvider());
//$app->register(new AssetServiceProvider());
//$app->register(new TwigServiceProvider());
//$app->register(new HttpFragmentServiceProvider());
//$app['twig'] = $app->extend('twig', function (\Twig_Environment $twig, Application $app) {
    // add custom globals, filters, tags, ...
//
//    return $twig;
//});

$app['shunt'] = function (\Silex\Application $app) {

    $result = $app['ec2.client']->describeInstances();
    //$key = $input->getOption('private') ? 'private_dns_name' : 'public_dns_name';
    $key = 'public_dns_name';

    $hosts = [];
    foreach ($result->search(EucalyptusServiceProvider::SEARCH_EXPRESSION) as $host) {
        $hosts['ec2'][] = $host[$key];
        if (isset($host['service'])) {
            $hosts[$host['service']][] = $host[$key];
        }
    }


    $recipe = array(
      'hosts' => $hosts['bastion'],
      'auth' => array(
        'username' => 'debian',
        'pubkeyfile' => '../ucsb-eucalyptus/bama.pub',
        'privkeyfile' => '../ucsb-eucalyptus/baba.pem',
        'passphrase' => NULL,
      ),

      'tasks' => array(
          'ansible-galaxy' => function (\League\Shunt\Shunt $s) {
              $s->run('cd cloud; source .env; source .env2; cd playbooks; ansible-galaxy install -r requirements.yml');
          },
        'worker-streaming' => function (\League\Shunt\Shunt $s) {
            $s->run('cd cloud; source .env; source .env2; cd playbooks; ansible-playbook 500-worker-streaming.yml');
        },
          'web-update' => function (\League\Shunt\Shunt $s) {
              $s->run('cd cloud; source .env; source .env2; cd playbooks; ansible-playbook 500-web-update.yml');
          },
          'all-update' => function (\League\Shunt\Shunt $s) {
              $s->run('cd cloud; source .env; source .env2; cd playbooks; ansible-playbook 500-all.yml');
          },
        'vmstat' => function (\League\Shunt\Shunt $s) {
            $s->run('vmstat -w');
            $s->run('uptime');
            $s->run('df');
            $s->run('free -h');
        },
        'read_home_dir' => function(\League\Shunt\Shunt $s) {
            $s->run('ls');
        },
        'print_php_info' => function(\League\Shunt\Shunt $s) {
            $s->run('php -i');
        },
        'upload_foo_source' => function(\League\Shunt\Shunt $s) {
            $s->sftp()->mkdir('source');
            $s->scp()->put('foo', 'source/foo');
        }
      ),
    );

    return new \League\Shunt\Console\Application($recipe, $app['debug']);
};
$app->register(new Aws\Silex\AwsServiceProvider(), [
  'aws.config' => function (Pimple\Container $c) {
      return [
        'version' => 'latest',
        'region' => 'cloud.aristotle.ucsb.edu',
        'endpoint_provider' => $c['eucalyptus.provider.pattern_endpoint'],
        'scheme' => 'http',
      ];
  },
]);

return $app;
