<?php

namespace Findologic\PlentymarketsTest\Parser;

use PHPUnit_Framework_TestCase;

class ManufacturersTest extends PHPUnit_Framework_TestCase
{
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
                        array('id' => 1, 'name' => 'Test')
                    )
                ),
                array(1 => 'Test')
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
                ''
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