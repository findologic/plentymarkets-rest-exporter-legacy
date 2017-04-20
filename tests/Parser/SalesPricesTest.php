<?php

namespace Findologic\PlentymarketsTest\Parser;

use Findologic\Plentymarkets\Parser\SalesPrices;
use PHPUnit_Framework_TestCase;

class SalesPricesTest extends PHPUnit_Framework_TestCase
{
    public function parseProvider()
    {
        return array(
            // No data provided, results should be empty
            array(array(), array()),
            // Sales prices data exist, data should be parsed into results
            array(
                array(
                    'entries' => array(
                        array(
                            'id' => '1',
                            'type' => 'default'
                        )
                    )
                ),
                array('1' => 'default')
            )
        );
    }

    /**
     * @dataProvider parseProvider
     */
    public function testParse($data, $expectedResult)
    {
        $salesPricesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\SalesPrices')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $salesPricesMock->parse($data);
        $this->assertSame($expectedResult, $salesPricesMock->getResults());
    }

    public function getRRPProvider()
    {
        return array(
            // No prices with RRP type, results should be empty
            array(array('1' => 'default'), array()),
            // Two price has RRP type, results should contain ids of those prices
            array(array('1' => 'default', '2' => SalesPrices::RRP_TYPE, '3' => SalesPrices::RRP_TYPE), array(2, 3))
        );
    }

    /**
     * @dataProvider getRRPProvider
     */
    public function testGetRRPProvider($salesPrices, $expectedResult)
    {
        $salesPricesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\SalesPrices')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $salesPricesMock->setResults($salesPrices);
        $this->assertSame($expectedResult, $salesPricesMock->getRRP());
    }
}