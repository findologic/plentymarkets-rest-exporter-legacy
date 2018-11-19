<?php

namespace Findologic\PlentymarketsTest;

use Findologic\Plentymarkets\Exception\CriticalException;
use Findologic\Plentymarkets\Exception\CustomerException;
use Findologic\Plentymarkets\Exception\ThrottlingException;
use PHPUnit_Framework_TestCase;

class ExporterTest extends PHPUnit_Framework_TestCase
{
    public function initProvider()
    {
        return [
            'Config values provided, defaultLanguage is equal to current language' => [1, 2, 3, 4, 3, 4, 'en', 'en', false, ''],
            'Config values provided, defaultLanguage is not current language; current language is not in languageList' => [1, 2, 3, 4, 3, 4, 'en', 'de', false, ''],
            'Config values provided missing, use store values, defaultLanguage is not current language; current language is in languageList' => [1, 2, false, false, 1, 2, 'en', 'de', true, 'en']
        ];
    }

    /**
     * Init method should call necessary methods for initialising data for mapping ids to actual values.
     *
     * @param int $priceId
     * @param int $rrpId
     * @param int|bool $configPriceId
     * @param int|bool $configRrpId
     * @param int $expectedPriceId
     * @param int $expectedRrpId
     *
     * @dataProvider initProvider
     */
    public function testInit($priceId, $rrpId, $configPriceId, $configRrpId, $expectedPriceId, $expectedRrpId, $configurationLanguage, $storeDefaultLanguage, $isAvailable, $expectedPrefix)
    {
        $storesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Stores')
            ->disableOriginalConstructor()
            ->setMethods(['getStoreDefaultLanguage', 'isLanguageAvailableInStore'])
            ->getMock();

        $storesMock->expects($this->any())->method('getStoreDefaultLanguage')->willReturn($storeDefaultLanguage);
        $storesMock->expects($this->any())->method('isLanguageAvailableInStore')->willReturn($isAvailable);

        $salesPricesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\SalesPrices')
            ->disableOriginalConstructor()
            ->setMethods(['getDefaultPrice', 'getDefaultRrp'])
            ->getMock();

        $salesPricesMock->expects($this->any())->method('getDefaultPrice')->willReturn($priceId);
        $salesPricesMock->expects($this->any())->method('getDefaultRrp')->willReturn($rrpId);

        $registryMock = $this->getRegistryMock(['get']);
        $registryMock->expects($this->any())->method('get')->willReturnMap([
            ['log', false],
            ['SalesPrices', $salesPricesMock],
            ['Stores', $storesMock]
        ]);

        $configMock = $this->getConfigMock();
        $configMock->expects($this->any())->method('getPriceId')->willReturn($configPriceId);
        $configMock->expects($this->any())->method('getRrpId')->willReturn($configRrpId);
        $configMock->expects($this->any())->method('getLanguage')->willReturn($configurationLanguage);

        $exporterMock = $this->getExporterMockBuilder();
        $exporterMock->setMethods(['getRegistry', 'getConfig', 'initAdditionalData', 'initCategoriesFullUrls', 'initAttributeValues', 'handleException']);
        $exporterMock = $exporterMock->getMock();

        /**
         * @var $exporterMock \Findologic\Plentymarkets\Exporter|\PHPUnit_Framework_MockObject_MockObject
         */
        $exporterMock->expects($this->any())->method('getRegistry')->willReturn($registryMock);
        $exporterMock->expects($this->any())->method('getConfig')->willReturn($configMock);
        $exporterMock->expects($this->once())->method('initAdditionalData');
        $exporterMock->expects($this->once())->method('initCategoriesFullUrls');
        $exporterMock->expects($this->once())->method('initAttributeValues');
        $exporterMock->init();

        $this->assertEquals($expectedPriceId, $exporterMock->getPriceId(), 'Price id is not matching expected value');
        $this->assertEquals($expectedRrpId, $exporterMock->getRrpId(), 'Rrp price id is not matching expected value');
        $this->assertEquals($expectedPrefix, $exporterMock->getLanguageUrlPrefix(), 'Language url prefix is not matching expected value');

        $this->assertInstanceOf('\Findologic\Plentymarkets\Wrapper\WrapperInterface', $exporterMock->getWrapper());
        $this->assertInstanceOf('\Findologic\Plentymarkets\Client', $exporterMock->getClient());
    }

