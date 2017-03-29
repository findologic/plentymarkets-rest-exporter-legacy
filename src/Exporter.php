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


    protected $additionalData = array('Vat', 'Categories', 'SalesPrices', 'Attributes');

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
            $this->initAdditionalData();
            $this->initAttributeValues();
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
     * @param null $offset
     * @param null $limit
     * @return mixed
     */
    public function getProducts($offset = null, $limit = null)
    {
        try {
            $results = $this->getClient()->getProducts($offset, $limit);

            if (!$results || !isset($results['entries'])) {
                throw new CustomerException('Could not find any results!');
            }

            foreach ($results['entries'] as $product) {
                $this->processProductData($product);
            }

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
                $parser->parse($this->getClient()->$methodName());
            }
        }
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
    }

    /**
     * @codeCoverageIgnore
     * @param \Exception $e
     */
    protected function handleException($e)
    {
        //TODO: logging implementation
        if ($e instanceof CriticalException) {
            $this->logger->error($e->getMessage());
            die();
        }
    }
}