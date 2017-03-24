<?php

namespace Findologic\PlentymarketsTest;

use Findologic\Plentymarkets\Product;
use Findologic\Plentymarkets\Registry;
use Findologic\Plentymarkets\Parser\SalesPrices;
use PHPUnit_Framework_TestCase;

class ProductTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \Findologic\Plentymarkets\Product
     */
    protected $product;

    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        $registry = new Registry();
        $this->product = new Product($registry);
    }

    public function setFieldProvider()
    {
        return array(
            array('testKey', 'testKey', 'testValue', 'testValue', false),
            array('testKey', 'getKey', 'testValue', null, false),
            array('testKey', 'testKey', 'testValue', array('testValue'), true),
        );
    }

    /**
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
            array('testKey', 'test', array('test', 'test'), true, 2),
            array('testKey', 'test', 'test', false, 2)
        );
    }

    /**
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
            array('Test Attribute', 'Test Value', array('Test Attribute' => array('Test Value')))
        );
    }

    /**
     * @dataProvider setAttributeFieldProvider
     */
    public function testSetAttributeField($attribute, $value, $expectedResult)
    {
        $this->product->setAttributeField($attribute, $value);
        $this->assertSame($expectedResult, $this->product->getField('attributes'));
    }

    public function processInitialDataProvider()
    {
        return array(
            array(
                '1',
                array(
                    'id' => '1',
                    'createdAt' => '2001-12-12 14:12:45',
                    'texts' => array(
                        array(
                            'name1' => 'Test',
                            'shortDescription' => 'Short Description',
                            'description' => 'Description',
                            'urlPath' => 'Url',
                            'keywords' => 'Keyword'
                        )
                    )
                ),
                array(
                    'name' => 'Test',
                    'summary' => 'Short Description',
                    'description' => 'Description',
                    'url' => 'Url',
                    'keywords' => 'Keyword'
                )
            ),
            array(
                '1',
                array(
                    'id' => '1',
                    'createdAt' => '2001-12-12 14:12:45'
                ),
                array()
            )
        );
    }

    /**
     * @dataProvider processInitialDataProvider
     */
    public function testProcessInitialData($itemId, $data, $texts)
    {
        $this->product->processInitialData($data);
        $this->assertSame($itemId, $this->product->getItemId());
        $this->assertSame($itemId, $this->product->getField('id'));
        foreach ($texts as $field => $value) {
            $this->assertSame($value, $this->product->getField($field));
        }
    }

    public function processVariationsProvider()
    {
        return array(
            array(
                array(),
                null,
                null,
                array('price' => null, 'maxprice' => null, 'instead' => null)
            ),
            array(
                array(
                    'entries' => array(
                        array(
                            'position' => '1',
                            'number' => 'Test Number',
                            'model' => 'Test Model',
                            'id' => 'Test Id',
                            'variationSalesPrices' => array(),
                            'variationAttributeValues' => array(
                                array(
                                    'attributeId' => '1',
                                    'valueId' => '2'
                                ),
                            ),
                            'variationBarcodes' => array()
                        )
                    )
                ),
                array('Test' => array('Test')),
                array('Test Number', 'Test Model', 'Test Id'),
                array('price' => null, 'maxprice' => null, 'instead' => null)
            ),
            array(
                array(
                    'entries' => array(
                        array(
                            'position' => '1',
                            'number' => 'Test Number',
                            'model' => 'Test Model',
                            'id' => 'Test Id',
                            'variationSalesPrices' => array(
                                array(
                                    'price' => 15,
                                    'salesPriceId' => 'default'
                                ),
                                array(
                                    'price' => 14,
                                    'salesPriceId' => 'default'
                                ),
                                array(
                                    'price' => 18,
                                    'salesPriceId' => 'default'
                                ),
                                array(
                                    'price' => 17,
                                    'salesPriceId' => SalesPrices::RRP_TYPE
                                )
                            ),
                            'variationAttributeValues' => array(),
                            'variationBarcodes' => array()
                        ),
                        array(
                            'position' => '2',
                            'number' => 'Test Number 2',
                            'model' => 'Test Model 2',
                            'id' => 'Test Id',
                            'variationSalesPrices' => array(
                                array(
                                    'price' => 15,
                                    'salesPriceId' => '1'
                                ),
                                array(
                                    'price' => 18,
                                    'salesPriceId' => '1'
                                ),
                                array(
                                    'price' => 17,
                                    'salesPriceId' => '2'
                                )
                            ),
                            'variationAttributeValues' => array(),
                            'variationBarcodes' => array(
                                array(
                                    'code' => 'Barcode'
                                )
                            )
                        )
                    )
                ),
                null,
                array('Test Number', 'Test Model', 'Test Id', 'Test Number 2', 'Test Model 2', 'Barcode'),
                array('price' => 14, 'maxprice' => 18, 'instead' => 17)

            )
        );
    }

    /**
     * @dataProvider processVariationsProvider
     */
    public function testProcessVariations($data, $expectedAttributes, $expectedIdentifiers, $expectedPrices)
    {
        $attributesMock = $this->getMockBuilder('Findologic\Plentymarkets\Parser\Attributes')
            ->setMethods(array('attributeValueExists', 'getAttributeName', 'getAttributeValueName'))
            ->getMock();

        $attributesMock->expects($this->any())->method('attributeValueExists')->willReturn(true);
        $attributesMock->expects($this->any())->method('getAttributeName')->willReturn('Test');
        $attributesMock->expects($this->any())->method('getAttributeValueName')->willReturn('Test');

        $salesPricesMock = $this->getMockBuilder('Findologic\Plentymarkets\Parser\SalesPrices')
            ->setMethods(array('getRRP'))
            ->getMock();

        $salesPricesMock->expects($this->any())->method('getRRP')->willReturn(array('2'));

        $registry = new Registry();
        $registry->set('Attributes', $attributesMock);
        $registry->set('salesPrices', $salesPricesMock);

        $productMock = $this->getMockBuilder('Findologic\Plentymarkets\Product')
            ->setMethods(array('getItemId'))
            ->setConstructorArgs(array($registry))
            ->getMock();

        $productMock->processVariations($data);
        $this->assertSame($expectedAttributes, $productMock->getField('attributes'));
        $this->assertSame($expectedIdentifiers, $productMock->getField('ordernumber'));
        foreach ($expectedPrices as $field => $price) {
            $this->assertSame($price, $productMock->getField($field));
        }
    }

    /**
     *  Method $data property example:
     *  array (
     *      0 => array (
     *          'id' => 3,
     *          'itemId' => 102,
     *          'propertyId' => 2,
     *          'variationId' => 1076,
     *          ...
     *          'property' => array (
     *              'id' => 2,
     *              'position' => 2,
     *              'unit' => 'LTR',
     *              'backendName' => 'Test Property 2',
     *              'valueType' => 'text',
     *              'isSearchable' => true,
     *              ...
     *          ),
     *          'names' => array (
     *              0 => array (
     *                  'propertyValueId' => 3,
     *                  'lang' => 'en',
     *                  'value' => 'Some Text',
     *              ),
     *          ),
     *          'propertySelection' => array (
     *              0 => array (
     *                  'id' => 1,
     *                  'propertyId' => 3,
     *                  'lang' => 'en',
     *                  'name' => 'Select 1',
     *                  'description' => 'Select 1',
     *              ),
     *          ),
     *      ),
     *  )
     */
    public function processVariationPropertiesProvider()
    {
        return array(
            array(
                array(),
                null
            )
        );
    }

    /**
     * @dataProvider processVariationPropertiesProvider
     */
    public function testProcessVariationsProperties($data, $expectedResult)
    {
        $this->product->processVariationsProperties($data);

        $this->assertSame($expectedResult, $this->product->getField('attributes'));
    }

    public function processImagesProvider()
    {
        return array(
            array(
                array(
                    array('urlMiddle' => 'path')
                ),
                'path'
            ),
            array(
                array(
                    'itemId' => '1',
                    'urlMiddle' => 'path'
                ),
                'path'
            ),
            array(
                false,
                null
            )
        );
    }

    /**
     * @dataProvider processImagesProvider
     */
    public function testProcessImages($data, $expectedResult)
    {
        $this->product->processImages($data);
        $this->assertSame($expectedResult, $this->product->getField('image'));
    }
}