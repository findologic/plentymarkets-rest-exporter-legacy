<?php

namespace Findologic\Plentymarkets;

use Findologic\Plentymarkets\Exception\CriticalException;
use Findologic\Plentymarkets\Exception\CustomerException;
use Findologic\Plentymarkets\Parser\ParserFactory;
use Findologic\Plentymarkets\Parser\Attributes;
use Findologic\Plentymarkets\Wrapper\WrapperInterface;

class Exporter
{
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


    protected $additionalData = array('Vat', 'Categories', 'SalesPrices', 'Attributes', 'Manufacturers');

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
    }

    /**
     * Init necessary data for mapping ids of some item fields with actual names
     *
     * @return $this
     */
    public function init()
    {
        try {
            $this->log->info('Starting to initialise necessary data (categories, attributes, etc.)');
            $this->getClient()->login();
            $this->initAdditionalData();
            $this->initCategoriesFullUrls();
            $this->initAttributeValues();
            $this->log->info('Finished initialising necessary data');
        } catch (\Exception $e) {
            $this->log->handleException($e);
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
     * Get all products
     *
     * @param int $numberOfItemsPerPage
     * @param int $page
     * @return mixed
     */
    public function getProducts($itemsPerPage = null, $page = 1)
    {
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

                if (!isset($results['isLastPage']) || $results['isLastPage'] == true) {
                    $continue = false;
                }

                $page++;
            }

            $this->getLog()->info('All products has been processed.');

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
        $product->setProtocol($this->getClient()->getProtocol());
        $product->processInitialData($productData);

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
            return $this;
        }

        $continue = true;
        $itemsPerPage = Config::NUMBER_OF_ITEMS_PER_PAGE;
        $page = 1;

        while ($continue) {
            $this->getClient()->setItemsPerPage($itemsPerPage)->setPage($page);
            $variations = $this->getClient()->getProductVariations($product->getItemId());

            if (isset($variations['entries'])) {
                $product->processVariations($variations);
                foreach ($variations['entries'] as $variation) {
                    $product->processVariationsProperties(
                        $this->getClient()->getVariationProperties($product->getItemId(), $variation['id'])
                    );
                }
            }

            if (!isset($variations['entries']) || $variations['isLastPage']) {
                $continue = false;
            }

            $page++;
        }

        $product->processImages($this->getClient()->getProductImages($product->getItemId()));
        $this->getWrapper()->wrapItem($product->getResults());
        unset($product);

        return $this;
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
     * Call categories tree parser for handling data
     */
    protected function initCategoriesFullUrls()
    {
        $this->getRegistry()->get('categories')->parseCategoryFullUrls($this->getClient()->getCategoriesBranches());

        return $this;
    }

    /**
     * Handle the initiation of all data parsers and call method to parse the result from api
     */
    protected function initAdditionalData()
    {
        foreach ($this->additionalData as $type) {
            $methodName = 'get' . ucwords($type);
            if (!method_exists($this->getClient(), $methodName)) {
                continue;
            }

            if (!$this->getRegistry()->get($type)) {
                $parser = ParserFactory::create($type, $this->getRegistry());
                $this->getRegistry()->set($type, $parser);
                $continue = true;
                $itemsPerPage = Config::NUMBER_OF_ITEMS_PER_PAGE;
                $page = 1;
                while ($continue) {
                    $this->getClient()->setItemsPerPage($itemsPerPage)->setPage($page);
                    $results = $this->getClient()->$methodName();
                    $parser->parse($results);
                    $page++;
                    if (!$results || $results['isLastPage']) {
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