    /**
     * Test if exception was thrown
     */
    public function testInitException()
    {
        $exporterMock = $this->getExporterMockBuilder();
        $exporterMock->setMethods(array('initAdditionalData', 'initAttributeValues'));
        $exporterMock = $exporterMock->getMock();
        $exporterMock->expects($this->once())->method('initAdditionalData')->will($this->throwException(new CustomerException()));

        $this->setExpectedException(CustomerException::class);

        $exporterMock->init();
    }

    /**
     * Init method should create parser objects and add those to registry
     */
    public function testInitAdditionalData()
    {
        $configMock = $this->getConfigMock();
        $logMock = $this->getMockBuilder('\Logger')->disableOriginalConstructor()->getMock();
        $clientMock = $this->getMockBuilder('\Findologic\Plentymarkets\Client')
            ->setConstructorArgs(array($configMock, $logMock, $logMock))
            ->setMethods(array())
            ->getMock();

        $clientMock->expects($this->any())->method('setItemsPerPage')->willReturn($clientMock);
        $clientMock->expects($this->any())->method('getWebstores')->willReturn(array());
        // Check if category branches will be parsed
        $clientMock->expects($this->once())->method('getCategoriesBranches');

        $exporterMock = $this->getExporterMockBuilder(
            array('registry' => $this->getRegistryMock(), 'client' => $clientMock)
        );

        $exporterMock->setMethods(array('initAttributeValues', 'getConfig', 'getStandardVatCountry'));
        $exporterMock = $exporterMock->getMock();
        $exporterMock->expects($this->any())->method('getConfig')->willReturn($configMock);
        $exporterMock->expects($this->any())->method('getStandardVatCountry')->willReturn('DE');

        $exporterMock->init();

        $items = array('Vat', 'Categories', 'SalesPrices', 'Attributes', 'Stores', 'Manufacturers', 'Properties', 'Units');

        foreach ($items as $item) {
            $this->assertInstanceOf('\Findologic\Plentymarkets\Parser\\' . $item, $exporterMock->getRegistry()->get($item));
        }
    }

    /**
     * Init attributes should get attributes from 'Attributes' parser and iterate over them to get attribute values
     */
    public function testInitAttributeValues()
    {
        $storesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Stores')
            ->disableOriginalConstructor()
            ->setMethods(['getStoreDefaultLanguage', 'isLanguageAvailableInStore'])
            ->getMock();

        $salesPricesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\SalesPrices')
            ->disableOriginalConstructor()
            ->setMethods(['getDefaultPrice', 'getDefaultRrp'])
            ->getMock();

        $salesPricesMock->expects($this->any())->method('getDefaultPrice')->willReturn(1);
        $salesPricesMock->expects($this->any())->method('getDefaultRrp')->willReturn(2);

        $attributesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Attributes')
            ->disableOriginalConstructor()
            ->setMethods(['getResults', 'parseValues'])
            ->getMock();
        $attributesMock->expects($this->once())->method('getResults')->willReturn(['1' => 'Test Attribute']);
        $attributesMock->expects($this->once())->method('parseValues')->willReturn(['1' => 'Test Value', '2' => 'Test Value']);

        $registryMock = $this->getRegistryMock(['get']);
        $registryMock->expects($this->any())->method('get')->willReturnMap([
            ['attributes', $attributesMock],
            ['SalesPrices', $salesPricesMock],
            ['Stores', $storesMock]
        ]);

        $exporterMock = $this->getExporterMockBuilder(['registry' => $registryMock]);
        $exporterMock->setMethods(['initAdditionalData', 'initCategoriesFullUrls', 'handleException']);
        $exporterMock = $exporterMock->getMock();

        $exporterMock->init();
    }

    public function providerGetProducts()
    {
        return array(
            array(
                array(
                    'totalsCount' => 3,
                    'entries' => array(
                        array('id' => 0),
                        array('id' => 1),
                        array('id' => 2)
                    )
                )
            )
        );
    }

    /**
     * @dataProvider providerGetProducts
     */
    public function testGetProducts($products)
    {
        $configMock = $this->getConfigMock();
        $logMock = $this->getMockBuilder('\Logger')->disableOriginalConstructor()->getMock();
        $clientMock = $this->getMockBuilder('\Findologic\Plentymarkets\Client')
            ->setConstructorArgs(array($configMock, $logMock, $logMock))
            ->setMethods(array('getProducts', 'getProductVariations'))
            ->getMock();

        $clientMock->expects($this->once())->method('getProducts')->willReturn($products);

        $exporterMock = $this->getExporterMockBuilder(array('client' => $clientMock));
        $exporterMock->setMethods(array('createProductItem', 'processProductData'));
        $exporterMock = $exporterMock->getMock();

        $exporterMock->expects($this->once())->method('processProductData');

        $exporterMock->getProducts();
    }

