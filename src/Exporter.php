<?php

namespace Findologic\Plentymarkets;

use Findologic\Plentymarkets\Data\Countries;
use Findologic\Plentymarkets\Exception\CustomerException;
use Findologic\Plentymarkets\Exception\ThrottlingException;
use Findologic\Plentymarkets\Parser\ParserFactory;
use Findologic\Plentymarkets\Parser\Attributes;
use Findologic\Plentymarkets\Wrapper\WrapperInterface;
use \Logger;

class Exporter
{
    const NUMBER_OF_ITEMS_PER_PAGE = 50;

    /**
     * @var \Findologic\Plentymarkets\Client $client
     */
    protected $client;

    /**
     * @var \Findologic\Plentymarkets\Wrapper\WrapperInterface
     */
    protected $wrapper;

    /**
     * @var \Logger
     */
    protected $log;

    /**
     * @var \Logger
     */
    protected $customerLog;

    /**
     * @var \Findologic\Plentymarkets\Registry
     */
    protected $registry;

    /**
     * List of data parsers which should be initialised before product parsing.
     * !IMPORTANT! After creating new parser class it should also be inserted here
     *
     * @var array
     */
    protected $additionalDataParsers = array('Vat', 'Categories', 'SalesPrices', 'Attributes', 'Manufacturers', 'Stores', 'PropertyGroups', 'Properties');

    /**
     * Count of products skipped during the export
     *
     * @var int
     */
    protected $skippedProductsCount = 0;

    /**
     * Array for temporary holding skipped products ids for logging
     *
     * @var array
     */
    protected $skippedProductsIds = array();

    /**
     * Standard vat value from REST
     *
     * @var bool
     */
    protected $standardVat = false;

    /**
     * Store plenty id
     *
     * @var bool|int
     */
    protected $storePlentyId = false;

    /**
     * Flag to know if store supports salesRank
     *
     * @var bool
     */
    protected $exportSalesFrequency = false;

    /**
     * @var \PlentyConfig
     */
    protected $config;

    /**
     * @param \Findologic\Plentymarkets\Client $client
     * @param \Findologic\Plentymarkets\Wrapper\WrapperInterface $wrapper
     * @param \Logger $log
     * @param \Logger $customerLog
     * @param \Findologic\Plentymarkets\Registry $registry
     */
    public function __construct(Client $client, WrapperInterface $wrapper, Logger $log, Logger $customerLog, Registry $registry)
    {
        $this->client = $client;
        $this->wrapper = $wrapper;
        $this->log = $log;
        $this->customerLog = $customerLog;
        $this->registry = $registry;
        $this->config = $client->getConfig();
    }

    /**
     * Init necessary data for mapping ids of some item fields with actual names
     *
     * @return $this
     */
    public function init()
    {
        $this->getCustomerLog()->info('Starting to initialise necessary data (categories, attributes, etc.)');
        $this->getClient()->login();
        $this->initAdditionalData();
        $this->initCategoriesFullUrls();
        $this->initAttributeValues();
        $this->getCustomerLog()->info('Starting to initialise necessary data (categories, attributes, etc.)');

        return $this;
    }

