<?php

require_once '../vendor/autoload.php';

//Temporary file for testing plugin

use Findologic\Plentymarkets\Exporter;
use Findologic\Plentymarkets\Registry;
use Findologic\Plentymarkets\Config;

$logger = new Logger('customer');
$debug = false;

if (Config::DEBUG) {
    $debug = new \Findologic\Plentymarkets\Debugger();
}

$client = new \Findologic\Plentymarkets\Client(Config::USERNAME, Config::PASSWORD, Config::ENDPOINT, $logger);
$wrapper = new \Findologic\Plentymarkets\Wrapper\Csv();
$registry = new \Findologic\Plentymarkets\Registry();
$exporter = new \Findologic\Plentymarkets\Exporter($client, $wrapper, $logger, $registry);
$exporter->init();

$result = $exporter->getProducts();