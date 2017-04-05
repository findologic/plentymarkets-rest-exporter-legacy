<?php

require_once '../vendor/autoload.php';

use Findologic\Plentymarkets\Exporter;
use Findologic\Plentymarkets\Registry;
use Findologic\Plentymarkets\Config;

$logger = new Logger('customer');
$debug = false;

if (Config::DEBUG) {
    $debug = new \Findologic\Plentymarkets\Debugger();
}

$client = new \Findologic\Plentymarkets\Client(Config::USERNAME, Config::PASSWORD, Config::URL, $logger, $debug);
$wrapper = new \Findologic\Plentymarkets\Wrapper\Csv();
$registry = new \Findologic\Plentymarkets\Registry($logger);
$exporter = new \Findologic\Plentymarkets\Exporter($client, $wrapper, $logger, $registry);
$exporter->init();

echo $exporter->getProducts(Config::NUMBER_OR_ITEMS_PER_PAGE);