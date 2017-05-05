<?php

ini_set('max_execution_time', 300);

require_once '../vendor/autoload.php';

use Findologic\Plentymarkets\Config;


$debug = false;
$logger = Logger::getLogger("main");
$log = new Findologic\Plentymarkets\Log($logger);

if (Config::DEBUG) {
    $log->info('Initialising the plugin with DEBUG mode ON.', false);
    $debug = new \Findologic\Plentymarkets\Debugger($log);
}

$registry = new \Findologic\Plentymarkets\Registry($log);
$client = new \Findologic\Plentymarkets\Client(Config::USERNAME, Config::PASSWORD, Config::URL, $log, $debug);
$wrapper = new \Findologic\Plentymarkets\Wrapper\Csv();
$exporter = new \Findologic\Plentymarkets\Exporter($client, $wrapper, $log, $registry);
$exporter->init();

echo $exporter->getProducts(Config::NUMBER_OF_ITEMS_PER_PAGE);