<?php

namespace Findologic\Plentymarkets;

use Findologic\Plentymarkets\Data\Countries;
use Findologic\Plentymarkets\Exception\CustomerException;
use Findologic\Plentymarkets\Exception\ThrottlingException;
use Findologic\Plentymarkets\Parser\ParserFactory;
use Findologic\Plentymarkets\Parser\Attributes;
use Findologic\Plentymarkets\Wrapper\WrapperInterface;
use Monolog\Logger;

class Exporter
{
    const NUMBER_OF_ITEMS_PER_PAGE = 100;

    /**
     * @var \Findologic\Plentymarkets\Client $client
     */
    protected $client;

    /**
     * @var \Findologic\Plentymarkets\Wrapper\WrapperInterface
     */
    protected $wrapper;

    /**
     * @var Logger
     */
    protected $log;

    /**
     * @var Logger
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
    protected $additionalDataParsers = array('Vat', 'Categories', 'SalesPrices', 'Attributes', 'Manufacturers', 'Stores', 'PropertyGroups', 'Properties', 'ItemProperties', 'Units', 'PropertySelections');

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
     * RRP price id
     *
     * @var bool|int
     */
    protected $rrpId = false;

    /**
     * Price id
     *
     * @var bool|int
     */
    protected $priceId = false;

    /**
     * Language url prefix
     *
     * @var bool|string
     */
    protected $languageUrlPrefix = false;

    /**
     * Flag to know if store supports salesRank
     *
     * @var bool
     */
    protected $exportSalesFrequency = false;

    /**
     * @var array
     */
    protected $storesConfiguration = array();

    /**
     * @var \PlentyConfig
     */
    protected $config;

    /**
     * @param \Findologic\Plentymarkets\Client $client
     * @param \Findologic\Plentymarkets\Wrapper\WrapperInterface $wrapper
     * @param Logger $log
     * @param Logger $customerLog
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
        $this->getCustomerLog()->info('Starting to initialise necessary data (categories, attributes, etc.).');
        $this->getClient()->login();
        $this->setStoresConfiguration($this->getClient()->getWebstores());
        $this->initAdditionalData();

        // Init price configuration after sales price additional data was parsed.
        $this->setRrpId($this->getConfig()->getRrpId() ? $this->getConfig()->getRrpId() : $this->getRegistry()->get('SalesPrices')->getDefaultRrp());
        $this->setPriceId($this->getConfig()->getPriceId() ? $this->getConfig()->getPriceId() : $this->getRegistry()->get('SalesPrices')->getDefaultPrice());

        $this->initCategoriesFullUrls();
        $this->initAttributeValues();
        $this->initLanguageUrlPrefix();
        $this->getCustomerLog()->info('Finished to initialise necessary data.');

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
     * @return bool|int
     */
    public function getRrpId()
    {
        return $this->rrpId;
    }

    /**
     * @param int $rrpId
     * @return $this
     */
    public function setRrpId($rrpId)
    {
        $this->rrpId = $rrpId;

        return $this;
    }

    /**
     * @return bool|int
     */
    public function getPriceId()
    {
        return $this->priceId;
    }

    /**
     * @param int $priceId
     * @return $this
     */
    public function setPriceId($priceId)
    {
        $this->priceId = $priceId;

        return $this;
    }

    /**
     * @param string $urlPrefix
     * @return $this
     */
    public function setLanguageUrlPrefix($urlPrefix)
    {
        $this->languageUrlPrefix = $urlPrefix;

        return $this;
    }

