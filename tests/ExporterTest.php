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
        $exporterMock->setMethods(array('initAdditionalData', 'initCategoriesFullUrls', 'initAttributeValues', 'handleException'));
        $exporterMock = $exporterMock->getMock();

        /**
         * @var $exporterMock \PHPUnit_Framework_MockObject_MockObject
         */
        $exporterMock->expects($this->once())->method('initAdditionalData');
        $exporterMock->expects($this->once())->method('initCategoriesFullUrls');
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
        $exporterMock = $this->getExporterMockBuilder(
            array('registry' => $this->getRegistryMock())
        );
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
     */
    public function testInitAttributeValues()
    {
        $attributesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Attributes')
            ->setMethods(array('getResults', 'parseValues'))
            ->getMock();
        $attributesMock->expects($this->once())
            ->method('getResults')
            ->will($this->returnValue(array('1' => 'Test Attribute')));
        $attributesMock->expects($this->once())
            ->method('parseValues')
            ->will($this->returnValue(array('1' => 'Test Value', '2' => 'Test Value')));

        $registryMock = $this->getRegistryMock(array('get'));
        $registryMock->expects($this->any())
            ->method('get')
            ->will($this->returnValue($attributesMock));

        $exporterMock = $this->getExporterMockBuilder(array('registry' => $registryMock));
        $exporterMock->setMethods(array('initAdditionalData', 'initCategoriesFullUrls', 'handleException'));
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
     * Test parsing units data from api
     */
    public function testGetUnits()
    {
        $clientMock = $this->getMockBuilder('\Findologic\Plentymarkets\Client')
            ->disableOriginalConstructor()
            ->setMethods(array('getUnits'))
            ->getMock();

        $clientMock->expects($this->any())
            ->method('getUnits')
            ->will(
                $this->returnValue(
                    array('entries' => array(
                        array('id' => 1, 'unitOfMeasurement' => 'C62'),
                        array('id' => 2, 'unitOfMeasurement' => 'KGM'),
                    ))
                )
            );

        $exporterMock = $this->getExporterMockBuilder(array('client' => $clientMock));
        $exporterMock->setMethods(array('handleException'));
        $exporterMock = $exporterMock->getMock();

        $this->assertSame(array(1 => 'C62', 2 => 'KGM'), $exporterMock->getUnits());
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


    /**
     * Test if all methods are called to process the product
     */
    public function testProcessProductData()
    {
        $clientMock = $this->getMockBuilder('\Findologic\Plentymarkets\Client')
            ->disableOriginalConstructor()
            ->setMethods(array('getProductImages', 'getVariationProperties', 'getProductVariations'))
            ->getMock();

        $clientMock->expects($this->any())
            ->method('getProductVariations')
            ->will(
                $this->returnValue(
                    array('entries' => array(array('id' => 'Test')))
                )
            );

        $exporterMock = $this->getExporterMockBuilder(array('client' => $clientMock));
        $exporterMock->setMethods(array('createProductItem'));
        $exporterMock = $exporterMock->getMock();

        $productMock = $this->getMockBuilder('\Findologic\Plentymarkets\Product')
            ->disableOriginalConstructor()
            ->setMethods(array('processVariations', 'processImages', 'getItemId'))
            ->getMock();

        $productMock->expects($this->once())->method('processVariations');
        $productMock->expects($this->once())->method('processImages');
        $productMock->expects($this->atLeast(4))->method('getItemId')->will($this->returnValue(1));
        $exporterMock->expects($this->once())->method('createProductItem')->will($this->returnValue($productMock));

        $exporterMock->processProductData(array());
    }

    public function testProcessProductDataNoItem()
    {
        $exporterMock = $this->getExporterMockBuilder();
        $exporterMock->setMethods(array('createProductItem'));
        $exporterMock = $exporterMock->getMock();
        $productMock = $this->getMockBuilder('\Findologic\Plentymarkets\Product')
            ->disableOriginalConstructor()
            ->setMethods(array('getItemId', 'processVariations', 'processImages'))
            ->getMock();

        $productMock->expects($this->once())->method('getItemId')->will($this->returnValue(false));
        $productMock->expects($this->never())->method('processVariations');
        $productMock->expects($this->never())->method('processImages');

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
        $clientMock = $this->getMockBuilder('Findologic\Plentymarkets\Client')->disableOriginalConstructor()->getMock();
        $clientMock->expects($this->any())->method('setItemsPerPage')->willReturn($clientMock);

        $defaultMocks = array(
            'client' => $clientMock,
            'wrapper' => $this->getMockBuilder('Findologic\Plentymarkets\Wrapper\Csv')->getMock(),
            'logger' => $this->getMockBuilder('Logger')->disableOriginalConstructor()->getMock(),
            'registry' => $this->getMockBuilder('Findologic\Plentymarkets\Registry')->disableOriginalConstructor()->getMock()
        );

        $finalMocks = array_merge($defaultMocks, $mocks);

        $exporterMock = $this->getMockBuilder('Findologic\Plentymarkets\Exporter')
            ->setConstructorArgs($finalMocks);

        return $exporterMock;
    }

    /**
     * Helper function to get exporter mock
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getExporterMock()
    {
        $mock = $this->getExporterMockBuilder();
        $mock->setMethods(array('handleException'));
        $mock = $mock->getMock();

        return $mock;
    }

    /**
     * Helper function to get registry mock
     *
     * @param array|null $methods
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getRegistryMock($methods = null)
    {
        $mock = $this->getMockBuilder('\Findologic\Plentymarkets\Registry')
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();

        return $mock;
    }
}