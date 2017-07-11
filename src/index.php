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

$config = new PlentyConfig();

$config->setUsername('username')
    ->setPassword('password')
    ->setDomain('www.store.com')
    ->setPriceId(1)
    ->setRrpId(2) // price id for 'instead' field
    ->setCountry('GB') // Country code for tax rates
    ->setLanguage('EN'); // Language code for texts

$registry = new \Findologic\Plentymarkets\Registry($log);
$client = new \Findologic\Plentymarkets\Client($config, $log, $debug);
$wrapper = new \Findologic\Plentymarkets\Wrapper\Csv();
$exporter = new \Findologic\Plentymarkets\Exporter($client, $wrapper, $log, $registry);
$exporter->init();

echo $exporter->getProducts(Config::NUMBER_OF_ITEMS_PER_PAGE, 5);