<?php

namespace Findologic\PlentymarketsTest\Parser;

use Findologic\Plentymarkets\Parser\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AttributesTest extends TestCase
{
    /**
     * @var string
     */
    protected $defaultEmptyValue = '';

    /**
     *  Method $data property example:
     *  array (
     *      ...
     *      'entries' => array (
     *          0 => array (
     *              'id' => 1,
     *              'backendName' => 'Couch color',
     *              'position' => 1,
     *              ...
     *          )
     *      )
     *  )
     */
    public function parseProvider()
    {
        return array(
            // There was no data provided so results should be empty
            array(
                array(),
                0,
                array()
            ),
            // Empty attributes entries array provided so results should be empty
            array(
               array('entries' => array()),
               0,
               array()
            ),
            // Parsing was successful
            array(
                array(
                    'entries' => array(
                        $this->getAttributeArray('1', 'Test 1', array('Test 1')),
                        $this->getAttributeArray('2', 'Test 2', array()),
                        $this->getAttributeArray('3', 'Test 3', array())
                    )
                ),
                1,
                array(
                    '1' => $this->getAttributeResultArray('Test 1', array()),
                    '2' => $this->getAttributeResultArray('Test 2', array()),
                    '3' => $this->getAttributeResultArray('Test 3', array())
                )
            )
        );
    }

    /**
     * @dataProvider parseProvider
     */
    public function testParse($data, $attributesNamesCount, $expectedResult)
    {
        $attributesMock = $this->getAttributesMock(array('parseAttributeName'));
        $attributesMock->expects($this->exactly($attributesNamesCount))->method('parseAttributeName')->willReturn('Test 1');
        $this->assertSame($expectedResult, $attributesMock->parse($data));
    }

    /**
     *  Method $data property example:
     *  array(
     *      0 => array(
     *          'attributeId' => 1,
     *          'lang' => 'en',
     *          'name' => 'Color',
     *      ),
     *   )
     */
    public function parseAttributeNameProvider()
    {
        return array(
            // No data provider, results should be empty
            array(false, $this->defaultEmptyValue),
            // Attribute has some data but the language is not the same as in export configuration
            // so it should be skipped and results should be empty
            array(array(array('name' => 'Test', 'lang' => 'lt', 'attributeId' => '1')), $this->defaultEmptyValue),
            // Correct data provided
            array(
                array(
                    array(
                        'name' => 'Test 1',
                        'attributeId' => '1',
                        'lang' => 'en'
                    )
                ),
                'Test 1'
            )
        );
    }

    /**
     * @dataProvider parseAttributeNameProvider
     */
    public function testParseAttributeName($data, $expectedResult)
    {
        $attributesMock = $this->getAttributesMock();
        $this->assertSame($expectedResult, $attributesMock->parseAttributeName($data));
    }

    /**
     *  Method $data property example:
     *  array(
     *      ...
     *      'entries' => array(
     *          0 => array(
     *              'id' => 1,
     *              'attributeId' => 1,
     *              'backendName' => 'black',
     *              'valueNames' => array (
     *                  array(
     *                      'lang' => 'en',
     *                      'name' => 'Blacks'
     *                  )
     *              )
     *              ...
     *          )
     *      )
     *  );
     *
     */
    public function parseValuesProvider()
    {
        return array(
            'No data provided' => array(
                array(),
                array(),
                array()
            ),
            'No attribute values provided' => array(
                array(),
                array('entries' => array()),
                array()
            ),
            'Attribute values provided' => array(
                array(
                    3 => array(
                        'name' => 'Test Attribute',
                        'values' => array()
                    )
                ),
                array(
                    'entries' => array(
                        $this->getValuesArray(
                            3,
                            1,
                            'Test Value',
                            array(
                                array(
                                    'lang' => 'en',
                                    'name' => 'Test Value'
                                )
                            )
                        ),
                        $this->getValuesArray(
                            3,
                            2,
                            'Test Value',
                            array(
                                array(
                                    'lang' => 'en',
                                    'name' => 'Test Value'
                                )
                            )
                        )
                    )
                ),
                array(
                    3 => array(
                        'name' => 'Test Attribute',
                        'values' => array(
                            1 => 'Test Value',
                            2 => 'Test Value'
                        )
                    )
                )
            ),
            'No translated attribute value provided' => array(
                array(
                    3 => array(
                        'name' => 'Test Attribute',
                        'values' => array()
                    )
                ),
                array(
                    'entries' => array(
                        $this->getValuesArray(
                            3,
                            1,
                            'internalName',
                            array(
                                array(
                                    'lang' => 'de',
                                    'name' => 'Testwert'
                                )
                            )
                        )
                    )
                ),
                array(
                    3 => array(
                        'name' => 'Test Attribute',
                        'values' => array(
                            1 => 'internalName',
                        )
                    )
                )
            ),
            'No attribute value names provided' => array(
                array(
                    3 => array(
                        'name' => 'Test Attribute',
                        'values' => array()
                    )
                ),
                array(
                    'entries' => array(
                        $this->getValuesArray(
                            3,
                            1,
                            'internalName',
                            array()
                        )
                    )
                ),
                array(
                    3 => array(
                        'name' => 'Test Attribute',
                        'values' => array(
                            1 => 'internalName',
                        )
                    )
                )
            )
        );
    }

    /**
     * @dataProvider parseValuesProvider
     */
    public function testParseValues($previouslyParsedData, $data, $expectedResult)
    {
        $attributesMock = $this->getAttributesMock();
        $attributesMock->setResults($previouslyParsedData);
        $attributesMock->parseValues($data);
        $this->assertSame($expectedResult, $attributesMock->getResults());
    }

    public function parseValuesWithMultiplePagesProvider()
    {
        return array(
            array(
                array(
                    '3' => array(
                        'name' => 'Test Attribute',
                        'values' => array()
                    )
                ),
                array(
                    array(
                        'entries' => array(
                            $this->getValuesArray(
                                3,
                                1,
                                'Test Value',
                                array(
                                    array(
                                        'lang' => 'en',
                                        'name' => 'Test Value'
                                    )
                                )
                            ),
                            $this->getValuesArray(
                                3,
                                2,
                                'Test Value',
                                array(
                                    array(
                                        'lang' => 'en',
                                        'name' => 'Test Value 2'
                                    )
                                )
                            )
                        )
                    ),
                    array(
                        'entries' => array(
                            $this->getValuesArray(
                                3,
                                1,
                                'Test Value',
                                array(
                                    array(
                                        'lang' => 'en',
                                        'name' => 'Test Value 3'
                                    )
                                )
                            )
                        ),
                    )
                ),
                array(
                    '3' => array(
                        'name' => 'Test Attribute',
                        'values' => array(
                            '1' => 'Test Value',
                            '2' => 'Test Value 2'
                        )
                    )
                )
            )
        );
    }

    /**
     * @dataProvider parseValuesWithMultiplePagesProvider
     */
    public function testParseValuesWithMultiplePages($previouslyParsedData, $paginatedData, $expectedResult)
    {
        $attributesMock = $this->getAttributesMock();
        $attributesMock->setResults($previouslyParsedData);

        foreach ($paginatedData as $data) {
            $attributesMock->parseValues($data);
        }

        $this->assertSame($expectedResult, $attributesMock->getResults());
    }

    public function parseValueNameProvider()
    {
        return array(
            // No data about value provided, results should be empty
            array(
                array(),
                $this->defaultEmptyValue
            ),
            // Parsing values successful
            array(
                array(
                    array(
                        'valueId' => '1',
                        'lang' => 'en',
                        'name' => 'Test Value'
                    )
                ),
                'Test Value'
            )
        );
    }

    /**
     * @dataProvider parseValueNameProvider
     */
    public function testParseValueName($data, $expectedResult)
    {
        $attributesMock = $this->getAttributesMock();
        $this->assertSame($expectedResult, $attributesMock->parseValueName($data));
    }


    public function attributeValueExistsProvider()
    {
        return array(
            // Some attributes exist but given id not exist, values ignored
            array('1', false, array('2' => array()), false),
            // Some attributes exist and given id also exist, values ignored
            array('2', false, array('2' => array()), true),
            // Attribute exist but do not have such value
            array('1', '2', array('1' => array('values' => array('1' => array()))), false),
            // Attribute exist and value also exists
            array('1', '1', array('1' => array('values' => array('1' => array()))), true),
        );
    }

    /**
     * @dataProvider attributeValueExistsProvider
     */
    public function testAttributeValueExists($attributeId, $valueId, $attributes, $expectedResult)
    {
        $attributesMock = $this->getAttributesMock();
        $attributesMock->setResults($attributes);
        $this->assertEquals($expectedResult, $attributesMock->attributeValueExists($attributeId, $valueId));
    }

    public function getAttributeNameProvider()
    {
        return array(
            // No attributes is parsed, result should be empty
            array('1', array(), $this->defaultEmptyValue),
            // There is some attributes parsed, but given id do not exist
            array('1', array('2' => array('name' => 'Test')), $this->defaultEmptyValue),
            // Given id exist and attribute name is returned
            array('1', array('1' => array('name' => 'Test')), 'Test')
        );
    }

    /**
     * @dataProvider getAttributeNameProvider
     */
    public function testGetAttributeName($attributeId, $attributes, $expectedResult)
    {
        $attributesMock = $this->getAttributesMock();
        $attributesMock->setResults($attributes);
        $this->assertSame($expectedResult, $attributesMock->getAttributeName($attributeId));
    }

    public function getAttributeValueNameProvider()
    {
        return array(
            // no attributes and values is set, result is empty
            array(
                '1',
                '1',
                array(),
                ''
            ),
            // value and attribute set
            array(
                '1',
                '1',
                array('1' =>
                    array('values' =>
                        array(
                            '1' =>  'Test'
                        )
                    )
                )
            , 'Test')
        );
    }

    /**
     * @dataProvider getAttributeValueNameProvider
     */
    public function testGetAttributeValueName($attributeId, $valueId, $attributes, $expectedResult)
    {
        $attributesMock = $this->getAttributesMock();
        $attributesMock->setResults($attributes);
        $this->assertSame($expectedResult, $attributesMock->getAttributeValueName($attributeId, $valueId));
    }

    /* ------ helper functions ------ */

    /**
     * Helper function to minimize code lines in data provider methods
     *
     * @param string $id
     * @param string $name
     * @param array $attributeNames
     * @return array
     */
    protected function getAttributeArray($id, $name, $attributeNames = array())
    {
        $values = array(
            'id' => $id,
            'backendName' => $name,
        );

        if (!empty($attributeNames)) {
            $values['attributeNames'] = $attributeNames;
        }

        return $values;
    }

    /**
     * Helper function to minimize code lines in data provider methods
     *
     * @param $name
     * @param bool $values
     * @return array
     */
    protected function getAttributeResultArray($name, $values = false)
    {
        $data = array(
            'name' => $name
        );

        if ($values !== false) {
            $data['values'] = $values;
        }

        return $data;
    }

    /**
     * Helper function to minimize code lines in data provider methods
     *
     * @param int $attributeId
     * @param int $id
     * @param string $backendName
     * @param array $valueNames
     * @return array
     */
    protected function getValuesArray($attributeId, $id, $backendName, array $valueNames = array())
    {
        $values = array(
            'id' => $id,
            'attributeId' => $attributeId,
            'backendName' => $backendName
        );

        $values['valueNames'] = $valueNames;

        return $values;
    }

    /**
     * Helper function to construct attributes mock
     *
     * @param array $methods
     * @return Attributes|MockObject
     * @throws \ReflectionException
     */
    protected function getAttributesMock($methods = array())
    {
        // Add getters of config values to mock
        if (!in_array('getLanguageCode', $methods)) {
            $methods[] = 'getLanguageCode';
        }

        $attributesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Attributes')
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();

        $attributesMock->expects($this->any())->method('getLanguageCode')->willReturn('EN');

        return $attributesMock;
    }
}