    /**
     * If API returns no result an exception should be thrown
     */
    public function testGetProductsThrottlingException()
    {
        $configMock = $this->getConfigMock();
        $logMock = $this->getMockBuilder('\Logger')->disableOriginalConstructor()->getMock();
        $clientMock = $this->getMockBuilder('\Findologic\Plentymarkets\Client')
            ->setConstructorArgs(array($configMock, $logMock, $logMock))
            ->setMethods(array('getProducts'))
            ->getMock();

        $clientMock->expects($this->once())->method('getProducts')->willThrowException(new ThrottlingException());

        $exporterMock = $this->getExporterMockBuilder(array('client' => $clientMock, 'log' => $logMock))
            ->setMethods(array('init'))
            ->getMock();

        $logMock->expects($this->once())->method('fatal');

        $exporterMock->getProducts();
    }

    /**
     * If API returns no result an exception should be thrown
     */
    public function testGetProductsException()
    {
        $configMock = $this->getConfigMock();
        $logMock = $this->getMockBuilder('\Logger')->disableOriginalConstructor()->getMock();
        $clientMock = $this->getMockBuilder('\Findologic\Plentymarkets\Client')
            ->setConstructorArgs(array($configMock, $logMock, $logMock))
            ->setMethods(array('getProducts'))
            ->getMock();

        $clientMock->expects($this->once())->method('getProducts')->willReturn(array());

        $exporterMock = $this->getExporterMockBuilder(array('client' => $clientMock))
            ->setMethods(array('init'))
            ->getMock();

        $this->setExpectedException(CustomerException::class);

        $exporterMock->getProducts();
    }

    /**
     * The tested method should return instance of \Findologic\Plentymarkets\Product
     */
    public function testCreateProductItem()
    {
        $exporterMock = $this->getExporterMockBuilder()->setMethods(null)->getMock();

        $product = $exporterMock->createProductItem([]);
        $this->assertInstanceOf('\Findologic\Plentymarkets\Product', $product);
    }

    /**
     * Test if all methods are called to process the product
     */
    public function testProcessProductData()
    {
        $clientMock = $this->getMockBuilder('\Findologic\Plentymarkets\Client')
            ->disableOriginalConstructor()
            ->setMethods(array('getConfig', 'getProductVariations'))
            ->getMock();
        $clientMock->expects($this->any())->method('getConfig')->willReturn($this->getConfigMock());
        $clientMock->expects($this->any())
            ->method('getProductVariations')
            ->willReturn(
                array(
                    'entries' => array(
                        array('id' => 'Test', 'itemId' => '1', 'isActive' => true, 'availability' => 1, 'variationCategories' => array(array('categoryId' => '1')), 'itemImages' => array(), 'variationProperties' => array()),
                        array('id' => 'Test', 'itemId' => '1', 'isActive' => true, 'availability' => 1, 'variationCategories' => array(array('categoryId' => '1')), 'itemImages' => array(), 'variationProperties' => array())
                    ),
                    'isLastPage' => true
                )
            );

        $exporterMock = $this->getExporterMockBuilder(array('client' => $clientMock));
        $exporterMock->setMethods(array('createProductItem'));
        $exporterMock = $exporterMock->getMock();

        $vatMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Vat')
            ->disableOriginalConstructor()
            ->setMethods(array('getVatRateByVatId'))
            ->getMock();

        $categoriesMock = $this->getMockBuilder('Findologic\Plentymarkets\Parser\Categories')
            ->disableOriginalConstructor()
            ->setMethods(array('getCategoryFullNamePath', 'getCategoryFullPath'))
            ->getMock();

        $categoriesMock->expects($this->any())->method('getCategoryFullNamePath')->willReturn('Test');
        $categoriesMock->expects($this->any())->method('getCategoryFullPath')->willReturn('Test');

        $registryMock = $this->getRegistryMock();
        $registryMock->set('Vat', $vatMock);
        $registryMock->set('Categories', $categoriesMock);

        $productMock = $this->getMockBuilder('\Findologic\Plentymarkets\Product')
            ->setConstructorArgs(array('registry' => $registryMock))
            ->setMethods(array('processImages', 'getItemId', 'processVariationAttributes', 'hasValidData', 'processVariation'))
            ->getMock();

        $productMock->expects($this->exactly(2))->method('processVariation')->willReturn(true);
        $productMock->expects($this->exactly(2))->method('processImages');
        $productMock->expects($this->once())->method('hasValidData')->willReturn(true);
        $productMock->expects($this->any())->method('getItemId')->willReturn(1);
        $productMock->expects($this->any())->method('processVariationAttributes')->willReturn($productMock);

        $exporterMock->expects($this->once())->method('createProductItem')->willReturn($productMock);

        $exporterMock->processProductData(array('1' => array()));
    }

