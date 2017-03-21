<?php

require_once '../vendor/autoload.php';

//Temporary file for testing plugin

use Findologic\Plentymarkets\Exporter;
use Findologic\Plentymarkets\Registry;
use Findologic\Plentymarkets\Config;

$logger = new Logger('customer');
$client = new Findologic\Plentymarkets\Client(Config::USERNAME, Config::PASSWORD, Config::ENDPOINT, $logger);
$wrapper = new Findologic\Plentymarkets\Wrapper\Csv();
$registry = new Registry();
$exporter = new Exporter($client, $wrapper, $logger, $registry);
$exporter->init();

$result = $exporter->getProducts();