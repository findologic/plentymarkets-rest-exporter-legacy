<?php

namespace Findologic\Plentymarkets;

use Findologic\Plentymarkets\Exception\CriticalException;
use Findologic\Plentymarkets\Exception\CustomerException;
use Findologic\Plentymarkets\Parser\ParserFactory;
use Findologic\Plentymarkets\Parser\Attributes;
use Findologic\Plentymarkets\Wrapper\WrapperInterface;
use \Logger;

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
     * @var \Logger $logger
     */
    protected $logger;

    /**
     * @var \Findologic\Plentymarkets\Registry $registry
     */
    protected $registry;


    protected $additionalData = array('Vat', 'Categories', 'SalesPrices', 'Attributes', 'Manufacturers');

    /**
     * @param \Findologic\Plentymarkets\Client $client
     * @param \Findologic\Plentymarkets\Wrapper\WrapperInterface $wrapper
     */
    public function __construct(Client $client, WrapperInterface $wrapper, Logger $logger, Registry $registry)
    {
        $this->client = $client;
        $this->wrapper = $wrapper;
        $this->logger = $logger;
        $this->registry = $registry;
    }

    /**
     * Init neccessary data for mapping ids of some item fields with actual names
     *
     * @return $this
     */
    public function init()
    {
        try {
            $this->logger->info('Starting to initialise necessary data (categories, attributes, etc.)');
            $this->getClient()->login();
            $this->initAdditionalData();
            $this->initCategoriesFullUrls();
            $this->initAttributeValues();
            $this->logger->info('Finished initialising necessary data');
        } catch (\Exception $e) {
            $this->handleException($e);
        }

        return $this;
    }

    public function getWrapper()
    {
        return $this->wrapper;
    }

    public function getClient()
    {
        return $this->client;
    }

    public function getRegistry()
    {
        return $this->registry;
    }


    /**
     * @param int $numberOfItemsPerPage
     * @param int $page
     * @return mixed
     */
    public function getProducts($itemsPerPage = null, $page = 1)
    {
        try {
            $continue = true;

            $this->logger->info('Starting product processing.)');

            // Cycle the call for products to api until all we have all products
            while ($continue) {
                $this->getClient()->setItemsPerPage($itemsPerPage)->setPage($page);
                $results = $this->getClient()->getProducts();

                // Check if there is any results. Products is contained is 'entries' value of response array
                if (!$results || !isset($results['entries'])) {
                    throw new CustomerException('Could not find any results!');
                }

                $limit = $page * $itemsPerPage;
                $offset = $limit - $itemsPerPage;
                $this->logger->info('Processing items from ' . $offset . ' to ' . $limit);

                foreach ($results['entries'] as $product) {
                    $this->processProductData($product);
                }

                if (!isset($results['isLastPage']) || $results['isLastPage'] == true) {
                    $continue = false;
                }

                $page++;
            }

            $this->logger->info('All products has been processed.)');

            $this->getWrapper()->allItemsHasBeenProcessed();

        } catch (\Exception $e) {
            $this->handleException($e);
        }

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

        $variations = $this->getClient()->getProductVariations($product->getItemId());

        if (isset($variations['entries'])) {
            $product->processVariations($variations);
            foreach ($variations['entries'] as $variation) {
                $product->processVariationsProperties(
                    $this->getClient()->getVariationProperties($product->getItemId(), $variation['id'])
                );
            }
        }

        $product->processImages($this->getClient()->getProductImages($product->getItemId()));
        $this->getWrapper()->wrapItem($product->getResults());
        unset($product);

        return $this;
    }

    /**
     * Function for gettings the units id with actual values
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
                $parser = ParserFactory::create($type);
                $this->getRegistry()->set($type, $parser);
                $continue = true;
                //TODO: replace with config value
                $itemsPerPage = 50;
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

                $this->logger->info('- ' . $type . ' data was parsed.');
            }
        }

        return $this;
    }

    /**
     * Call all neccessary methods to fully get attributes values
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
            $attributes->parseAttributeName($this->getClient()->getAttributeName($id));
            $values = $attributes->parseValues($this->getClient()->getAttributeValues($id));
            // Get values for frontend names
            foreach ($values as $valueId => $value) {
                $attributes->parseValueNames($id, $this->getClient()->getAttributeValueName($valueId));
            }
        }

        return $this;
    }

    /**
     * @codeCoverageIgnore
     * @param \Exception $e
     */
    protected function handleException($e)
    {
        //TODO: logging implementation
        if ($e instanceof CriticalException) {
            $this->logger->fatal('Fatal error: ' . $e->getMessage());
            die();
        }

        $this->logger->warn('An error occured while initializing necessary data: ' . $e->getMessage());
    }
}