    /**
     * @return WrapperInterface
     */
    public function getWrapper()
    {
        return $this->wrapper;
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @return Logger
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * @return Logger
     */
    public function getCustomerLog()
    {
        return $this->customerLog;
    }

    /**
     * @return Registry
     */
    public function getRegistry()
    {
        return $this->registry;
    }

    /**
     * @return \PlentyConfig
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return int
     */
    public function getSkippedProductsCount()
    {
        return $this->skippedProductsCount;
    }

    /**
     * @param int|bool $storePlentyId
     * @return $this
     */
    public function setStorePlentyId($storePlentyId)
    {
        $this->storePlentyId = $storePlentyId;

        return $this;
    }

    /**
     * @return bool|int
     */
    public function getStorePlentyId()
    {
        return $this->storePlentyId;
    }

    /**
     * Function for getting the units id with actual values
     *
     * @return array
     */
    public function getUnits()
    {
        $results = $this->getClient()->getUnits();

        $units = array();

        foreach ($results['entries'] as $result) {
            $units[$result['id']] = $result['unitOfMeasurement'];
        }

        return $units;
    }

    /**
     * Get all products
     *
     * @param int|null $itemsPerPage
     * @param int $page
     * @return mixed
     * @throws CustomerException
     * @throws ThrottlingException
     * @throws \Exception
     */
    public function getProducts($itemsPerPage = null, $page = 1)
    {
        if ($itemsPerPage === null) {
            $itemsPerPage = self::NUMBER_OF_ITEMS_PER_PAGE;
        }

        $continue = true;

        $this->getCustomerLog()->info('Starting product processing.');

        try {
            // Cycle the call for products to API until all we have all products
            while ($continue) {
                $this->getClient()->setItemsPerPage($itemsPerPage)->setPage($page);
                $results = $this->getClient()->getProducts();

                // Check if there is any results. Products is contained is 'entries' value of response array
                if (!$results || !isset($results['entries'])) {
                    throw new CustomerException('Could not find any results!');
                }

                $start = (($page - 1) * $itemsPerPage);
                $this->getCustomerLog()->info(
                    'Processing items from ' . $start .
                    ' to ' . ($start + count($results['entries'])) .
                    ' out of ' . $results['totalsCount']
                );

                foreach ($results['entries'] as $product) {
                    $this->processProductData($product);
                }

                if (!empty($this->skippedProductsIds)) {
                    $this->getLog()->debug('Products with ids ' . implode(',', $this->skippedProductsIds) . ' were skipped as they have no correct data (all variations could be inactive or etc.)');
                    $this->skippedProductsIds = array();
                }

                if (!$results || !isset($results['isLastPage']) || $results['isLastPage'] == true) {
                    $continue = false;
                }

                $page++;
            }
        } catch (\Exception $e) {
            if ($e instanceof ThrottlingException) {
                $this->log->fatal('Stopping products processing because of throttling exception.');
            } else {
                throw $e;
            }
        }

        $this->getCustomerLog()->info('Products processing finished. ' . $this->skippedProductsCount . ' products where skipped.');
        $this->getWrapper()->allItemsHasBeenProcessed();
        $this->getCustomerLog()->info('Data processing finished.');

        return $this->getWrapper()->getResults();
    }

    /**
     * Create new product item with initial request data
     *
     * @param array $productData
     * @return Product
     */
    public function createProductItem($productData)
    {
        $product = new Product($this->getRegistry());
        $product->setStorePlentyId($this->getStorePlentyId())
            ->setProtocol($this->getClient()->getProtocol())
            ->setStoreUrl($this->getConfig()->getDomain())
            ->setLanguageCode($this->getConfig()->getLanguage())
            ->setAvailabilityIds($this->getConfig()->getAvailabilityId())
            ->setPriceId($this->getConfig()->getPriceId())
            ->setRrpPriceId($this->getConfig()->getRrpId())
            ->setExportSalesFrequency($this->exportSalesFrequency)
            ->processInitialData($productData);

        return $product;
    }

    /**
     * Process product data
     *
     * @param array $productData
     * @return $this
     */
    public function processProductData($productData)
    {
        $product = $this->createProductItem($productData);

        // Ignore product if there is no id
        if (!$product->getItemId() || $product->getItemId() < 0) {
            $this->skippedProductsCount++;
            $this->skippedProductsIds[] = $product->getItemId();
            $this->getLog()->trace('Product was skipped as it has no id.');
            return $this;
        }

        $continue = true;
        $page = 1;

        while ($continue) {
            $this->getClient()->setItemsPerPage(self::NUMBER_OF_ITEMS_PER_PAGE)->setPage($page);
            $variations = $this->getClient()->getProductVariations($product->getItemId(), $this->getStorePlentyId());

            if (isset($variations['entries'])) {
                foreach ($variations['entries'] as $variation) {
                    $continueProcess = $product->processVariation($variation);

                     if (!$continueProcess) {
                        continue;
                    }

                    if (isset($variation['itemImages'])) {
                        $product->processImages($variation['itemImages']);
                    }

                    if (isset($variation['variationProperties'])) {
                        $product->processVariationsProperties($variation['variationProperties']);
                    }
                }
            }

            if (!$variations || !isset($variations['entries']) || $variations['isLastPage']) {
                $continue = false;
            }

            $page++;
        }

        if ($product->hasValidData()) {
            $this->getWrapper()->wrapItem($product->getResults());
        } else {
            $this->skippedProductsCount++;
            $this->skippedProductsIds[] = $product->getItemId();
        }

        unset($product);

        return $this;
    }

    /**
     * Get standard vat country, if there is no configured country call API
     *
     * @return bool|mixed|string
     */
    public function getStandardVatCountry()
    {
        if ($this->getConfig()->getMultishopId() === null || $this->getConfig()->getMultishopId() === false) {
            return $this->getConfig()->getCountry();
        }

        if (!$this->standardVat) {
            $stores = $this->getClient()->getWebstores();
            foreach ($stores as $store) {
                if ($store['id'] == $this->getConfig()->getMultishopId()) {
                    $this->setStorePlentyId($store['storeIdentifier']);
                    if (isset($store['configuration']['itemSortByMonthlySales'])) {
                        $this->exportSalesFrequency = $store['configuration']['itemSortByMonthlySales'];
                    }
                    $data = $this->getClient()->getStandardVat($this->getStorePlentyId());
                    $this->standardVat = Countries::getCountryIsoCode($data['countryId']);
                    break;
                }
            }
        }

        return $this->standardVat;
    }

    /**
     * Call categories tree parser for handling data
     */
    protected function initCategoriesFullUrls()
    {
        $continue = true;
        $page = 1;
        while ($continue) {
            $this->getClient()->setItemsPerPage(self::NUMBER_OF_ITEMS_PER_PAGE)->setPage($page);
            $results = $this->getClient()->getCategoriesBranches();
            $this->getRegistry()->get('categories')->parseCategoryFullNames($results);
            $page++;
            if (!$results || !isset($results['isLastPage']) ||$results['isLastPage']) {
                $continue = false;
            }
        }

        return $this;
    }

    /**
     * Handle the initiation of all data parsers and call method to parse the result from API
     *
     * @return $this
     */
    protected function initAdditionalData()
    {
        foreach ($this->additionalDataParsers as $type) {
            $methodName = 'get' . ucwords($type);
            if (!method_exists($this->getClient(), $methodName)) {
                $this->getLog()->warn(
                    'Plugin tried to call method from API client which does not exist when initialising parsers. ' .
                    'Parser type: ' . $type .
                    ' Method called: ' . $methodName,
                    true
                );
                continue;
            }

            if (!$this->getRegistry()->get($type)) {
                $parser = ParserFactory::create($type, $this->getRegistry());
                $parser->setLanguageCode($this->getConfig()->getLanguage())
                    ->setTaxRateCountryCode($this->getStandardVatCountry())
                    ->setStorePlentyId($this->getStorePlentyId());
                $this->getRegistry()->set($type, $parser);
                $continue = true;
                $page = 1;
                while ($continue) {
                    $this->getClient()->setItemsPerPage(self::NUMBER_OF_ITEMS_PER_PAGE)->setPage($page);
                    $results = $this->getClient()->$methodName($this->getStorePlentyId());
                    $parser->parse($results);
                    $page++;
                    if (!$results || !isset($results['isLastPage']) ||$results['isLastPage']) {
                        $continue = false;
                    }
                }

                $this->getCustomerLog()->info('- ' . $type . ' data was parsed.');
            }
        }

        return $this;
    }

    /**
     * Call all necessary methods to fully get attributes values
     *
     * @return $this
     * @throws Exception\CustomerException
     */
    protected function initAttributeValues()
    {
        $attributes = $this->getRegistry()->get('attributes');

        if (!$attributes || !$attributes instanceof Attributes) {
            throw new CustomerException('Could not get the attributes from API!');
        }

        foreach ($attributes->getResults() as $id => $attribute) {
            $continue = true;
            $page = 1;
            while ($continue) {
                $this->getClient()->setItemsPerPage(self::NUMBER_OF_ITEMS_PER_PAGE)->setPage($page);
                $results = $this->getClient()->getAttributeValues($id);
                $attributes->parseValues($results);
                $page++;
                if (!$results || !isset($results['isLastPage']) ||$results['isLastPage']) {
                    $continue = false;
                }
            }
        }

        return $this;
    }
}
