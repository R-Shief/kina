<?php

$app = new Silex\Application(['debug' => true]);
$app->register(new EucalyptusServiceProvider());
$app->register(new Silex\Provider\VarDumperServiceProvider());
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

$app['dotenv.loader'] = function () {
    return new Dotenv\Dotenv(dirname(dirname(__DIR__)));
};
$app['dotenv.loader']->load();

return $app;
