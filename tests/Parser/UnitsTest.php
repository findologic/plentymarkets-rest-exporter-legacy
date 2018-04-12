<?php

namespace Findologic\PlentymarketsTest\Parser;

use Findologic\Plentymarkets\Parser\Units;
use Findologic\Plentymarkets\Config;

class UnitsTest extends \PHPUnit_Framework_TestCase
{
    public function parseProvider()
    {
        return array(
            // No data provided, results should be empty
            array(
                array(),
                array()
            ),
            // unit data provided
            array(
                array(
                    'entries' =>
                        array(
                            array(
                                'id' => 1,
                                'unitOfMeasurement' => 'KG',
                            )
                        )
                ),
                array(1 => 'KG')
            ),
        );
    }

    /**
     * @dataProvider parseProvider
     */
    public function testParse($data, $expectedResult)
    {
        /** @var Units $unitsMock */
        $unitsMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Units')
            ->disableOriginalConstructor()
            ->setMethods(array('getLanguageCode'))
            ->getMock();

        $unitsMock->parse($data);
        $this->assertSame($expectedResult, $unitsMock->getResults());
    }

    public function getUnitValueProvider()
    {
        return array(
            array(
                1,
                'KG'
            ),
            array(
                999,
                Config::DEFAULT_EMPTY_VALUE
            ),
        );
    }

    /**
     * @dataProvider getUnitValueProvider
     */
    public function testGetUnitValue($unitId, $expectedResult)
    {
        /** @var Units $unitsMock */
        $unitsMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Units')
            ->disableOriginalConstructor()
            ->setMethods(array('getLanguageCode'))
            ->getMock();

        $unitsMock->setResults(array(1 => 'KG'));

        $this->assertSame($expectedResult, $unitsMock->getUnitValue($unitId));
    }
}