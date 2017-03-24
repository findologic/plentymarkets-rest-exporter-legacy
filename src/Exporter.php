<?php

namespace Findologic\Plentymarkets;

use Findologic\Plentymarkets\Exception\CriticalException;
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
                throw new Exception\CustomerException('Could not find any results!');
            }

            foreach ($results['entries'] as $product) {
                $this->processProductData($product);
            }
        } catch (\Exception $e) {
            $this->handleException($e);
        }

        return $this->getWrapper()->getResults();
    }

    public function createProductItem($productData)
    {
        $product = new Product($this->getRegistry());
        $product->processInitialData($productData);

        return $product;
    }

    /**
     * @param array $productData
     * @return $this
     */
    public function processProductData($productData)
    {
        $product = $this->createProductItem($productData);
        $variations = $this->getClient()->getProductVariations($product->getitemId());
        $product->processVariations($variations);

        if (isset($variations['entries'])) {
            foreach ($variations['entries'] as $variation) {
                $product->processVariationsProperties(
                    $this->getClient()->getVariationProperties($product->getitemId(), $variation['id'])
                );
            }
        }

        $product->processImages($this->getClient()->getProductImages($product->getitemId()));
        //TODO: maybe after all processing validation is needed to ensure all neccesary fields for product is set?
        $this->getWrapper()->wrapProduct($product);
        unset($product);

        return $this;
    }

    /**
     * Handle the initiation all of data parsers and call method to parse the result from api
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
            throw new Exception\CustomerException('Could not get the attributes from api!');
        }

        foreach ($attributes->getResults() as $id => $attribute) {
            $attributes->parseAttributeName($this->getClient()->getAttributeName($id));
            $values = $attributes->parseValues($this->getClient()->getAttributeValues($id));
            // Get values frontend names
            foreach ($values as $valueId => $value) {
                $attributes->parseValueNames($id, $this->getClient()->getAttributeValueName($valueId));
            }
        }
    }

    /**
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