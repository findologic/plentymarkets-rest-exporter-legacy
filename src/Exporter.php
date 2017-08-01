<?php

namespace Findologic\Plentymarkets;

use Findologic\Plentymarkets\Data\Countries;
use Findologic\Plentymarkets\Exception\CriticalException;
use Findologic\Plentymarkets\Exception\CustomerException;
use Findologic\Plentymarkets\Parser\ParserFactory;
use Findologic\Plentymarkets\Parser\Attributes;
use Findologic\Plentymarkets\Wrapper\WrapperInterface;

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
     * @var Log $log
     */
    protected $log;

    /**
     * @var \Findologic\Plentymarkets\Registry $registry
     */
    protected $registry;

    /**
     * List of data parsers which should be initialised before product parsing.
     * !IMPORTANT! After creating new parser class it should also be inserted here
     *
     * @var array
     */
    protected $additionalDataParsers = array('Vat', 'Categories', 'SalesPrices', 'Attributes', 'Manufacturers', 'Stores', 'PropertyGroups');

    /**
     * Count of products skipped during the export
     *
     * @var int
     */
    protected $skippedProductsCount = 0;

    /**
     * Standard vat value from rest
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
     * @var \PlentyConfig
     */
    protected $config;

    /**
     * @param \Findologic\Plentymarkets\Client $client
     * @param \Findologic\Plentymarkets\Wrapper\WrapperInterface $wrapper
     * @param \Findologic\Plentymarkets\Log $log
     * @param \Findologic\Plentymarkets\Registry $registry
     */
    public function __construct(Client $client, WrapperInterface $wrapper, Log $log, Registry $registry)
    {
        $this->client = $client;
        $this->wrapper = $wrapper;
        $this->log = $log;
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
        try {
            $this->getLog()->info('Starting to initialise necessary data (categories, attributes, etc.)');
            $this->getClient()->login();
            $this->initAdditionalData();
            $this->initCategoriesFullUrls();
            $this->initAttributeValues();
            $this->getLog()->info('Finished initialising necessary data');
        } catch (\Exception $e) {
            $this->getLog()->handleException($e);
        }

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
     * @return Log
     */
    public function getLog()
    {
        return $this->log;
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
     */
    public function getProducts($itemsPerPage = null, $page = 1)
    {
        if ($itemsPerPage === null) {
            $itemsPerPage = self::NUMBER_OF_ITEMS_PER_PAGE;
        }

        try {
            $continue = true;

            $this->getLog()->info('Starting product processing.');

            // Cycle the call for products to api until all we have all products
            while ($continue) {
                $this->getClient()->setItemsPerPage($itemsPerPage)->setPage($page);
                $results = $this->getClient()->getProducts();

                // Check if there is any results. Products is contained is 'entries' value of response array
                if (!$results || !isset($results['entries'])) {
                    throw new CustomerException('Could not find any results!');
                }

                $this->getLog()->info('Processing items from ' . (($page - 1) * $itemsPerPage) . ' to ' . ($page * $itemsPerPage));

                foreach ($results['entries'] as $product) {
                    $this->processProductData($product);
                }

                if (!$results || !isset($results['isLastPage']) || $results['isLastPage'] == true) {
                    $continue = false;
                }

                $page++;
            }

            $this->getLog()->info('Products processing finished. ' . $this->skippedProductsCount . ' products where skipped.');
            $this->getWrapper()->allItemsHasBeenProcessed();

        } catch (\Exception $e) {
            $this->getLog()->handleException($e);
        }

        $this->getLog()->info('Data processing finished.');

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
        $product
            ->setProtocol($this->getClient()->getProtocol())
            ->setStoreUrl($this->getConfig()->getDomain())
            ->setLanguageCode($this->getConfig()->getLanguage())
            ->setAvailabilityIds($this->getConfig()->getAvailabilityId())
            ->setPriceId($this->getConfig()->getPriceId())
            ->setRrpPriceId($this->getConfig()->getRrpId())
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
        if (!$product->getItemId()) {
            $this->skippedProductsCount++;
            $this->getLog()->trace('Product was skipped as it has no id.');
            return $this;
        }

        $continue = true;
        $page = 1;

        while ($continue) {
            $this->getClient()->setItemsPerPage(self::NUMBER_OF_ITEMS_PER_PAGE)->setPage($page);
            $variations = $this->getClient()->getProductVariations($product->getItemId(), $this->getConfig()->getLanguage());

            if (isset($variations['entries'])) {
                $product->processVariations($variations);
                foreach ($variations['entries'] as $variation) {
                    $product->processVariationsProperties(
                        $this->getClient()->getVariationProperties($product->getItemId(), $variation['id'])
                    );
                }
            }

            if (!$variations || !isset($variations['entries']) || $variations['isLastPage']) {
                $continue = false;
            }

            $page++;
        }

        if ($product->hasData()) {
            $product->processImages($this->getClient()->getProductImages($product->getItemId()));
            $this->getWrapper()->wrapItem($product->getResults());
        } else {
            $this->skippedProductsCount++;
            $this->getLog()->debug('Product with id ' . $product->getItemId() . ' was skipped as it has no correct data (all variations could be inactive or etc.)');
        }

        unset($product);

        return $this;
    }

    /**
     * Get standard vat country, if there is no configured country call api
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
                    $this->storePlentyId = $store['storeIdentifier'];
                    $data = $this->getClient()->getStandardVat($this->storePlentyId);
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
            $this->getRegistry()->get('categories')->parseCategoryFullUrls($results);
            $page++;
            if (!$results || !isset($results['isLastPage']) ||$results['isLastPage']) {
                $continue = false;
            }
        }

        return $this;
    }

    /**
     * Handle the initiation of all data parsers and call method to parse the result from api
     */
    protected function initAdditionalData()
    {
        foreach ($this->additionalDataParsers as $type) {
            $methodName = 'get' . ucwords($type);
            if (!method_exists($this->getClient(), $methodName)) {
                $this->getLog()->warn(
                    'Plugin tried to call method from api client which do not exists when initialising parsers. ' .
                    'Parser type: ' . $type .
                    ' Method called: ' . $methodName,
                    true
                );
                continue;
            }

            if (!$this->getRegistry()->get($type)) {
                $parser = ParserFactory::create($type, $this->getRegistry());
                $parser->setLanguageCode($this->getConfig()->getLanguage());
                $parser->setTaxRateCountryCode($this->getStandardVatCountry());
                $this->getRegistry()->set($type, $parser);
                $continue = true;
                $page = 1;
                while ($continue) {
                    $this->getClient()->setItemsPerPage(self::NUMBER_OF_ITEMS_PER_PAGE)->setPage($page);
                    $results = $this->getClient()->$methodName($this->storePlentyId);
                    $parser->parse($results);
                    $page++;
                    if (!$results || !isset($results['isLastPage']) ||$results['isLastPage']) {
                        $continue = false;
                    }
                }

                $this->getLog()->info('- ' . $type . ' data was parsed.');
            }
        }

        return $this;
    }

    /**
     * Call all necessary methods to fully get attributes values
     *
     * @throws Exception\CustomerException
     */
    protected function initAttributeValues()
    {
        $attributes = $this->getRegistry()->get('attributes');

        if (!$attributes || !$attributes instanceof Attributes) {
            throw new CustomerException('Could not get the attributes from api!');
        }

        foreach ($attributes->getResults() as $id => $attribute) {
            $attributes->parseValues($this->getClient()->getAttributeValues($id));
        }

        return $this;
    }
}
