<?php

namespace Findologic\PlentymarketsTest\Parser;

use Findologic\Plentymarkets\Parser\Attributes;
use PHPUnit_Framework_TestCase;

class AttributesTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \Findologic\Plentymarkets\Parser\Attributes
     */
    protected $parser;

    public function setUp()
    {
        $this->parser = new Attributes();
    }

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
            /** There was no data provided so results should be empty */
            array(
                array(),
                array()
            ),
            /** Parsing was successful */
            array(
                array(
                    'entries' => array(
                        $this->getAttributeArray('1', 'Test 1'),
                        $this->getAttributeArray('2', 'Test 2'),
                        $this->getAttributeArray('3', 'Test 3')
                    )
                ),
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
    public function testParse($data, $expectedResult)
    {
        $this->assertSame($expectedResult, $this->parser->parse($data));
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
            /** No data provider, results should be empty */
            array(false,array()),
            /** Attribute has some data but the name value is null so it should be skipped and results should be empty */
            array(array(array('name' => '')), array()),
            /** Correct data provided */
            array(
                array(
                    array(
                        'name' => 'Test 1',
                        'attributeId' => '1'
                    )
                ),
                array(
                    '1' => $this->getAttributeResultArray('Test 1')
                )
            )
        );
    }

    /**
     * @dataProvider parseAttributeNameProvider
     */
    public function testParseAttributeName($data, $expectedResult)
    {
        $this->assertSame($expectedResult, $this->parser->parseAttributeName($data));
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
     *              ...
     *          )
     *      )
     *  );
     *
     */
    public function parseValuesProvider()
    {
        return array(
            /** No data for attribute value provided, results should be empty */
            array(
                array(),
                array()
            ),
            /** Attribute has some values */
            array(
                array(
                    'entries' => array(
                        $this->getValuesArray('3', '1', 'Test Value'),
                        $this->getValuesArray('3', '2', 'Test Value')
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
        $this->parser->parseValues($data);
        $this->assertSame($expectedResult, $this->parser->getResults());
    }

    /**
     *  Method $data property example:
     *  array (
     *      0 => array (
     *          'lang' => 'en',
     *          'valueId' => 1,
     *          'name' => 'black',
     *      ),
     *  )
     *
     */
    public function parseValueNamesProvider()
    {
        return array(
            /** No data about value provided, results should be empty */
            array(
                '1',
                array(),
                array()
            ),
            /** Parsing values successful */
            array(
                '1',
                array(
                    array(
                        'valueId' => '1',
                        'name' => 'Test Value'
                    )
                ),
                array(
                    '1' => array(
                        'values' => array(
                            '1' => 'Test Value'
                        )
                    )
                )
            )
        );
    }

    /**
     * @dataProvider parseValueNamesProvider
     */
    public function testParseValueNames($attributeId, $data, $expectedResult)
    {
        $this->parser->parseValueNames($attributeId, $data);
        $this->assertSame($expectedResult, $this->parser->getResults());
    }

    public function attributeValueExistsProvider()
    {
        return array(
            /** Some attributes exist but given id not exist, values ignored */
            array('1', false, array('2' => array()), false),
            /** Some attributes exist and given id also exist, values ignored */
            array('2', false, array('2' => array()), true),
            /** Attribute exist but do not have such value */
            array('1', '2', array('1' => array('values' => array('1' => array()))), false),
            /** Attribute exist and value also exists */
            array('1', '1', array('1' => array('values' => array('1' => array()))), true),
        );
    }

    /**
     * @dataProvider attributeValueExistsProvider
     */
    public function testAttributeValueExists($attributeId, $valueId, $attributes, $expectedResult)
    {
        $this->parser->setResults($attributes);
        $this->assertEquals($expectedResult, $this->parser->attributeValueExists($attributeId, $valueId));
    }

    public function getAttributeNameProvider()
    {
        return array(
            /** No attributes is parsed, result should be empty */
            array('1', array(), ''),
            /** There is some attributes parsed, but given id do not exist */
            array('1', array('2' => array('name' => 'Test')), ''),
            /** Given id exist and attribute name is returned */
            array('1', array('1' => array('name' => 'Test')), 'Test')
        );
    }

    /**
     * @dataProvider getAttributeNameProvider
     */
    public function testGetAttributeName($attributeId, $attributes, $expectedResult)
    {
        $this->parser->setResults($attributes);
        $this->assertSame($expectedResult, $this->parser->getAttributeName($attributeId));
    }

    public function getAttributeValueNameProvider()
    {
        return array(
            /** no attributes and values is set, result is empty */
            array(
                '1',
                '1',
                array(),
                ''
            ),
            /** value and attribute set */
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
        $this->parser->setResults($attributes);
        $this->assertSame($expectedResult, $this->parser->getAttributeValueName($attributeId, $valueId));
    }

    /* ------ helper functions ------ */

    /**
     * Helper function to minimize code lines in data provider methods
     *
     * @param $id
     * @param $name
     * @return array
     */
    protected function getAttributeArray($id, $name)
    {
        return array(
            'id' => $id,
            'backendName' => $name
        );
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
    protected function getValuesArray($attributeId, $id, $backendName)
    {
        return array(
            'id' => $id,
            'attributeId' => $attributeId,
            'backendName' => $backendName
        );
    }
}