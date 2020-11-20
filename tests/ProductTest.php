<?php

namespace Findologic\PlentymarketsTest;

use Findologic\Plentymarkets\Parser\Attributes;
use Findologic\Plentymarkets\Parser\Categories;
use Findologic\Plentymarkets\Parser\ItemProperties;
use Findologic\Plentymarkets\Parser\Properties;
use Findologic\Plentymarkets\Parser\Units;
use Findologic\Plentymarkets\Parser\Vat;
use Findologic\Plentymarkets\Product;
use Findologic\Plentymarkets\Registry;
use Findologic\Plentymarkets\Tests\MockResponseHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    use MockResponseHelper;

    /**
     * @var Product
     */
    protected $product;

    protected $defaultEmptyValue = '';

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        /** @var Registry|MockObject $registry */
        $registry = $this->getRegistryMock();
        $this->product = new Product($registry);
    }

    public function setFieldProvider()
    {
        return array(
            // Some value is set but getter is called for value which is not set, results should be null
            array('testKey', 'getKey', 'testValue', $this->defaultEmptyValue, false),
            // Value set and getter returns correct results
            array('testKey', 'testKey', 'testValue', 'testValue', false),
        );
    }

    /**
     * Test setting the 'fields' array setter method
     *
     * @dataProvider setFieldProvider
     */
    public function testSetField($setKey, $getKey, $value, $expectedResult, $arrayFlag)
    {
        $this->product->setField($setKey, $value, $arrayFlag);

        $this->assertSame($expectedResult, $this->product->getField($getKey));
    }

    public function setFieldWithArrayProvider()
    {
        return array(
            // Set attribute without $array flag, result should be plain value
            array('testKey', 'test', 'test', false, 2),
            // Set attribute wit $array flag, result should contain array with values
            array('testKey', 'test', array('test', 'test'), true, 2),
        );
    }

    /**
     * Some fields can have multiple values so it can be saved as array of values
     *
     * @dataProvider setFieldWithArrayProvider
     */
    public function testSetFieldWithArray($key, $value, $expectedResult, $arrayFlag, $times)
    {
        for ($i = 0; $i < $times; $i++) {
            $this->product->setField($key, $value, $arrayFlag);
        }

        $this->assertSame($expectedResult, $this->product->getField($key));
    }

    public function setAttributeFieldProvider()
    {
        return array(
            // Set attribute with one value
            array(
                'Test Attribute',
                array('Test Value'),
                array('Test Attribute' => array('Test Value'))
            ),
            // Set attribute with multiple value
            array(
                'Test Attribute',
                array('Test Value', 'Test Value 2'),
                array('Test Attribute' => array('Test Value', 'Test Value 2'))
            ),
            // Set attribute with multiple values which have duplicates
            array(
                'Test Attribute',
                array('Test Value', 'Test Value 2', 'Test Value 2'),
                array('Test Attribute' => array('Test Value', 'Test Value 2'))
            ),
        );
    }

    /**
     * @dataProvider setAttributeFieldProvider
     */
    public function testSetAttributeField($attribute, $values, $expectedResult)
    {
        foreach ($values as $value) {
            $this->product->setAttributeField($attribute, $value);
        }

        $this->assertSame($expectedResult, $this->product->getField('attributes'));
    }

    public function getAttributeFieldProvider()
    {
        return array(
            array(
                'Test Attribute',
                'Test Attribute 2',
                array('Test Value'),
                ''
            ),
            array(
                'Test Attribute',
                'Test Attribute',
                array('Test Value'),
                array('Test Value')
            )
        );
    }

    /**
     * @dataProvider getAttributeFieldProvider
     */
    public function testGetAttributeField($setAttribute, $getAttribute, $values, $expectedResult)
    {
        foreach ($values as $value) {
            $this->product->setAttributeField($setAttribute, $value);
            $this->assertSame($expectedResult, $this->product->getAttributeField($getAttribute));
        }
    }

    /**
     * Test if passed path is not string
     */
    public function getProductFullUrlEmptyPath()
    {
        $productMock = $this->getProductMock(array('handleEmptyData'));
        $productMock->expects($this->once())->method('handleEmptyData')->willReturn('');

        $this->assertSame('', $productMock->getProductFullUrl(false));
    }

    public function getProductFullUrlProvider()
    {
        return array(
            // Trim path
            array('test.com', '/test/', 1 , 'http://test.com/test_1_1000'),
            // No trim
            array('test.com', 'test', 1, 'http://test.com/test_1_1000'),
        );
    }

    /**
     * @dataProvider getProductFullUrlProvider
     */
    public function testGetProductFullUrl($storeUrl, $path, $productId, $expectedResult)
    {
        $productMock = $this->getProductMock(array('getStoreUrl', 'getItemId'));
        $productMock->expects($this->once())->method('getStoreUrl')->willReturn($storeUrl);
        $productMock->expects($this->once())->method('getItemId')->willReturn($productId);

        $productMock->setPath($path);
        $this->assertSame($expectedResult, $productMock->getProductFullUrl(1000));
    }

    /**
     *  array (
     *      'id' => 102,
     *      'position' => 0,
     *      'manufacturerId' => 2,
     *      'createdAt' => '2017-01-01T07:47:30+01:00',
     *      'storeSpecial' => 0,
     *      'isActive' => true,
     *      'type' => 'default',
     *      ...
     *      'itemProperties' => array (
     *          ...
     *      ),
     *      'texts' => array (
     *          0 => array (
     *              'lang' => 'en',
     *              'name1' => 'Name',
     *              'name2' => '',
     *              'name3' => '',
     *              'shortDescription' => 'Description.',
     *              'metaDescription' => 'Meta description',
     *              'description' => 'Long description',
     *              'urlPath' => 'Path',
     *              'keywords' => 'Keyword'
     *          ),
     *      ),
     *  )
     */
    public function processInitialDataProvider()
    {
        return [
            // No data given, item object should not have any information
            ['', '1', [], [], ''],
            // Product initial data provided but the texts array is empty
            [
                1,
                '1',
                [
                    'id' => 1,
                    'createdAt' => '2001-12-12 14:12:45'
                ],
                [],
                ''
            ],
            // Product initial data provided but the texts data is not in language configured in export config,
            // texts should be null
            [
                1,
                '1',
                [
                    'id' => 1,
                    'createdAt' => '2001-12-12 14:12:45',
                    'texts' => [
                        [
                            'lang' => 'lt',
                            'name1' => 'Test',
                            'shortDescription' => 'Short Description',
                            'description' => 'Description',
                            'keywords' => 'Keyword'
                        ]
                    ]
                ],
                [
                    'name' => '',
                    'summary' => '',
                    'description' => '',
                    'keywords' => ''
                ],
                ''
            ],
            // Product initial data provided, item should have an id and appropriate texts fields (description, meta description, etc.)
            [
                1,
                2,
                [
                    'id' => 1,
                    'createdAt' => '2001-12-12 14:12:45',
                    'texts' => [
                        [
                            'lang' => 'en',
                            'name1' => 'Test',
                            'name2' => 'Test 2',
                            'shortDescription' => 'Short Description',
                            'description' => 'Description',
                            'keywords' => 'Keyword'
                        ]
                    ],
                    'free1' => null,
                    'free2' => '',
                    'free3' => '0',
                    'free4' => 100,
                    'free5' => 'value'
                ],
                [
                    'name' => 'Test 2',
                    'summary' => 'Short Description',
                    'description' => 'Description',
                    'keywords' => 'Keyword'
                ],
                ['free3' => ['0'], 'free4' => [100], 'free5' => ['value']]
            ]
        ];
    }

    /**
     * Test initial data parsing
     *
     * @dataProvider processInitialDataProvider
     */
    public function testProcessInitialData($itemId, $productNameId, $data, $texts, $attributes)
    {
        $productMock = $this->getProductMock();
        $productMock->setProductNameFieldId($productNameId);
        $productMock->processInitialData($data);

        $this->assertSame($attributes, $productMock->getField('attributes'));
        $this->assertSame($itemId, $productMock->getItemId());
        $this->assertSame($itemId, $productMock->getField('id'));
        foreach ($texts as $field => $value) {
            $this->assertSame($value, $productMock->getField($field));
        }
    }

    public function mergeKeywordsAndTagsProvider()
    {
        return [
            'Texts keywords is set;Tag is of type variation and is set' => [
                'initialData' => [
                    'id' => 1,
                    'createdAt' => '2001-12-12 14:12:45',
                    'texts' => [
                        [
                            'lang' => 'en',
                            'name1' => 'Test',
                            'name2' => 'Test 2',
                            'shortDescription' => 'Short Description',
                            'description' => 'Description',
                            'keywords' => 'Keyword'
                        ]
                    ]
                ],
                'variationData' => $this->getMockResponse('/pim/variations/variation_with_tags.json')['entries'][0],
                'expectedResult' => 'Keyword,I am a Tag'
            ]
        ];
    }

    /**
     * @dataProvider mergeKeywordsAndTagsProvider
     *
     * @param array $initialData
     * @param array $variationData
     * @param string $expectedResult
     * @throws \ReflectionException
     */
    public function testProcessVariationKeywordsAndTagsFromRequestAreMergedToKeywordsField(array $initialData, array $variationData, $expectedResult)
    {
        $attributesMock = $this->getMockBuilder(Attributes::class)
            ->disableOriginalConstructor()
            ->setMethods(['attributeValueExists', 'getAttributeName', 'getAttributeValueName'])
            ->getMock();

        $attributesMock->expects($this->any())->method('attributeValueExists')->willReturn(true);
        $attributesMock->expects($this->any())->method('getAttributeName')->willReturn('Test');
        $attributesMock->expects($this->any())->method('getAttributeValueName')->willReturn('Test');

        $vatMock = $this->getMockBuilder(Vat::class)
            ->disableOriginalConstructor()
            ->setMethods(['getVatRateByVatId'])
            ->getMock();

        $vatMock->expects($this->any())->method('getVatRateByVatId')->willReturn('19.00');

        $unitsMock = $this->getMockBuilder(Units::class)
            ->disableOriginalConstructor()
            ->setMethods(['getUnitValue'])
            ->getMock();

        $unitsMock->expects($this->any())->method('getUnitValue')->willReturn('C62');
        $categoriesMock = $this->getMockBuilder(Categories::class)
            ->disableOriginalConstructor()
            ->getMock();
        $categoriesMock->expects($this->any())->method('getCategoryFullNamePath')->willReturn('');

        $registry = $this->getRegistryMock();
        $registry->set('Attributes', $attributesMock);
        $registry->set('Vat', $vatMock);
        $registry->set('Units', $unitsMock);
        $registry->set('categories', $categoriesMock);

        $productMock = $this->getProductMock(['getItemId', 'getLanguageCode', 'getStorePlentyId', 'processVariationGroups'], [$registry]);
        $productMock->expects($this->any())->method('processVariationGroups')->willReturn($productMock);
        $productMock->expects($this->any())->method('getStorePlentyId')->willReturn(1);
        $productMock->setExportSalesFrequency(true);
        $productMock->setPriceId(1);
        $productMock->setRrpPriceId(4);
        $productMock->processInitialData($initialData);

        $productMock->processVariation($variationData);

        $this->assertSame($productMock->getField('keywords'), $expectedResult);
    }

    public function getManufacturerProvider()
    {
        return array(
            // Check if manufacturer is setted properly
            array(
                1,
                'Test',
                array(Product::MANUFACTURER_ATTRIBUTE_FIELD => array('Test')),
            ),
        );
    }

    /**
     * @dataProvider getManufacturerProvider
     */
    public function testProcessManufacturer($manufacturerId, $manufacturerName, $expectedResult)
    {
        $manufacturersMock = $this->getMockBuilder('Findologic\Plentymarkets\Parser\Manufacturers')
            ->disableOriginalConstructor()
            ->setMethods(array('getManufacturerName'))
            ->getMock();

        $manufacturersMock->expects($this->any())->method('getManufacturerName')->willReturn($manufacturerName);

        $registry = $this->getRegistryMock();
        $registry->set('manufacturers', $manufacturersMock);

        $productMock = $this->getProductMock(array(), array($registry));
        $productMock->processManufacturer($manufacturerId);
        $this->assertSame($productMock->getField('attributes'), $expectedResult);
    }

    public function processVariationProvider()
    {
        return [
            'No variation data provided' => [
                'data' => [],
                'expectedAttributes' => '',
                'expectedIdentifiers' => '',
                'expectedFields' => []
            ],
            // Variation attributes, units and identifiers (barcodes not included) data provided but the prices is
            // missing, second variation will be ignored as it is not active
            'Prices is missing' => [
                'data' => $this->getMockResponse('/pim/variations/variations_without_price.json')['entries'],
                'expectedAttributes' => [
                    'Test' => ['Test']
                ],
                'expectedIdentifiers' => ['Test Number', 'Test Model', 'Test Id'],
                'expectedFields' => [
                    'price' => 0.00,
                    'maxprice' => '',
                    'instead' => 0.00,
                    'base_unit' => 'C62',
                    'taxrate' => '19.00',
                    'sales_frequency' => 0,
                    'variation_id' => 'Test Id'
                ]
            ],
            // Variation prices includes price with configured sales price id and configured rrp price id
            // Variation has duplicate identifier id => 'Test Id' so it should be ignored when adding
            // to 'ordernumber' field.
            'Variation has duplicate identifier id' => [
                'data' => $this->getMockResponse('/pim/variations/variations_with_same_ids.json')['entries'],
                'expectedAttributes' => '',
                'expectedIdentifiers' => [
                    'Test Number',
                    'Test Model',
                    'Test Id',
                    'Test Number 2',
                    'Test Model 2',
                    'Barcode'
                ],
                'expectedFields' => [
                    'price' => 14,
                    'maxprice' => '',
                    'instead' => 17,
                    'variation_id' => 'Test Id',
                    'sort' => 2
                ]
            ],
            'Variation is hidden in category list' => [
                'data' =>
                    $this->getMockResponse('/pim/variations/variation_hidden_in_category_list.json')['entries'],
                'expectedAttributes' => '',
                'expectedIdentifiers' => ['Test Number', 'Test Model', 'Test Id'],
                'expectedFields' => []
            ],
            'Variation is hidden in item list' => [
                'data' =>
                    $this->getMockResponse('/pim/variations/variation_hidden_in_item_list.json')['entries'],
                'expectedAttributes' => '',
                'expectedIdentifiers' => ['Test Number', 'Test Model', 'Test Id'],
                'expectedFields' => []
            ],
            'Variation is visible in category list' => [
                'data' =>
                    $this->getMockResponse('/pim/variations/variation_visible_in_category_list.json')['entries'],
                'expectedAttributes' => '',
                'expectedIdentifiers' => ['Test Number', 'Test Model', 'Test Id'],
                'expectedFields' => []
            ],
            'Variation is visible in item list' => [
                'data' =>
                    $this->getMockResponse('/pim/variations/variation_visible_in_item_list.json')['entries'],
                'expectedAttributes' => '',
                'expectedIdentifiers' => [
                    'Test Number',
                    'Test Model',
                    'Test Id',
                    'Test Number 2',
                    'Test Model 2',
                    'Test Id 2'
                ],
                'expectedFields' => []
            ],
            'Main variation is inactive, use fallback variation ID' => [
                'data' => $this->getMockResponse('/pim/variations/main_variation_is_inactive.json')['entries'],
                'expectedAttributes' => '',
                'expectedIdentifiers' => ['Test Number', 'Test Model', 'Not the main variation'],
                'expectedFields' => [
                    'price' => 0.0,
                    'maxprice' => '',
                    'instead' => 0.0,
                    'variation_id' => 'Not the main variation',
                    'sort' => 1
                ]
            ],
            'Use the main variation ID for field, although already set' => [
                'data' => $this->getMockResponse('/pim/variations/last_variation_is_main_variation.json')['entries'],
                'expectedAttributes' => '',
                'expectedIdentifiers' => [
                    'Test Number',
                    'Test Model',
                    'Not the main variation',
                    'Test Number 2',
                    'Test Model 2',
                    'Test Id'
                ],
                'expectedFields' => [
                    'price' => 0.0,
                    'maxprice' => '',
                    'instead' => 0.0,
                    'variation_id' => 'Test Id',
                    'sort' => 2
                ]
            ],
            'Tag list is empty; field for keywords is empty' => [
                'data' =>
                    $this->getMockResponse('/pim/variations/variations_without_tags_and_keywords.json')['entries'],
                'expectedAttributes' => '',
                'expectedIdentifiers' => [
                    'Test Number',
                    'Test Model',
                    'Not the main variation',
                    'Test Number 2',
                    'Test Model 2',
                    'Test Id'
                ],
                'expectedFields' => [
                    'price' => 0.0,
                    'maxprice' => '',
                    'instead' => 0.0,
                    'variation_id' => 'Test Id',
                    'sort' => 2,
                    'keywords' => ''
                ]
            ],
            'Variations with tags; "keywords" is set with tag value and "cat_id" is set with tag id' => [
                'data' => $this->getMockResponse('/pim/variations/variations_with_tags.json')['entries'],
                'expectedAttributes' => [
                    'cat_id' => [1]
                ],
                'expectedIdentifiers' => [
                    'Test Number',
                    'Test Model',
                    'Not the main variation',
                    'Test Number 2',
                    'Test Model 2',
                    'Test Id'
                ],
                'expectedFields' => [
                    'price' => 0.0,
                    'maxprice' => '',
                    'instead' => 0.0,
                    'variation_id' => 'Test Id',
                    'sort' => 2,
                    'keywords' => 'I am a Tag'
                ]
            ],
            'Tag list contains tags for irrelevant language; field for keywords is set with main tag name' => [
                'data' =>
                    $this->getMockResponse('/pim/variations/variations_with_tags_of_another_lang.json')['entries'],
                'expectedAttributes' => [
                    'cat_id' => [1]
                ],
                'expectedIdentifiers' => [
                    'Test Number',
                    'Test Model',
                    'Not the main variation',
                    'Test Number 2',
                    'Test Model 2',
                    'Test Id'
                ],
                'expectedFields' => [
                    'price' => 0.0,
                    'maxprice' => '',
                    'instead' => 0.0,
                    'variation_id' => 'Test Id',
                    'sort' => 2,
                    'keywords' => 'Main Tag'
                ]
            ],
            'Multiple tags are set' => [
                'data' => $this->getMockResponse('/pim/variations/variation_with_multiple_tags.json')['entries'],
                'expectedAttributes' => [
                    'cat_id' => [1, 2, 5]
                ],
                'expectedIdentifiers' => [
                    'Test Number',
                    'Test Model',
                    'Not the main variation',
                    'Test Number 2',
                    'Test Model 2',
                    'Test Id'
                ],
                'expectedFields' => [
                    'price' => 0.0,
                    'maxprice' => '',
                    'instead' => 0.0,
                    'variation_id' => 'Test Id',
                    'sort' => 2,
                    'keywords' => 'Main Tag,Another Main Tag,Third Tag'
                ]
            ],
        ];
    }

    /**
     * @dataProvider processVariationProvider
     *
     * @param array $data
     * @param array|string $expectedAttributes
     * @param array|string $expectedIdentifiers
     * @param array $expectedFields
     */
    public function testProcessVariation(array $data, $expectedAttributes, $expectedIdentifiers, array $expectedFields)
    {
        $attributesMock = $this->getMockBuilder('Findologic\Plentymarkets\Parser\Attributes')
            ->disableOriginalConstructor()
            ->setMethods(array('attributeValueExists', 'getAttributeName', 'getAttributeValueName'))
            ->getMock();

        $attributesMock->expects($this->any())->method('attributeValueExists')->willReturn(true);
        $attributesMock->expects($this->any())->method('getAttributeName')->willReturn('Test');
        $attributesMock->expects($this->any())->method('getAttributeValueName')->willReturn('Test');

        $vatMock = $this->getMockBuilder('Findologic\Plentymarkets\Parser\Vat')
            ->disableOriginalConstructor()
            ->setMethods(array('getVatRateByVatId'))
            ->getMock();

        $vatMock->expects($this->any())->method('getVatRateByVatId')->willReturn('19.00');

        $unitsMock = $this->getMockBuilder('Findologic\Plentymarkets\Parser\Units')
            ->disableOriginalConstructor()
            ->setMethods(array('getUnitValue'))
            ->getMock();

        $unitsMock->expects($this->any())->method('getUnitValue')->willReturn('C62');

        $categoriesMock = $this->getMockBuilder(Categories::class)
            ->disableOriginalConstructor()
            ->getMock();
        $categoriesMock->expects($this->any())->method('getCategoryFullNamePath')->willReturn('');

        $registry = $this->getRegistryMock();
        $registry->set('Attributes', $attributesMock);
        $registry->set('Vat', $vatMock);
        $registry->set('Units', $unitsMock);
        $registry->set('categories', $categoriesMock);

        $productMock = $this->getProductMock(array('getItemId', 'getLanguageCode', 'getStorePlentyId', 'processVariationGroups'), array($registry));
        $productMock->expects($this->any())->method('processVariationGroups')->willReturn($productMock);
        $productMock->expects($this->any())->method('getStorePlentyId')->willReturn(1);
        $productMock->setExportSalesFrequency(true);
        $productMock->setPriceId(1);
        $productMock->setRrpPriceId(4);

        foreach ($data as $variation) {
            $productMock->processVariation($variation);
        }

        $this->assertSame($expectedAttributes, $productMock->getField('attributes'));
        $this->assertSame($expectedIdentifiers, $productMock->getField('ordernumber'));
        foreach ($expectedFields as $field => $expectedValue) {
            $this->assertSame($expectedValue, $productMock->getField($field), sprintf('Expected field "%s" is not identical.', $field));
        }
    }

    public function processVariationWhenVariationIsNotActiveProvider()
    {
        return [
            'Variation is not active and config is set to not include inactive variations' => [
                'data' => [
                    'base' => [
                        'isActive' => false,
                        'availability' => 1,
                    ]
                ],
                'availabilityId' => []
            ],
            'Variation is active but availability id does not match with the config' => [
                'data' => [
                    'base' => [
                        'isActive' => true,
                        'availability' => 2,
                    ]
                ],
                'availabilityId' => 2
            ],
            'Variation is no longer available' => [
                'data' => [
                    'base' => [
                        'isActive' => true,
                        'availability' => 1,
                        'availableUntil' => '2018-01-01T00:00:00+01:00'
                    ]
                ],
                'availabilityId' => 1
            ]
        ];
    }

    /**
     * Variation should be skipped if product is not active or availability ids is not in config array
     *
     * @dataProvider processVariationWhenVariationIsNotActiveProvider
     */
    public function testProcessVariationWhenVariationIsNotActive($data, $availabilityId)
    {
        $productMock = $this->getProductMock(
            [
                'processVariationIdentifiers',
                'processVariationCategories'
            ]
        );

        $productMock->setAvailabilityIds($availabilityId);

        // Further processing methods should not be called if variation do not pass visibility filtering so it could be
        // used to indicate whether it passes or not
        $productMock->expects($this->never())->method('processVariationIdentifiers');
        $productMock->expects($this->never())->method('processVariationCategories');

        $productMock->processVariation($data);
    }

    public function processVariationCategoriesProvider()
    {
        return array(
            // No data for categories provider, results should be empty
            array(
                array(),
                false,
                ''
            ),
            // Variations belongs to category, but category values is empty so attributes should also be empty
            array(
                array(array('categoryId' => 1)),
                array(
                    array('urlKey' => '', 'fullNamePath' => ''),
                ),
                ''
            ),
            // Variations belongs to two categories, categories names is saved in attributes field
            array(
                array(array('categoryId' => 1), array('categoryId' => 2)),
                array(
                    array('urlKey' => 'test', 'fullNamePath' => 'Test'),
                    array('urlKey' => 'category', 'fullNamePath' => 'Category')
                ),
                array(
                    Product::CATEGORY_ATTRIBUTE_FIELD => array('Test', 'Category'),
                    Product::CATEGORY_URLS_ATTRIBUTE_FIELD => array('test', 'category')
                )
            )
        );
    }

    /**
     * Test setting the categories attribute field
     *
     * @dataProvider processVariationCategoriesProvider
     */
    public function testProcessVariationCategories($data, $categories , $expectedResult)
    {
        $categoriesMock = $this->getMockBuilder('Findologic\Plentymarkets\Parser\Categories')
            ->disableOriginalConstructor()
            ->setMethods(array('getCategoryFullNamePath', 'getCategoryFullPath'))
            ->getMock();

        if ($categories) {
            // Mock return method for testing product with multiple categories
            $i = 0;
            foreach ($categories as $category) {
                $categoriesMock->expects($this->at($i))->method('getCategoryFullNamePath')->will($this->returnValue($category['fullNamePath']));
                $i++;
                $categoriesMock->expects($this->at($i))->method('getCategoryFullPath')->will($this->returnValue($category['urlKey']));
                $i++;
            }
        }

        $registry = $this->getRegistryMock();
        $registry->set('categories', $categoriesMock);

        $productMock = $this->getProductMock(array('handleEmptyData'), array($registry));
        $productMock->processVariationCategories($data);
        $this->assertSame($expectedResult, $productMock->getField('attributes'));
    }

    public function processVariationGroupsProvider()
    {
        return array(
            // No data for categories provider, results should be empty
            array(
                array(),
                false,
                ''
            ),
            // Variations belongs to two categories, categories names is saved in attributes field
            array(
                array(array('plentyId' => 31776), array('plentyId' => 31777)),
                array(0, 1),
                '0_,1_'
            )
        );
    }

    /**
     * Test setting the categories attribute field
     *
     * @dataProvider processVariationGroupsProvider
     */
    public function testProcessVariationGroups($data, $stores , $expectedResult)
    {
        $storesMock = $this->getMockBuilder('Findologic\Plentymarkets\Parser\Stores')
            ->disableOriginalConstructor()
            ->setMethods(array('getStoreInternalIdByIdentifier'))
            ->getMock();

        if ($stores) {
            // Mock return method for testing product with multiple stores
            $i = 0;
            foreach ($stores as $store) {
                $storesMock->expects($this->at($i))->method('getStoreInternalIdByIdentifier')->will($this->returnValue($store));
                $i++;
            }
        }

        $registry = $this->getRegistryMock();
        $registry->set('stores', $storesMock);

        $productMock = $this->getProductMock(array('handleEmptyData'), array($registry));
        $productMock->processVariationGroups($data);
        $this->assertSame($expectedResult, $productMock->getField('groups'));
    }

    public function processCharacteristicsProvider(): array
    {
        $allProperties = [
            '1' => [
                'propertyId' => 1,
                'names' => [
                    'EN' => ['name' => 'Test Property Name']
                ]
            ],
            '2' => [
                'propertyId' => 2,
                'names' => [
                    'EN' => ['name' => 'Test Property Name']
                ]
            ],
            '5' => [
                'propertyId' => 5,
                'names' => [
                    'EN' => ['name' => 'Test Property Name']
                ]
            ],
        ];

        return [
            'No characteristics' => [
                'data' => $this->getMockResponse('/item/properties/no_properties.json')['entries'],
                'assignedToProduct' => [],
                'expectedResult' => '',
            ],
            'All characteristics are not searchable' => [
                'data' => $this->getMockResponse('/item/properties/property_not_searchable.json')['entries'],
                'assignedToProduct' => $allProperties,
                'expectedResult' => ''
            ],
            'Variation has "text" type property but value is "null"' => [
                'data' => $this->getMockResponse('/item/properties/property_text_without_value.json')['entries'],
                'assignedToProduct' => [
                    [
                        'propertyId' => 1,
                        'names' => [
                            'EN' => ['name' => null]
                        ]
                    ]
                ],
                'expectedResult' => ''
            ],
            'Variation with "text" and "selection" characteristics - lang is not the same as in export config' => [
                'data' => $this->getMockResponse('/item/properties/properties_text_and_selection.json')['entries'],
                'assignedToProduct' => [
                    [
                        'propertyId' => 1,
                        'propertySelection' => [
                            [
                                'lang' => 'lt',
                                'id' => 3,
                                'name' => 'Test name',
                                'description' => '',
                                'propertyId' => 1
                            ]
                        ]
                    ],
                    [
                        'propertyId' => 2,
                        'valueTexts' => [
                            [
                                'lang' => 'lt',
                                'value' => 'Kettenlänge <40cm',
                                'valueId' => 10
                            ]
                        ]
                    ]
                ],
                'expectedResult' => ''
            ],
            'Variation with "text" and "selection" characteristics - lang is not the same as in export config but propertyGroupId is set' => [
                'data' => $this->getMockResponse('/item/properties/properties_with_group_id.json')['entries'],
                'assignedToProduct' => [
                    [
                        'propertyId' => 1,
                        'propertySelection' => [
                            [
                                'lang' => 'lt',
                                'id' => 3,
                                'name' => 'Test name',
                                'description' => '',
                                'propertyId' => 1
                            ]
                        ]
                    ],
                    [
                        'propertyId' => 2,
                        'valueTexts' => [
                            [
                                'lang' => 'lt',
                                'value' => 'Kettenlänge <40cm',
                                'valueId' => 10
                            ]
                        ]
                    ]
                ],
                'expectedResult' => ['Test' => ['Test Property Select', 'Test Property']]
            ],
            'Variation has "text" and "float" type properties' => [
                'data' => $this->getMockResponse('/item/properties/text_and_float_properties.json')['entries'],
                'assignedToProduct' => [
                    [
                        'propertyId' => 1,
                        'valueTexts' => [
                            [
                                'lang' => 'en',
                                'value' => 'Test Value',
                                'valueId' => 3,
                            ]
                        ]
                    ],
                    [
                        'propertyId' => 999,
                        'valueTexts' => [
                            [
                                'lang' => 'en',
                                'value' => 'Test Value 2',
                                'valueId' => 10
                            ]
                        ]
                    ],
                    [
                        'propertyId' => 2,
                        'valueTexts' => [],
                        'valueFloat' => 3.25
                    ]
                ],
                'expectedResult' => [
                    'Test Property' => ['Test Value'],
                    'Test Property Name' => ['Test Value 2'],
                    'Test Float' => [3.25]
                ]
            ],
            'Variation has "selection" and "int" type properties' => [
                'data' => $this->getMockResponse('/item/properties/selection_and_int_properties.json')['entries'],
                'assignedToProduct' => [
                    [
                        'propertyId' => 1,
                        'propertySelection' => [
                            [
                                'lang' => 'en',
                                'id' => 3,
                                'name' => 'Select Value',
                                'description' => '',
                                'propertyId' => 1
                            ]
                        ]
                    ],
                    [
                        'propertyId' => 2,
                        'valueTexts' => [],
                        'valueInt' => 3
                    ],
                    [
                        'propertyId' => 3,
                        'valueTexts' => []
                    ],
                    [
                        'propertyId' => 4,
                        'valueTexts' => []
                    ],
                ],
                'expectedResult' => [
                    'Test Property Select' => ['Select Value'],
                    'Test Int' => [3],
                    'Test' => ['Test Value']
                ]
            ],
            'Variation property should use name for provided language instead backend name' => [
                'data' => $this->getMockResponse('/item/properties/property_selection_with_empty_names.json')['entries'],
                'assignedToProduct' => [
                    [
                        'propertyId' => 1,
                        'valueTexts' => [
                            [
                                'lang' => 'en',
                                'value' => '',
                                'valueId' => 10
                            ]
                        ],
                        'propertySelection' => [
                            [
                                'lang' => 'en',
                                'name' => 'Test value en'
                            ]
                        ]
                    ]
                ],
                'expectedResult' => [
                    'Test' => ['Test value en']
                ]
            ],
            'Multiple properties should use name for provided language instead backend name' => [
                'data' => $this->getMockResponse('/item/properties/multiple_property_selections_with_names.json')['entries'],
                'assignedToProduct' => [
                    [
                        'propertyId' => 1,
                        'valueTexts' => [
                            [
                                'lang' => 'en',
                                'value' => 'Test EN',
                                'valueId' => 10
                            ],
                            [
                                'lang' => 'de',
                                'value' => 'Test DE',
                                'valueId' => 10
                            ],
                        ],
                        'propertySelection' => [
                            [
                                'lang' => 'en',
                                'name' => 'Test value en'
                            ],
                            [
                                'lang' => 'de',
                                'name' => 'Test value de'
                            ],
                        ]
                    ],
                    [
                        'propertyId' => 2,
                        'valueTexts' => [
                            [
                                'lang' => 'de',
                                'value' => 'Test DE',
                                'valueId' => 10
                            ],
                            [
                                'lang' => 'en',
                                'value' => 'Test',
                                'valueId' => 10
                            ]
                        ]
                    ],
                    [
                        'propertyId' => 3,
                        'valueTexts' => [
                            [
                                'lang' => 'en',
                                'value' => 'Test EN'
                            ]
                        ]
                    ]
                ],
                'expectedResult' => [
                    'Test Selection' => ['Test value en'],
                    'Test Value' => ['Test'],
                    'Test' => ['Test 2']
                ]
            ]
        ];
    }

    /**
     * @dataProvider processCharacteristicsProvider
     */
    public function testProcessCharacteristics(array $data, array $assignedToProduct, $expectedResult)
    {
        $propertyGroupsMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\PropertyGroups')
            ->disableOriginalConstructor()
            ->setMethods(array('getPropertyGroupName'))
            ->getMock();

        $propertyGroupsMock->expects($this->any())->method('getPropertyGroupName')->will($this->returnCallback(function ($gid) {
            return is_null($gid) ? '' : 'Test';
        }));

        /** @var ItemProperties|MockObject $propertiesMock */
        $propertiesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\ItemProperties')
            ->disableOriginalConstructor()
            ->setMethods(['getLanguageCode', 'getProperty'])
            ->getMock();

        $structured = [];
        foreach ($data as $property) {
            $structured[$property['id']] = $property;
        }

        $propertiesMock->expects($this->any())->method('getLanguageCode')->willReturn('EN');
        $propertiesMock->expects($this->any())->method('getProperty')->willReturnCallback(function ($id) use ($structured) {
            return $structured[$id];
        });

        /** @var Registry|MockObject $registryMock */
        $registryMock = $this->getMockBuilder('\Findologic\Plentymarkets\Registry')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $registryMock->set('PropertyGroups', $propertyGroupsMock);
        $registryMock->set('ItemProperties', $propertiesMock);

        $productMock = $this->getProductMock(['handleEmptyData'], ['registry' => $registryMock]);
        $productMock->processCharacteristics($assignedToProduct);

        $this->assertSame($expectedResult, $productMock->getField('attributes'));
    }

    public function providerProcessProperties()
    {
        return [
            'No data provided' => [
                'registryData' => [],
                'data' => [],
                'expectedResult' => ''
            ],
            'No item properties provided' => [
                'registryData' => [],
                'data' => [
                    [
                        'property' => [
                            'id' => '1',
                        ],
                        'relationTypeIdentifier' => 'Test'
                    ]
                ],
                'expectedResult' => ''
            ],
            'Empty type property' => [
                'registryData' => [
                    '1' => [
                        'names' => [
                            'EN' => 'Test Property EN'
                        ],
                        'propertyGroups' => [
                            '1' => ['DE' => 'Test DE', 'EN' => 'Test EN']
                        ]
                    ]
                ],
                'data' => [
                    [
                        'property' => [
                            'id' => '1',
                            'typeIdentifier' => 'item',
                            'cast' => 'empty'
                        ],
                        'propertyId' => '1',
                        'propertyGroups' => [
                            '1' => [
                                'DE' => 'Test DE',
                                'EN' => 'Test EN'
                            ]
                        ]
                    ]
                ],
                'expectedResult' => ['Test EN' => ['Test Property EN']],
            ],
            'Text type property' => [
                'registryData' => [
                    '1' => [
                        'names' => [
                            'EN' => 'Test Property'
                        ]
                    ]
                ],
                'data' => [
                    [
                        'property' => [
                            'id' => '1',
                            'typeIdentifier' => 'item',
                            'cast' => 'shortText',
                        ],
                        'propertyId' => '1',
                        'values' => [
                            ['lang' => 'DE', 'value' => 'Test DE'],
                            ['lang' => 'EN', 'value' => 'Test EN'],
                        ]
                    ]
                ],
                'expectedResult' => ['Test Property' => ['Test EN']],
            ],
            'Int type property' => [
                'registryData' => [
                    '1' => [
                        'names' => [
                            'EN' => 'Test Property'
                        ]
                    ]
                ],
                'data' => [
                    [
                        'property' => [
                            'id' => '1',
                            'typeIdentifier' => 'item',
                            'cast' => 'int'
                        ],
                        'propertyId' => '1',
                        'values' => [
                            ['lang' => 0, 'value' => 12],
                        ]
                    ]
                ],
                'expectedData' => ['Test Property' => [12]],
            ],
            'Selection type property' => [
                'registryData' => [
                    '1' => [
                        'names' => [
                            'EN' => 'Test Property'
                        ],
                        'selections' => [
                            '1' => [
                                'EN' => 'Selecttion Value'
                            ]
                        ]
                    ]
                ],
                'data' => [
                    [
                        'property' => [
                            'id' => '1',
                            'typeIdentifier' => 'item',
                            'cast' => 'selection',
                        ],
                        'propertyId' => '1',
                        'values' => [
                            ['value' => 1]
                        ]
                    ]
                ],
                'expectedResult' => ['Test Property' => ['Selecttion Value']],
            ],
        ];
    }

    /**
     * @dataProvider providerProcessProperties
     */
    public function testProcessProperties($registryData, $data, $expectedResult)
    {
        $propertiesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Properties')
            ->disableOriginalConstructor()
            ->setMethods(array('getLanguageCode'))
            ->getMock();

        $propertiesMock->expects($this->any())->method('getLanguageCode')->willReturn('EN');
        $propertiesMock->setResults($registryData);

        $registryMock = $this->getRegistryMock();
        $registryMock->set('Properties', $propertiesMock);

        $productMock = $this->getProductMock(array('handleEmptyData'), array('registry' => $registryMock));
        $productMock->processProperties($data);

        $this->assertSame($expectedResult, $productMock->getField('attributes'));
    }

    public function providerProcessMultiselectProperties()
    {
        return [
            'Single selection' => [
                'registryData' => [
                    10 => [
                        'names' => [
                            'EN' => 'testMultiselectProperty'
                        ]
                    ]
                ],
                'parsedSelectionsData' => [
                    10 => [
                        'selections' => [
                            100 => [
                                'EN' => 'enValue1'
                            ],
                        ]
                    ]
                ],
                'data' => [
                    [
                        'property' => [
                            'id' => '10',
                            'typeIdentifier' => 'item',
                            'cast' => 'multiSelection'
                        ],
                        'propertyId' => '10',
                        'values' => [
                            ['value' => 100]
                        ]
                    ]
                ],
                'expectedResult' => ['testMultiselectProperty' => ['enValue1']],
            ],
            'Double selection' => [
                'registryData' => [
                    10 => [
                        'names' => [
                            'EN' => 'testMultiselectProperty'
                        ]
                    ]
                ],
                'parsedSelectionsData' => [
                    10 => [
                        'selections' => [
                            100 => [
                                'EN' => 'enValue1'
                            ],
                            200 => [
                                'EN' => 'enValue2'
                            ]
                        ]
                    ]
                ],
                'data' => [
                    [
                        'property' => [
                            'id' => '10',
                            'typeIdentifier' => 'item',
                            'cast' => 'multiSelection'
                        ],
                        'propertyId' => '10',
                        'values' => [
                            ['value' => 100],
                            ['value' => 200]
                        ]
                    ]
                ],
                'expectedResult' => ['testMultiselectProperty' => ['enValue1', 'enValue2']],
            ],
            'No selection' => [
                'registryData' => [
                    10 => [
                        'names' => [
                            'EN' => 'testMultiselectProperty'
                        ]
                    ]
                ],
                'parsedSelectionsData' => [
                    11 => [
                        'selections' => [
                            100 => [
                                'EN' => 'enValue1'
                            ],
                            200 => [
                                'EN' => 'enValue2'
                            ]
                        ]
                    ]
                ],
                'data' => [
                    [
                        'property' => [
                            'id' => '10',
                            'typeIdentifier' => 'item',
                            'cast' => 'multiSelection'
                        ],
                        'propertyId' => '10',
                        'values' => []
                    ]
                ],
                'expectedResult' => '',
            ],
            'Two values - one selected' => [
                'registryData' => [
                    10 => [
                        'names' => [
                            'EN' => 'testMultiselectProperty'
                        ]
                    ]
                ],
                'parsedSelectionsData' => [
                    10 => [
                        'selections' => [
                            100 => [
                                'EN' => 'enValue1'
                            ],
                            200 => [
                                'EN' => 'enValue2'
                            ]
                        ]
                    ]
                ],
                'data' => [
                    [
                        'property' => [
                            'id' => '10',
                            'typeIdentifier' => 'item',
                            'cast' => 'multiSelection'
                        ],
                        'propertyId' => '10',
                        'values' => [
                            ['value' => 100],
                        ]
                    ]
                ],
                'expectedResult' => ['testMultiselectProperty' => ['enValue1']],
            ],
        ];
    }

    /**
     * @dataProvider providerProcessMultiselectProperties
     */
    public function testProcessMultiselectProperties($registryData, $parsedSelectionsData, $data, $expectedResult)
    {
        $propertiesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Properties')
            ->disableOriginalConstructor()
            ->setMethods(array('getLanguageCode'))
            ->getMock();

        $propertiesMock->expects($this->any())->method('getLanguageCode')->willReturn('EN');
        $propertiesMock->setResults($registryData);

        $propertySelectionsMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\PropertySelections')
            ->disableOriginalConstructor()
            ->setMethods(array('getLanguageCode'))
            ->getMock();

        $propertySelectionsMock->expects($this->any())->method('getLanguageCode')->willReturn('EN');
        $propertySelectionsMock->setResults($parsedSelectionsData);

        $registryMock = $this->getRegistryMock();
        $registryMock->set('Properties', $propertiesMock);
        $registryMock->set('PropertySelections', $propertySelectionsMock);

        $productMock = $this->getProductMock(array('handleEmptyData'), array('registry' => $registryMock));
        $productMock->processProperties($data);

        $this->assertSame($expectedResult, $productMock->getField('attributes'));
    }

    public function testDoesNotProcessVariationSpecificPropertiesWhenDataIsUnavailable()
    {
        $registryMock = $this->getMockBuilder(Registry::class)
            ->disableOriginalConstructor()
            ->setMethods(['get'])
            ->getMock();
        $registryMock->expects($this->once())->method('get')->willReturn(false);

        $productMock = $this->getProductMock(['handleEmptyData'], ['registry' => $registryMock]);
        $productMock->expects($this->once())->method('handleEmptyData')->with('Variation properties are missing');

        $productMock->processProperties(['test' => 'test']);
        $this->assertNotSame($productMock->getAttributeField('test'), 'test');
    }

    public function testDoesNotProcessVariationSpecificPropertiesWhenNoneExist()
    {
        $propertiesMock = $this->getMockBuilder(Properties::class)
            ->disableOriginalConstructor()
            ->setMethods(['getResults'])
            ->getMock();
        $propertiesMock->expects($this->once())->method('getResults')->willReturn([]);

        $registryMock = $this->getMockBuilder(Registry::class)
            ->disableOriginalConstructor()
            ->setMethods(['get'])
            ->getMock();
        $registryMock->expects($this->once())->method('get')->willReturn($propertiesMock);

        $productMock = $this->getProductMock(['handleEmptyData'], ['registry' => $registryMock]);
        $productMock->expects($this->once())->method('handleEmptyData')->with('Variation properties are missing');

        $productMock->processProperties(['test' => 'test']);
        $this->assertNotSame($productMock->getAttributeField('test'), 'test');
    }

    /**
     *  Result for this method could vary depending if product has one or multiple images
     *  If product has multiple images then $data will hold an array with images data arrays
     *  If product has one image the $data will hold that image data array
     *
     *  array (
     *      0 => array (
     *          'id' => 19,
     *          'itemId' => 102,
     *          'type' => 'internal',
     *          'fileType' => 'jpg',
     *          'url' => 'https://test.com/item/images/102/3000x3000/102-gruen.jpg',
     *          'urlMiddle' => 'https://test.com/item/images/102/370x450/102-gruen.jpg',
     *          'urlPreview' => 'https://test.com/item/images/102/150x150/102-gruen.jpg',
     *          'urlSecondPreview' => 'https://test.com/item/images/102/0x0/102-gruen.jpg',
     *          ...
     *      ),
     *   )
     */
    public function processImagesProvider()
    {
        return [
            'No image data provided' => [
                false,
                ''
            ],
            'First image is available in shop' => [
                [
                    [
                        'itemId' => '1',
                        'urlMiddle' => 'firstPath',
                        'availabilities' => [
                            [
                                'type' => Product::AVAILABILITY_STORE
                            ],
                            [
                                'type' => 'marketplace'
                            ]
                        ],
                        'position' => 0
                    ],
                    [
                        'itemId' => '2',
                        'urlMiddle' => 'secondPath',
                        'availabilities' => [
                            [
                                'type' => 'marketplace'
                            ]
                        ],
                        'position' => 1
                    ]
                ],
                'firstPath'
            ],
            'Last image is available in shop' => [
                [
                    [
                        'itemId' => '1',
                        'urlMiddle' => 'firstPath',
                        'availabilities' => [],
                        'position' => 0
                    ],
                    [
                        'itemId' => '2',
                        'urlMiddle' => 'secondPath',
                        'availabilities' => [],
                        'position' => 1
                    ],
                    [
                        'itemId' => '3',
                        'urlMiddle' => 'thirdPath',
                        'availabilities' => [
                            [
                                'type' => Product::AVAILABILITY_STORE
                            ]
                        ],
                        'position' => 2
                    ]
                ],
                'thirdPath'
            ],
            'No images are available in shop' => [
                [
                    [
                        'itemId' => '1',
                        'urlMiddle' => 'firstPath',
                        'availabilities' => []
                    ],
                    [
                        'itemId' => '2',
                        'urlMiddle' => 'secondPath',
                        'availabilities' => []
                    ],
                    [
                        'itemId' => '3',
                        'urlMiddle' => 'thirdPath',
                        'availabilities' => []
                    ]
                ],
                ''
            ],
            'Image is available in a marketplace, but not in shop' => [
                [
                    [
                        'itemId' => '1',
                        'urlMiddle' => 'firstPath',
                        'availabilities' => [
                            [
                                'imageId' => 11,
                                'type' => 'marketplace',
                                'value' => 1234
                            ]
                        ]
                    ],
                    [
                        'itemId' => '2',
                        'urlMiddle' => 'secondPath',
                        'availabilities' => [
                            [
                                'imageId' => 22,
                                'type' => 'marketplace',
                                'value' => 1234
                            ]
                        ]
                    ],
                    [
                        'itemId' => '3',
                        'urlMiddle' => 'thirdPath',
                        'availabilities' => []
                    ]
                ],
                ''
            ]
        ];
    }

    /**
     * @dataProvider processImagesProvider
     */
    public function testProcessImages($data, $expectedResult)
    {
        $productMock = $this->getProductMock();

        $productMock->processImages($data);
        $this->assertSame($expectedResult, $productMock->getField('image'));
    }

    public function testSalesFrequencyIsProperlyExported(): void
    {
        $registryMock = $this->getRegistryMock(['get', 'getVatRateByVatId']);
        $registryMock->expects($this->any())->method('get')->willReturnSelf();
        $registryMock->expects($this->any())->method('getVatRateByVatId')->willReturn(1);

        $productMock = $this->getProductMock(['getExportSalesFrequency', 'getRegistry']);
        $productMock->expects($this->once())->method('getExportSalesFrequency')->willReturn(true);
        $productMock->expects($this->any())->method('getRegistry')->willReturn($registryMock);

        $response = $this->getMockResponse('/pim/variations/variation_with_position_as_sales_frequency.json');
        $productMock->processVariation($response['entries'][0]);

        $this->assertSame(1337, $productMock->getField('sales_frequency'));
    }

    public function testUrlIsBuiltFromFirstActiveVariationIfMainVariationIsInactive(): void
    {
        $registryMock = $this->getRegistryMock(['get', 'getVatRateByVatId']);
        $registryMock->expects($this->any())->method('get')->willReturnSelf();
        $registryMock->expects($this->any())->method('getVatRateByVatId')->willReturn(1);

        $productMock = $this->getProductMock(['getExportSalesFrequency', 'getRegistry', 'getStoreUrl', 'getItemId']);
        $productMock->expects($this->once())->method('getExportSalesFrequency')->willReturn(true);
        $productMock->expects($this->any())->method('getRegistry')->willReturn($registryMock);
        $productMock->expects($this->any())->method('getStoreUrl')->willReturn('www.blub.io');
        $productMock->expects($this->any())->method('getItemId')->willReturn(69);
        $productMock->setPath('/wohnzimmer/buro');

        $response = $this->getMockResponse('/pim/variations/main_variation_inactive_other_is_active.json');
        $productMock->processVariation($response['entries'][0]);

        $this->assertEmpty($productMock->getField('url'));

        $productMock->processVariation($response['entries'][1]);

        $this->assertSame('http://www.blub.io/wohnzimmer/buro_69_1074', $productMock->getField('url'));
    }

    /**
     * @param array $methods
     * @param array|bool $constructorArgs
     * @return Product|MockObject
     */
    protected function getProductMock($methods = [], $constructorArgs = false)
    {
        // Add getters of config values to mock
        if (!in_array('getLanguageCode', $methods)) {
            $methods[] = 'getLanguageCode';
        }

        $productMock = $this->getMockBuilder('\Findologic\Plentymarkets\Product');

        if (is_array($constructorArgs)) {
            $productMock->setConstructorArgs($constructorArgs);
        } else {
            $productMock->disableOriginalConstructor();
        }

        $productMock = $productMock->setMethods($methods)->getMock();

        $productMock->expects($this->any())->method('getLanguageCode')->willReturn('EN');

        return $productMock;
    }

    /**
     * Helper function to get registry mock
     *
     * @return Registry|MockObject
     */
    protected function getRegistryMock(array $methods = null): Registry
    {
        $mock = $this->getMockBuilder('\Findologic\Plentymarkets\Registry')
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();

        return $mock;
    }
}