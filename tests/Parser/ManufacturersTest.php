<?php

namespace Findologic\PlentymarketsTest\Parser;

use PHPUnit\Framework\TestCase;

class ManufacturersTest extends TestCase
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
     *              'logo' => '',
     *              'url' => '',
     *              'name' => 'Test'
     *              ...
     *          ),
     *      )
     *  )
     *
     */
    public function parseProvider()
    {
        return array(
            // No manufacturers given, results should be empty
            array(array(), array()),
            // Manufacturers data given, results should contain array with manufacturer id => name
            array(
                array(
                    'entries' => array(
                        array('id' => 1, 'name' => 'Test'),
                        array('id' => 2, 'name' => 'Test 2', 'externalName' => 'Test 2 E')
                    )
                ),
                array(1 => 'Test', 2 => 'Test 2 E')
            )
        );
    }

    /**
     * @dataProvider parseProvider
     */
    public function testParse($data, $expectedResult)
    {
        $manufacturersMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Manufacturers')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $manufacturersMock->parse($data);
        $this->assertSame($expectedResult, $manufacturersMock->getResults());
    }

    public function getManufacturerNameProvider()
    {
        return array(
            array(
                array(
                    'entries' => array(
                        array('id' => 1, 'name' => 'Test')
                    )
                ),
                2,
                $this->defaultEmptyValue
            ),
            array(
                array(
                    'entries' => array(
                        array('id' => 1, 'name' => 'Test')
                    )
                ),
                1,
                'Test'
            ),
        );
    }

    /**
     * @dataProvider getManufacturerNameProvider
     */
    public function testGetManufacturerName($data, $manufacturerId, $expectedResult)
    {
        $manufacturersMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Manufacturers')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $manufacturersMock->parse($data);
        $this->assertSame($expectedResult, $manufacturersMock->getManufacturerName($manufacturerId));
    }
}