    public function providerProcessProductDataProductDoNotHaveData()
    {
        return array(
            array(
                array(
                    'entries' => array(array('id' => 'Test', 'isMain' => false, 'itemId' => '1', 'mainVariationId' => 'Test', 'isActive' => false, 'availability' => 1, 'variationCategories' => array(array('categoryId' => '1')))),
                    'isLastPage' => true
                )
            ),
            array(
                array(
                    'entries' => array(array('id' => 'Test', 'isMain' => false, 'itemId' => '1', 'mainVariationId' => 'Test', 'isActive' => true, 'availability' => 1, 'variationCategories' => array(array('categoryId' => '1')))),
                    'isLastPage' => true
                )
            )
        );
    }

    /**
     * Test if product wrapping is skipped if product has data flag is false
     *
     * @dataProvider providerProcessProductDataProductDoNotHaveData
     */
    public function testProcessProductDataProductDoNotHaveData($productVariations)
    {
        $clientMock = $this->getMockBuilder('\Findologic\Plentymarkets\Client')
            ->disableOriginalConstructor()
            ->setMethods(array('getConfig', 'getProductVariations'))
            ->getMock();
        $clientMock->expects($this->any())->method('getConfig')->willReturn($this->getConfigMock());
        $clientMock->expects($this->any())->method('getProductVariations')->willReturn($productVariations);

        $exporterMock = $this->getExporterMockBuilder(array('client' => $clientMock));
        $exporterMock->setMethods(array('createProductItem'));
        $exporterMock = $exporterMock->getMock();

        $vatMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Vat')
            ->disableOriginalConstructor()
            ->setMethods(array('getVatRateByVatId'))
            ->getMock();

        $categoriesMock = $this->getMockBuilder('Findologic\Plentymarkets\Parser\Categories')
            ->disableOriginalConstructor()
            ->setMethods(array('getCategoryFullNamePath', 'getCategoryFullPath'))
            ->getMock();

        $registryMock = $this->getRegistryMock();
        $registryMock->set('Vat', $vatMock);
        $registryMock->set('Categories', $categoriesMock);

        $productMock = $this->getMockBuilder('\Findologic\Plentymarkets\Product')
            ->setConstructorArgs(array('registry' => $registryMock))
            ->setMethods(array('processImages', 'getItemId', 'processVariationAttributes'))
            ->getMock();

        $productMock->expects($this->never())->method('processImages');
        $productMock->expects($this->any())->method('getItemId')->willReturn(1);
        $productMock->expects($this->any())->method('processVariationAttributes')->willReturn($productMock);

        $exporterMock->expects($this->once())->method('createProductItem')->willReturn($productMock);

        $this->assertEquals(null, $exporterMock->getSkippedProductsCount());
        $exporterMock->processProductData(array('1' => array()));
        $this->assertEquals(1, $exporterMock->getSkippedProductsCount());
    }

    /**
     * If created product don't have an id then processing should be skipped
     */
    public function testProcessProductDataNoItem()
    {
        $exporterMock = $this->getExporterMockBuilder();
        $exporterMock->setMethods(array('createProductItem'));
        $exporterMock = $exporterMock->getMock();

        $productMock = $this->getMockBuilder('\Findologic\Plentymarkets\Product')
            ->disableOriginalConstructor()
            ->setMethods(array('getItemId', 'processVariation', 'processImages'))
            ->getMock();

        $productMock->expects($this->any())->method('getItemId')->will($this->returnValue(false));
        $productMock->expects($this->never())->method('processVariation');
        $productMock->expects($this->never())->method('processImages');

        $exporterMock->expects($this->any())->method('createProductItem')->will($this->returnValue($productMock));

        $exporterMock->processProductData(array());
    }

