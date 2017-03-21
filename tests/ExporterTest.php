<?php

namespace Findologic\PlentymarketsTest;

use Findologic\Plentymarkets\Exception\CustomerException;
use PHPUnit_Framework_TestCase;

class ExporterTest extends PHPUnit_Framework_TestCase
{
    /**
     * Init method should call necessary methods for initialising data for mapping ids to actuals values
     */
    public function testInit()
    {
        $exporterMock = $this->getExporterMockBuilder();
        $exporterMock->setMethods(array('initAdditionalData', 'initAttributeValues', 'handleException'));
        $exporterMock = $exporterMock->getMock();

        /**
         * @var $exporterMock \PHPUnit_Framework_MockObject_MockObject
         */
        $exporterMock->expects($this->once())->method('initAdditionalData');
        $exporterMock->expects($this->once())->method('initAttributeValues');
        $exporterMock->init();

        $this->assertInstanceOf('\Findologic\Plentymarkets\Wrapper\WrapperInterface', $exporterMock->getWrapper());
        $this->assertInstanceOf('\Findologic\Plentymarkets\Client', $exporterMock->getClient());
    }

    /**
     * Test if exception was handled
     */
    public function testInitException()
    {
        $exporterMock = $this->getExporterMockBuilder();
        $exporterMock->setMethods(array('initAdditionalData', 'initAttributeValues', 'handleException'));
        $exporterMock = $exporterMock->getMock();

        /**
         * @var $exporterMock \PHPUnit_Framework_MockObject_MockObject
         */
        $exporterMock->expects($this->once())->method('initAdditionalData')
            ->will($this->throwException(new \Findologic\Plentymarkets\Exception\CustomerException()));
        $exporterMock->expects($this->once())->method('handleException');
        $exporterMock->init();
    }

    /**
     * Init method should create parser objects and add those to registry
     */
    public function testInitAdditionalData()
    {
        $exporterMock = $this->getExporterMockBuilder(array('registry' => new \Findologic\Plentymarkets\Registry()));
        $exporterMock->setMethods(array('initAttributeValues', 'handleException'));
        $exporterMock = $exporterMock->getMock();

        $exporterMock->init();

        $items = array('Vat', 'Categories', 'SalesPrices', 'Attributes');

        foreach ($items as $item) {
            $this->assertInstanceOf('\Findologic\Plentymarkets\Parser\\' . $item, $exporterMock->getRegistry()->get($item));
        }
    }

    /**
     * Init attributes should get attributes from 'Attributes' parser and iterate over them to get attribute values
     * Each value also needs a call to parse value name so it should be as many calls to 'parseValueNames' function as
     * there is values for attribute
     */
    public function testInitAttributeValues()
    {
        $attributesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Attributes')
            ->setMethods(array('getResults', 'parseValues', 'parseValueNames'))
            ->getMock();
        $attributesMock->expects($this->once())
            ->method('getResults')
            ->will($this->returnValue(array('1' => 'Test Attribute')));
        $attributesMock->expects($this->once())
            ->method('parseValues')
            ->will($this->returnValue(array('1' => 'Test Value', '2' => 'Test Value')));
        $attributesMock->expects($this->exactly(2))
            ->method('parseValueNames');

        $registryMock = $this->getMockBuilder('\Findologic\Plentymarkets\Registry')
            ->setMethods(array('get'))
            ->getMock();
        $registryMock->expects($this->any())
            ->method('get')
            ->will($this->returnValue($attributesMock));

        $exporterMock = $this->getExporterMockBuilder(array('registry' => $registryMock));
        $exporterMock->setMethods(array('initAdditionalData', 'handleException'));
        $exporterMock = $exporterMock->getMock();

        $exporterMock->init();
    }

    /**
     * Test how method handles the product response from api
     */
    public function testGetProducts()
    {
        $clientMock = $this->getMockBuilder('\Findologic\Plentymarkets\Client')
            ->disableOriginalConstructor()
            ->setMethods(array('getProducts'))
            ->getMock();

        $clientMock->expects($this->any())
            ->method('getProducts')
            ->will(
                $this->returnValue(
                    array('entries' => array(array()))
                )
            );

        $exporterMock = $this->getExporterMockBuilder(array('client' => $clientMock));
        $exporterMock->setMethods(array('processProductData'));
        $exporterMock = $exporterMock->getMock();

        $exporterMock->expects($this->once())->method('processProductData');

        $exporterMock->getProducts();
    }

    /**
     * If api returns no result an exception should be thrown and handleException method should be called
     */
    public function testGetProductsException()
    {
        $exporterMock = $this->getExporterMock();

        $exporterMock->expects($this->once())->method('handleException');

        $exporterMock->getProducts();
    }

    /**
     * The tested method should return instance of \Findologic\Plentymarkets\Product
     */
    public function testCreateProductItem()
    {
        $exporterMock = $this->getExporterMock();
        $product = $exporterMock->createProductItem(array());

        $this->assertInstanceOf('\Findologic\Plentymarkets\Product', $product);
    }

    public function testProcessProductData()
    {
        $exporterMock = $this->getExporterMockBuilder();
        $exporterMock->setMethods(array('createProductItem'));
        $exporterMock = $exporterMock->getMock();

        $productMock = $this->getMockBuilder('\Findologic\Plentymarkets\Product')
            ->disableOriginalConstructor()
            ->setMethods(array('processVariations', 'processImages'))
            ->getMock();

        $productMock->expects($this->once())->method('processVariations');
        $productMock->expects($this->once())->method('processImages');
        $exporterMock->expects($this->once())->method('createProductItem')->will($this->returnValue($productMock));

        $exporterMock->processProductData(array());
    }

    /* ------ helper functions ------ */

    /**
     * Helper function for building exporter mock if setting default mocks for constructor
     *
     * @param array $mocks
     * @return \PHPUnit_Framework_MockObject_MockBuilder
     */
    protected function getExporterMockBuilder($mocks = array())
    {
        $defaultMocks = array(
            'client' => $this->getMockBuilder('Findologic\Plentymarkets\Client')->disableOriginalConstructor()->getMock(),
            'wrapper' => $this->getMockBuilder('Findologic\Plentymarkets\Wrapper\Csv')->getMock(),
            'logger' => $this->getMockBuilder('Logger')->disableOriginalConstructor()->getMock(),
            'registry' => $this->getMockBuilder('Findologic\Plentymarkets\Registry')->getMock()
        );

        $finalMocks = array_merge($defaultMocks, $mocks);

        $exporterMock = $this->getMockBuilder('Findologic\Plentymarkets\Exporter')
            ->setConstructorArgs($finalMocks);

        return $exporterMock;
    }

    /**
     * Helper function to get exporter mock
     *
     * @return \PHPUnit_Framework_MockObject_MockBuilder|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getExporterMock()
    {
        $mock = $this->getExporterMockBuilder();
        $mock->setMethods(array('handleException'));
        $mock = $mock->getMock();

        return $mock;
    }
}