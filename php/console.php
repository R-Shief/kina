<?php

require 'vendor/autoload.php';

// Load environment variables.
//(new Dotenv\Dotenv(__DIR__))->load();

$app = new Silex\Application([
  'debug' => false,
  'pimpledump' => function () {
      return new Sorien\Provider\PimpleDumpProvider();
  },
  'dotenv.loader' => function () {
      return new Dotenv\Dotenv(dirname(__DIR__));
  }
]);
$app['dotenv.loader']->load();
$app->register($app['pimpledump']);
$app->register(new Silex\Provider\VarDumperServiceProvider());
$app->register(new EucalyptusServiceProvider());
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
      'hosts' => $hosts['ec2'],
      'auth' => array(
        'username' => 'debian',
        'pubkeyfile' => '../ucsb-eucalyptus/bama.pub',
        'privkeyfile' => '../ucsb-eucalyptus/baba.pem',
        'passphrase' => NULL,
      ),

      'tasks' => array(
        'vmstat' => function ($s) {
            $s->run('vmstat -w');
            $s->run('uptime');
            $s->run('df');
            $s->run('free -h');
        },
        'read_home_dir' => function($s) {
            $s->run('ls');
        },
        'print_php_info' => function($s) {
            $s->run('php -i');
        },
        'upload_foo_source' => function($s) {
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

$app->boot();
$app['shunt']->run();


//$app['ansible.application']->run();


$app['pimpledump']->dump($app);