    public function getStandartVatProvider()
    {
        return array(
            array(array(), null, 'GB', 2, 'GB'),
            array(array(array('id' => 1, 'storeIdentifier' => 2, 'itemSortByMonthlySales' => 1, 'configuration' => array('itemSortByMonthlySales' => 15))), 1, 'GB', 2, 'AT')
        );
    }

    /**
     * @dataProvider getStandartVatProvider
     */
    public function testGetStandartVat($webstores, $configMultishopId, $configCountry, $apiCountryId, $expectedResult)
    {
        $clientMock = $this->getMockBuilder('\Findologic\Plentymarkets\Client')
            ->disableOriginalConstructor()
            ->setMethods(array('getConfig', 'getStandardVat'))
            ->getMock();

        $configMock = $this->getConfigMock();
        $configMock->expects($this->any())->method('getMultishopId')->willReturn($configMultishopId);
        $configMock->expects($this->any())->method('getCountry')->willReturn($configCountry);

        $clientMock->expects($this->any())->method('getStandardVat')->willReturn(array('countryId' => $apiCountryId));
        $clientMock->expects($this->any())->method('getConfig')->willReturn($configMock);

        $exporterMock = $this->getExporterMockBuilder(['client' => $clientMock]);
        $exporterMock->setMethods(null);
        $exporterMock = $exporterMock->getMock();
        $exporterMock->setStoresConfiguration($webstores);

        $this->assertEquals($expectedResult, $exporterMock->getStandardVatCountry());
    }

    public function getStoreConfigValueProvider()
    {
        return array(
            'No store id provided' => array(
                array(),
                null,
                'displayItemName',
                null
            ),
            'No store configuration available' => array(
                array(),
                11,
                'displayItemName',
                null
            ),
            'Get configuration value' => array(
                array(
                    array(
                        'storeIdentifier' => 11,
                        'configuration' => array(
                            'displayItemName' => '2'
                        )
                    )
                ),
                11,
                'displayItemName',
                2
            )
        );
    }

    /**
     * @dataProvider getStoreConfigValueProvider
     */
    public function testGetStoreConfigValue($storesConfiguration, $storeId, $configField, $expectedValue)
    {
        $exporterMock = $this->getExporterMockBuilder(array())
            ->setMethods(null)
            ->getMock();

        $exporterMock->setStoresConfiguration($storesConfiguration);

        $this->assertEquals($expectedValue, $exporterMock->getStoreConfigValue($storeId, $configField));
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
        $clientMock->expects($this->any())->method('getConfig')->willReturn($this->getConfigMock());
        $clientMock->expects($this->any())->method('getAttributeValues')->willReturn(array());
        $clientMock->expects($this->any())->method('getWebstores')->willReturn(array());

        $defaultMocks = array(
            'client' => $clientMock,
            'wrapper' => $this->getMockBuilder('Findologic\Plentymarkets\Wrapper\Csv')->getMock(),
            'log' => $this->getMockBuilder('\Logger')->disableOriginalConstructor()->getMock(),
            'customerLog' => $this->getMockBuilder('\Logger')->disableOriginalConstructor()->getMock(),
            'registry' => $this->getMockBuilder('Findologic\Plentymarkets\Registry')->disableOriginalConstructor()->getMock(),
        );

        $finalMocks = array_merge($defaultMocks, $mocks);

        $exporterMock = $this->getMockBuilder('Findologic\Plentymarkets\Exporter')
            ->setConstructorArgs($finalMocks);

        return $exporterMock;
    }

    /**
     * Helper function to get exporter mock
     *
     * @return \Findologic\Plentymarkets\Exporter|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getExporterMock()
    {
        $mock = $this->getExporterMockBuilder();
        $mock->setMethods(array('handleException'));
        $mock = $mock->getMock();

        return $mock;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getConfigMock()
    {
        $configMock = $this->getMockBuilder('PlentyConfig')
            ->disableOriginalConstructor()
            ->setMethods(
                array(
                    'getDomain',
                    'getUsername',
                    'getPassword',
                    'getWsdlUrl',
                    'getLanguage',
                    'getMultishopId',
                    'getAvailabilityId',
                    'getPriceId',
                    'getRrpId',
                    'getCountry'
                )
            )->getMock();

        return $configMock;
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