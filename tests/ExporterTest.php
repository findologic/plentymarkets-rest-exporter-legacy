<?php

namespace Findologic\PlentymarketsTest;

use Findologic\Plentymarkets\Client;
use Findologic\Plentymarkets\Exception\CustomerException;
use Findologic\Plentymarkets\Exception\ThrottlingException;
use Findologic\Plentymarkets\Exporter;
use Findologic\Plentymarkets\Parser\Attributes;
use Findologic\Plentymarkets\Parser\Categories;
use Findologic\Plentymarkets\Parser\SalesPrices;
use Findologic\Plentymarkets\Parser\Stores;
use Findologic\Plentymarkets\Parser\Vat;
use Findologic\Plentymarkets\Product;
use Findologic\Plentymarkets\Registry;
use Findologic\Plentymarkets\Stream\GetProductsStreamer;
use Findologic\Plentymarkets\Tests\ClientHelper;
use Findologic\Plentymarkets\Wrapper\Csv;
use Findologic\Plentymarkets\Wrapper\WrapperInterface;
use Log4Php\Logger;
use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PlentyConfig;
use ReflectionException;

class ExporterTest extends TestCase
{
    use ClientHelper;

    public function initProvider()
    {
        return [
            'Config values provided, defaultLanguage is equal to current language' => [1, 2, 3, 4, 3, 4, 'en', 'en', false, ''],
            'Config values provided, defaultLanguage is not current language; current language is not in languageList' => [1, 2, 3, 4, 3, 4, 'en', 'de', false, ''],
            'Config values missing, use store values, defaultLanguage is not current language; current language is in languageList' => [1, 2, false, false, 1, 2, 'en', 'de', true, 'en']
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
        $storesMock = $this->getMockBuilder(Stores::class)
            ->disableOriginalConstructor()
            ->setMethods(['getStoreDefaultLanguage', 'isLanguageAvailableInStore'])
            ->getMock();

        $storesMock->expects($this->any())->method('getStoreDefaultLanguage')->willReturn($storeDefaultLanguage);
        $storesMock->expects($this->any())->method('isLanguageAvailableInStore')->willReturn($isAvailable);

        $salesPricesMock = $this->getMockBuilder(SalesPrices::class)
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
         * @var $exporterMock Exporter|MockObject
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

        $this->assertInstanceOf(WrapperInterface::class, $exporterMock->getWrapper());
        $this->assertInstanceOf(Client::class, $exporterMock->getClient());
    }

    /**
     * Test if exception was thrown
     */
    public function testInitException()
    {
        $exporterMock = $this->getExporterMockBuilder();
        $exporterMock->setMethods(['initAdditionalData', 'initAttributeValues']);
        $exporterMock = $exporterMock->getMock();
        $exporterMock->expects($this->once())->method('initAdditionalData')->will($this->throwException(new CustomerException()));

        $this->expectException(CustomerException::class);

        $exporterMock->init();
    }

    /**
     * Init method should create parser objects and add those to registry
     */
    public function testInitAdditionalData()
    {
        $configMock = $this->getConfigMock();
        $logMock = $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->getMock();
        $httpClientMock = $this->getHttpClientMock(['send']);
        $clientMock = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([$configMock, $logMock, $logMock, $httpClientMock])
            ->setMethods([])
            ->getMock();

        $clientMock->expects($this->any())->method('setItemsPerPage')->willReturn($clientMock);
        $clientMock->expects($this->any())->method('getWebstores')->willReturn([]);
        // Check if category branches will be parsed
        $clientMock->expects($this->once())->method('getCategoriesBranches');

        $exporterMock = $this->getExporterMockBuilder(
            ['registry' => $this->getRegistryMock(), 'client' => $clientMock]
        );

        $exporterMock->setMethods(['initAttributeValues', 'getConfig', 'getStandardVatCountry']);
        $exporterMock = $exporterMock->getMock();
        $exporterMock->expects($this->any())->method('getConfig')->willReturn($configMock);
        $exporterMock->expects($this->any())->method('getStandardVatCountry')->willReturn('DE');

        $exporterMock->init();

        $items = ['Vat', 'Categories', 'SalesPrices', 'Attributes', 'Stores', 'Manufacturers', 'ItemProperties', 'Units'];

        foreach ($items as $item) {
            $this->assertInstanceOf('\Findologic\Plentymarkets\Parser\\' . $item, $exporterMock->getRegistry()->get($item));
        }
    }

    /**
     * Init attributes should get attributes from 'Attributes' parser and iterate over them to get attribute values
     */
    public function testInitAttributeValues()
    {
        $storesMock = $this->getMockBuilder(Stores::class)
            ->disableOriginalConstructor()
            ->setMethods(['getStoreDefaultLanguage', 'isLanguageAvailableInStore'])
            ->getMock();

        $salesPricesMock = $this->getMockBuilder(SalesPrices::class)
            ->disableOriginalConstructor()
            ->setMethods(['getDefaultPrice', 'getDefaultRrp'])
            ->getMock();

        $salesPricesMock->expects($this->any())->method('getDefaultPrice')->willReturn(1);
        $salesPricesMock->expects($this->any())->method('getDefaultRrp')->willReturn(2);

        $attributesMock = $this->getMockBuilder(Attributes::class)
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
        return [
            [
                [
                    'page' => 1,
                    'totalsCount' => 3,
                    'isLastPage' => true,
                    'lastPageNumber' => 1,
                    'entries' => [
                        ['id' => 0],
                        ['id' => 1],
                        ['id' => 2]
                    ],
                    'firstOnPage' => 0,
                    'lastOnPage' => 3,
                    'itemsPerPage' => 100
                ]
            ]
        ];
    }

    /**
     * @dataProvider providerGetProducts
     */
    public function testGetProducts($products)
    {
        $configMock = $this->getConfigMock();
        $logMock = $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->getMock();
        $httpClientMock = $this->getHttpClientMock(['send']);
        $clientMock = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([$configMock, $logMock, $logMock, $httpClientMock])
            ->setMethods(['getProducts', 'getProductVariations'])
            ->getMock();

        $clientMock->expects($this->any())->method('getProducts')->willReturn($products);

        /** @var GetProductsStreamer|MockObject $getProductsStreamerMock */
        $getProductsStreamerMock = $this->getMockBuilder(GetProductsStreamer::class)
            ->disableOriginalConstructor()
            ->setMethods(['getMetadata'])
            ->getMock();
        $getProductsStreamerMock->expects($this->once())->method('getMetadata')->willReturn([
            GetProductsStreamer::METADATA_IS_LAST_PAGE => $products['isLastPage'],
            GetProductsStreamer::METADATA_TOTALS_COUNT => $products['totalsCount']
        ]);

        /** @var Exporter|MockObject $exporterMock */
        $exporterMock = $this->getExporterMockBuilder(
            [
                'client' => $clientMock,
                'getProductsStreamer' => $getProductsStreamerMock
            ]
        );
        $exporterMock->setMethods(['createProductItem', 'processProductData']);
        $exporterMock = $exporterMock->getMock();

        $exporterMock->expects($this->any())->method('processProductData');

        $exporterMock->getProducts();
    }

    /**
     * If API returns no result an exception should be thrown
     */
    public function testGetProductsThrottlingException()
    {
        $configMock = $this->getConfigMock();
        $logMock = $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->getMock();
        $httpClientMock = $this->getHttpClientMock(['send']);
        $clientMock = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([$configMock, $logMock, $logMock, $httpClientMock])
            ->setMethods(['getProducts'])
            ->getMock();

        $clientMock->expects($this->once())->method('getProducts')->willThrowException(new ThrottlingException());

        $exporterMock = $this->getExporterMockBuilder(['client' => $clientMock, 'log' => $logMock])
            ->setMethods(['init'])
            ->getMock();

        $logMock->expects($this->once())->method('alert');

        $this->expectException(ThrottlingException::class);
        $exporterMock->getProducts();
    }

    /**
     * If API returns no result an exception should be thrown
     */
    public function testGetProductsException()
    {
        $configMock = $this->getConfigMock();
        $logMock = $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->getMock();
        $httpClientMock = $this->getHttpClientMock(['send']);
        $clientMock = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([$configMock, $logMock, $logMock, $httpClientMock])
            ->setMethods(['getProducts'])
            ->getMock();

        $clientMock->expects($this->once())->method('getProducts')->willReturn('test.json');

        /** @var GetProductsStreamer|MockObject $getProductsStreamerMock */
        $getProductsStreamerMock = $this->getMockBuilder(GetProductsStreamer::class)
            ->disableOriginalConstructor()
            ->setMethods(['getProductsIds', 'processProducts', 'getMetadata', 'isResponseValid'])
            ->getMock();
        $getProductsStreamerMock->expects($this->any())->method('getProductsIds')->willReturn([1,2]);
        $getProductsStreamerMock->expects($this->any())->method('isResponseValid')->willReturn(false);

        /** @var Exporter|MockObject $exporterMock */
        $exporterMock = $this->getExporterMockBuilder(['client' => $clientMock, 'getProductsStreamer' => $getProductsStreamerMock])
            ->setMethods(['init'])
            ->getMock();

        $this->expectException(CustomerException::class);

        $exporterMock->getProducts();
    }

    /**
     * The tested method should return instance of Product
     */
    public function testCreateProductItem()
    {
        $exporterMock = $this->getExporterMockBuilder()->setMethods(null)->getMock();

        $product = $exporterMock->createProductItem([]);
        $this->assertInstanceOf(Product::class, $product);
    }

    /**
     * Test if all methods are called to process the product
     */
    public function testProcessProductData()
    {
        $clientMock = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->setMethods(['getConfig', 'getProductVariations'])
            ->getMock();
        $clientMock->expects($this->any())->method('getConfig')->willReturn($this->getConfigMock());
        $clientMock->expects($this->any())
            ->method('getProductVariations')
            ->willReturn(
                [
                    'entries' => [
                        [
                            'id' => 'Test',
                            'itemId' => '1',
                            'isActive' => true,
                            'availability' => 1,
                            'variationCategories' => [
                                [
                                    'categoryId' => '1'
                                ]
                            ],
                            'itemImages' => [],
                            'variationProperties' => [],
                            'properties' => []
                        ],
                        [
                            'id' => 'Test',
                            'itemId' => '1',
                            'isActive' => true,
                            'availability' => 1,
                            'variationCategories' => [
                                [
                                    'categoryId' => '1'
                                ]
                            ],
                            'itemImages' => [],
                            'variationProperties' => [],
                            'properties' => []
                        ]
                    ],
                    'isLastPage' => true
                ]
            );

        /** @var GetProductsStreamer|MockObject $getProductsStreamerMock */
        $getProductsStreamerMock = $this->getMockBuilder(GetProductsStreamer::class)
            ->disableOriginalConstructor()
            ->setMethods(['getProductsIds', 'processProducts'])
            ->getMock();
        $getProductsStreamerMock->expects($this->any())->method('getProductsIds')->willReturn([1,2]);

        /** @var Exporter|MockObject $exporterMock */
        $exporterMock = $this->getExporterMockBuilder(
            [
                'client' => $clientMock,
                'getProductsStreamer' => $getProductsStreamerMock
            ]
        );
        $exporterMock->setMethods(['createProductItem']);
        $exporterMock = $exporterMock->getMock();

        $vatMock = $this->getMockBuilder(Vat::class)
            ->disableOriginalConstructor()
            ->setMethods(['getVatRateByVatId'])
            ->getMock();

        $categoriesMock = $this->getMockBuilder(Categories::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCategoryFullNamePath', 'getCategoryFullPath'])
            ->getMock();

        $categoriesMock->expects($this->any())->method('getCategoryFullNamePath')->willReturn('Test');
        $categoriesMock->expects($this->any())->method('getCategoryFullPath')->willReturn('Test');

        $registryMock = $this->getRegistryMock();
        $registryMock->set('Vat', $vatMock);
        $registryMock->set('Categories', $categoriesMock);

        $productMock = $this->getMockBuilder(Product::class)
            ->setConstructorArgs(['registry' => $registryMock])
            ->setMethods(
                [
                    'processImages',
                    'getItemId',
                    'processVariationAttributes',
                    'processVariationSpecificProperties',
                    'hasValidData',
                    'processVariation'
                ]
            )->getMock();

        $productMock->expects($this->exactly(2))->method('processVariation')->willReturn(true);
        $productMock->expects($this->exactly(2))->method('processImages');
        $productMock->expects($this->once())->method('hasValidData')->willReturn(true);
        $productMock->expects($this->any())->method('getItemId')->willReturn(1);
        $productMock->expects($this->any())->method('processVariationAttributes')->willReturn($productMock);

        $exporterMock->expects($this->once())->method('createProductItem')->willReturn($productMock);

        $exporterMock->processProductData('test.json');
    }

    public function providerProcessProductDataProductDoNotHaveData()
    {
        return [
            [
                [
                    'entries' => [
                        [
                            'id' => 'Test',
                            'isMain' => false,
                            'itemId' => '1',
                            'mainVariationId' => 'Test',
                            'isActive' => false,
                            'availability' => 1,
                            'variationCategories' => [
                                [
                                    'categoryId' => '1'
                                ]
                            ],
                            'tags' => []
                        ]
                    ],
                    'isLastPage' => true
                ]
            ],
            [
                [
                    'entries' => [
                        [
                            'id' => 'Test',
                            'isMain' => false,
                            'itemId' => '1',
                            'mainVariationId' => 'Test',
                            'isActive' => true,
                            'availability' => 1,
                            'variationCategories' => [
                                [
                                    'categoryId' => '1'
                                ]
                            ],
                            'tags' => []
                        ]
                    ],
                    'isLastPage' => true
                ]
            ]
        ];
    }

    /**
     * Test if product wrapping is skipped if product has data flag is false
     *
     * @dataProvider providerProcessProductDataProductDoNotHaveData
     */
    public function testProcessProductDataProductDoNotHaveData($productVariations)
    {
        $clientMock = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->setMethods(['getConfig', 'getProductVariations'])
            ->getMock();
        $clientMock->expects($this->any())->method('getConfig')->willReturn($this->getConfigMock());
        $clientMock->expects($this->any())->method('getProductVariations')->willReturn($productVariations);

        /** @var GetProductsStreamer|MockObject $getProductsStreamerMock */
        $getProductsStreamerMock = $this->getMockBuilder(GetProductsStreamer::class)
            ->disableOriginalConstructor()
            ->setMethods(['getProductsIds', 'processProducts'])
            ->getMock();
        $getProductsStreamerMock->expects($this->any())->method('getProductsIds')->willReturn([1,2]);

        $exporterMock = $this->getExporterMockBuilder(
            [
                'client' => $clientMock,
                'getProductsStreamer' => $getProductsStreamerMock
            ]
        );
        $exporterMock->setMethods(['createProductItem']);
        $exporterMock = $exporterMock->getMock();

        $vatMock = $this->getMockBuilder(Vat::class)
            ->disableOriginalConstructor()
            ->setMethods(['getVatRateByVatId'])
            ->getMock();

        $categoriesMock = $this->getMockBuilder(Categories::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCategoryFullNamePath', 'getCategoryFullPath'])
            ->getMock();

        $registryMock = $this->getRegistryMock();
        $registryMock->set('Vat', $vatMock);
        $registryMock->set('Categories', $categoriesMock);

        $productMock = $this->getMockBuilder(Product::class)
            ->setConstructorArgs(['registry' => $registryMock])
            ->setMethods(['processImages', 'getItemId', 'processVariationAttributes'])
            ->getMock();

        $productMock->expects($this->never())->method('processImages');
        $productMock->expects($this->any())->method('getItemId')->willReturn(1);
        $productMock->expects($this->any())->method('processVariationAttributes')->willReturn($productMock);

        $exporterMock->expects($this->once())->method('createProductItem')->willReturn($productMock);

        $this->assertEquals(null, $exporterMock->getSkippedProductsCount());
        $exporterMock->processProductData(['1' => []]);
        $this->assertEquals(1, $exporterMock->getSkippedProductsCount());
    }

    /**
     * If created product don't have an id then processing should be skipped
     */
    public function testProcessProductDataNoItem()
    {
        /** @var GetProductsStreamer|MockObject $getProductsStreamerMock */
        $getProductsStreamerMock = $this->getMockBuilder(GetProductsStreamer::class)
            ->disableOriginalConstructor()
            ->setMethods(['getProductsIds', 'processProducts'])
            ->getMock();
        $getProductsStreamerMock->expects($this->any())->method('getProductsIds')->willReturn([1,2]);

        $exporterMock = $this->getExporterMockBuilder(
            [
                'getProductsStreamer' => $getProductsStreamerMock
            ]
        );
        $exporterMock->setMethods(['createProductItem']);
        $exporterMock = $exporterMock->getMock();

        $productMock = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->setMethods(['getItemId', 'processVariation', 'processImages'])
            ->getMock();

        $productMock->expects($this->any())->method('getItemId')->will($this->returnValue(false));
        $productMock->expects($this->never())->method('processVariation');
        $productMock->expects($this->never())->method('processImages');

        $exporterMock->expects($this->any())->method('createProductItem')->will($this->returnValue($productMock));

        $exporterMock->processProductData([]);
    }

    public function getStandartVatProvider()
    {
        return [
            [
                [], null, 'GB', 2, 'GB'
            ],
            [
                [
                    [
                        'id' => 1,
                        'storeIdentifier' => 2,
                        'itemSortByMonthlySales' => 1,
                        'configuration' => [
                            'itemSortByMonthlySales' => 15
                        ]
                    ]
                ],
                1,
                'GB',
                2,
                'AT'
            ]
        ];
    }

    /**
     * @dataProvider getStandartVatProvider
     */
    public function testGetStandartVat($webstores, $configMultishopId, $configCountry, $apiCountryId, $expectedResult)
    {
        $clientMock = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->setMethods(['getConfig', 'getStandardVat'])
            ->getMock();

        $configMock = $this->getConfigMock();
        $configMock->expects($this->any())->method('getMultishopId')->willReturn($configMultishopId);
        $configMock->expects($this->any())->method('getCountry')->willReturn($configCountry);

        $clientMock->expects($this->any())->method('getStandardVat')->willReturn(['countryId' => $apiCountryId]);
        $clientMock->expects($this->any())->method('getConfig')->willReturn($configMock);

        $exporterMock = $this->getExporterMockBuilder(['client' => $clientMock]);
        $exporterMock->setMethods(null);
        $exporterMock = $exporterMock->getMock();
        $exporterMock->setStoresConfiguration($webstores);

        $this->assertEquals($expectedResult, $exporterMock->getStandardVatCountry());
    }

    public function getStoreConfigValueProvider()
    {
        return [
            'No store id provided' => [
                [],
                null,
                'displayItemName',
                null
            ],
            'No store configuration available' => [
                [],
                11,
                'displayItemName',
                null
            ],
            'Get configuration value' => [
                [
                    [
                        'storeIdentifier' => 11,
                        'configuration' => [
                            'displayItemName' => '2'
                        ]
                    ]
                ],
                11,
                'displayItemName',
                2
            ]
        ];
    }

    /**
     * @dataProvider getStoreConfigValueProvider
     */
    public function testGetStoreConfigValue($storesConfiguration, $storeId, $configField, $expectedValue)
    {
        $exporterMock = $this->getExporterMockBuilder([])
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
     * @return MockBuilder
     */
    protected function getExporterMockBuilder($mocks = [])
    {
        $clientMock = $this->getMockBuilder(Client::class)->disableOriginalConstructor()->getMock();
        $clientMock->expects($this->any())->method('setItemsPerPage')->willReturn($clientMock);
        $clientMock->expects($this->any())->method('getConfig')->willReturn($this->getConfigMock());
        $clientMock->expects($this->any())->method('getAttributeValues')->willReturn([]);
        $clientMock->expects($this->any())->method('getWebstores')->willReturn([]);

        $defaultMocks = array(
            'client' => $clientMock,
            'wrapper' => $this->getMockBuilder(Csv::class)->getMock(),
            'log' => $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->getMock(),
            'customerLog' => $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->getMock(),
            'registry' => $this->getMockBuilder(Registry::class)->disableOriginalConstructor()->getMock(),
            'getProductsStreamer' => $this->getMockBuilder(GetProductsStreamer::class)->disableOriginalConstructor()->getMock()
        );

        $finalMocks = array_merge($defaultMocks, $mocks);

        $exporterMock = $this->getMockBuilder(Exporter::class)
            ->setConstructorArgs($finalMocks);

        return $exporterMock;
    }

    /**
     * Helper function to get exporter mock
     *
     * @return Exporter|MockObject
     * @throws ReflectionException
     */
    protected function getExporterMock()
    {
        $mock = $this->getExporterMockBuilder();
        $mock->setMethods(['handleException']);
        $mock = $mock->getMock();

        return $mock;
    }

    /**
     * @return MockObject
     */
    protected function getConfigMock()
    {
        $configMock = $this->getMockBuilder(PlentyConfig::class)
            ->disableOriginalConstructor()
            ->setMethods(
                [
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
                ]
            )->getMock();

        return $configMock;
    }

    /**
     * Helper function to get registry mock
     *
     * @param array|null $methods
     * @return MockObject
     */
    protected function getRegistryMock($methods = null)
    {
        $mock = $this->getMockBuilder(Registry::class)
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();

        return $mock;
    }
}