    /**
     * @return bool|string
     */
    public function getLanguageUrlPrefix()
    {
        return $this->languageUrlPrefix;
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
                $results = $this->getClient()->getProducts($this->getConfig()->getLanguage());

                // Check if there is any results. Products is contained in 'entries' value of response array
                if (!$results || !isset($results['entries'])) {
                    throw new CustomerException('Could not find any results!');
                }

                $count = 0;
                $products = array();

                while (($product = array_shift($results['entries']))) {
                    if ($product['id'] > 0) {
                        $products[$product['id']] = $product;
                    } else {
                        $this->trackSkippedProducts($product['id']);
                        $this->getLog()->debug('Product was skipped as it has no id.');
                    }

                    unset($product);

                    $count++;
                }

                $start = (($page - 1) * $itemsPerPage);
                $this->getCustomerLog()->info(sprintf(
                    'Processing items from %d to %d out of %d',
                    $start, ($start + $count), $results['totalsCount']
                ));

                if (!empty($products)) {
                    $this->processProductData($products);
                }

                if (!empty($this->skippedProductsIds)) {
                    $this->getLog()->debug(sprintf(
                        'Products with ids %s were skipped as they have no correct data (all variations could be inactive or etc.)',
                        implode(',', $this->skippedProductsIds)
                    ));
                    $this->skippedProductsIds = array();
                }

                if (!isset($results['isLastPage']) || $results['isLastPage'] === true) {
                    $continue = false;
                }

                unset($results);
                unset($products);

                $page++;
            }
        } catch (ThrottlingException $e) {
            $this->log->alert('Stopping products processing because of throttling exception.');

            // Re-throw the exception to ensure that the export fails.
            throw $e;
        }

        $this->getCustomerLog()->info(sprintf(
            'Products processing finished. %d products where skipped.',
            $this->skippedProductsCount
        ));
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
            ->setPriceId($this->getPriceId())
            ->setRrpPriceId($this->getRrpId())
            ->setProductUrlPrefix($this->getLanguageUrlPrefix())
            ->setExportSalesFrequency($this->exportSalesFrequency)
            ->setProductNameFieldId($this->getStoreConfigValue($this->getStorePlentyId(), 'displayItemName'))
            ->processInitialData($productData);

        return $product;
    }

    /**
     * Process product data
     *
     * @param array $productsData
     * @return $this
     */
    public function processProductData($productsData)
    {
        $page = 1;
        $continue = true;
        $variations = array();
        $itemIds = array_keys($productsData);

        $this->getLog()->info('Getting all variants for products...');
        while ($continue) {
            $this->getClient()->setItemsPerPage(self::NUMBER_OF_ITEMS_PER_PAGE)->setPage($page);
            $result = $this->getClient()->getProductVariations(
                $itemIds,
                $this->getRequiredVariationValues(),
                $this->getStorePlentyId()
            );
            if ($result['lastPageNumber'] > 0) {
                $this->getLog()->info(
                    sprintf('Page %d of %d variants pages have been fetched...', $page, $result['lastPageNumber'])
                );
            }

            if (isset($result['entries'])) {
                while (($variation = array_shift($result['entries']))) {
                    if (isset($variations[$variation['base']['itemId']])) {
                        $variations[$variation['base']['itemId']][] = $variation;
                    } else {
                        $variations[$variation['base']['itemId']] = array($variation);
                    }

                    unset($variation);
                }
            }

            if (!$result || !isset($result['entries']) || $result['isLastPage']) {
                $continue = false;
            }

            $page++;

            unset($result);
        }

        $validItemIds = array_keys($variations);

        $this->trackSkippedProducts(array_diff($itemIds, $validItemIds));

        foreach ($validItemIds as $itemId) {
            if (!isset($productsData[$itemId])) {
                continue;
            }

            $product = $this->createProductItem($productsData[$itemId]);

            unset($productsData[$itemId]);

            while (($variation = array_shift($variations[$product->getItemId()]))) {
                $continueProcess = $product->processVariation($variation);

                if (!$continueProcess) {
                    continue;
                }

                $variationImages = [];
                if (isset($variation['images']) && $variation['images'] !== []) {
                    $variationImages = array_map(function ($variationImage) {
                        return $variationImage['image'];
                    }, $variation['images']);
                }

                $images = $variationImages;
                if (isset($variation['base']['images']) && $variation['base']['images'] !== []) {
                    $images = array_merge($variation['base']['images'], $variationImages);
                }

                if (!empty($images)) {
                    usort($images, function($a, $b) {
                        return $a['position'] <=> $b['position'];
                    });

                    $product->processImages($images);
                }

                if (isset($variation['base']['characteristics'])) {
                    $product->processCharacteristics($variation['base']['characteristics']);
                }

                if (isset($variation['properties'])) {
                    $product->processProperties($variation['properties']);
                }

                unset($variation);
            }

            if ($product->hasValidData()) {
                $this->getWrapper()->wrapItem($product->getResults());
            } else {
                $this->trackSkippedProducts($product->getItemId());
            }

            unset($variations[$product->getItemId()]);
            unset($product);
        }

        unset($productsData);
        unset($variations);

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
            $stores = $this->getStoresConfiguration();
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
     * @param array $configuration
     * @return $this
     */
    public function setStoresConfiguration(array $configuration)
    {
        $this->storesConfiguration = $configuration;

        return $this;
    }

    /**
     * @return array
     */
    public function getStoresConfiguration()
    {
        return $this->storesConfiguration;
    }

    /**
     * @param int $storeId
     * @param string $configKey
     * @return null|mixed
     */
    public function getStoreConfigValue($storeId, $configKey)
    {
        if (!$storeId) {
            return null;
        }

        $storesConfigurations = $this->getStoresConfiguration();

        if (!is_array($storesConfigurations) || empty($storesConfigurations)) {
            return null;
        }

        foreach ($storesConfigurations as $storeConfiguration) {
            if ($storeConfiguration['storeIdentifier'] !== $storeId) {
                continue;
            }

            if (isset($storeConfiguration['configuration'][$configKey])) {
                return $storeConfiguration['configuration'][$configKey];
            }
        }

        return null;
    }

    /**
     * Check store default and configuration languages to decide if language url prefix is needed.
     *
     * @return $this
     */
    protected function initLanguageUrlPrefix()
    {
        /** @var \Findologic\Plentymarkets\Parser\Stores $stores */
        $stores = $this->getRegistry()->get('Stores');

        $configurationLanguage = strtolower($this->getConfig()->getLanguage());
        $urlPrefix = '';

        if (
            $configurationLanguage != $stores->getStoreDefaultLanguage($this->getStorePlentyId()) &&
            $stores->isLanguageAvailableInStore($this->getStorePlentyId(), $configurationLanguage)
        ) {
            $urlPrefix = $configurationLanguage;
        }

        $this->setLanguageUrlPrefix($urlPrefix);

        return $this;
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
                $this->getLog()->warning(
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

    protected function getRequiredVariationValues():array
    {
        $variationValues = [];

        $categories = $this->registry->get('categories');
        if ($categories && !empty($categories->getResults())) {
            $variationValues[] = 'categories';
        }

        $salesPrices = $this->registry->get('salesprices');
        if ($salesPrices && !empty($salesPrices->getResults())) {
            $variationValues[] = 'salesPrices';
        }

        $attributes = $this->registry->get('attributes');
        if ($attributes && !empty($attributes->getResults())) {
            $variationValues[] = 'attributeValues';
        }

        // itemProperties aka. Eigenschaften
        $itemProperties = $this->registry->get('itemproperties');
        // properties aka. Merkmale
        $properties = $this->registry->get('properties');

        if ($itemProperties && $properties) {

            // If shop has no properties set don't request properties as it increases the export time without benefit.
            $allProperties = array_merge($itemProperties->getResults(), $properties->getResults());
            if (!empty($allProperties)) {
                $variationValues[] = 'properties.property';
            }
        }

        $units = $this->registry->get('units');
        if ($units && !empty($units->getResults())) {
            $variationValues[] = 'units.unit';
        }

        array_push(
            $variationValues,
            'barcodes',
            'clients',
            'properties',
            'images.image',
            'tags.tag',
            'base.item',
            'base.characteristics',
            'base.images'
        );

        return $variationValues;
    }

    /**
     * @param array|int $ids
     */
    private function trackSkippedProducts($ids)
    {
        if (is_array($ids)) {
            $this->skippedProductsIds = array_merge($this->skippedProductsIds, $ids);
            $this->skippedProductsCount += count($ids);
        } else {
            $this->skippedProductsIds[] = $ids;
            $this->skippedProductsCount++;
        }
    }
}
