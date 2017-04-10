<?php

namespace Findologic\PlentymarketsTest\Parser;

use Findologic\Plentymarkets\Config;
use PHPUnit_Framework_TestCase;

class AttributesTest extends PHPUnit_Framework_TestCase
{
    protected $defaultEmptyValue = Config::DEFAULT_EMPTY_VALUE;

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
            // No data for attribute value provided, results should be empty
            array(
                array(),
                array()
            ),
            // Attribute has some values
            array(
                array(
                    'entries' => array(
                        $this->getValuesArray(
                            '3',
                            '1',
                            'Test Value',
                            array(
                                array(
                                    'lang' => 'en',
                                    'name' => 'Test Value'
                                )
                            )
                        ),
                        $this->getValuesArray(
                            '3',
                            '2',
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
                    '3' => array(
                        'values' => array(
                            '1' => 'Test Value',
                            '2' => 'Test Value'
                        )
                    )
                )
            )
        );
    }

    /**
     * @dataProvider parseValuesProvider
     */
    public function testParseValues($data, $expectedResult)
    {
        $attributesMock = $this->getAttributesMock();
        $attributesMock->parseValues($data);
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
     * @param $attributeId
     * @param $id
     * @param $backendName
     * @return array
     */
    protected function getValuesArray($attributeId, $id, $backendName, $valueNames = array())
    {
        $values = array(
            'id' => $id,
            'attributeId' => $attributeId,
            'backendName' => $backendName
        );

        if (!empty($valueNames)) {
            $values['valueNames'] = $valueNames;
        }

        return $values;
    }

    /**
     * Helper function to construct attributes mock
     *
     * @param array $methods
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getAttributesMock($methods = array())
    {
        // Add getters of config values to mock
        if (!in_array('getConfigLanguageCode', $methods)) {
            $methods[] = 'getConfigLanguageCode';
        }

        $attributesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Attributes')
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();

        $attributesMock->expects($this->any())->method('getConfigLanguageCode')->willReturn('EN');

        return $attributesMock;
    }
}