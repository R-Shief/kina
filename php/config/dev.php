<?php

use Silex\Provider\MonologServiceProvider;
use Silex\Provider\VarDumperServiceProvider;
//use Silex\Provider\WebProfilerServiceProvider;

// include the prod configuration
require __DIR__.'/prod.php';

$app['dotenv.loader'] = function () {
    return new Dotenv\Dotenv(dirname(dirname(__DIR__)));
};
$app['dotenv.loader']->load();

// enable the debug mode
$app['debug'] = true;

$app->register(new MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/../var/logs/silex_dev.log',
));

$app->register(new VarDumperServiceProvider());

//$app->register(new WebProfilerServiceProvider(), array(
//    'profiler.cache_dir' => __DIR__.'/../var/cache/profiler',
//));

$app['pimpledump'] = function () {
    return new Sorien\Provider\PimpleDumpProvider();
};
$app->register($app['pimpledump']);
