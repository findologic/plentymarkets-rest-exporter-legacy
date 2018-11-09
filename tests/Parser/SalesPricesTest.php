<?php

namespace Findologic\PlentymarketsTest\Parser;

use Findologic\Plentymarkets\Parser\SalesPrices;
use PHPUnit_Framework_TestCase;

class SalesPricesTest extends PHPUnit_Framework_TestCase
{
    public function parseProvider()
    {
        return [
            'No data provided, results should be empty' => [[], []],
            'Sales prices data exist, data should be parsed into results' => [
                [
                    'entries' => [
                        [
                            'id' => 3,
                            'type' => 'default'
                        ],
                        [
                            'id' => 1,
                            'type' => 'default'
                        ],
                        [
                            'id' => 4,
                            'type' => 'rrp'
                        ],
                        [
                            'id' => 2,
                            'type' => 'rrp'
                        ]
                    ]
                ],
                [
                    'default' => [0 => 1, 1 => 3],
                    'rrp' => [0 => 2, 1 => 4],
                ]
            ]
        ];
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

    public function getDefaultPriceProvider()
    {
        return [
            'No default price data parsed' => [
                [],
                false
            ],
            'Default price data provided, return lowest id' => [
                ['default' => [2, 4]],
                2
            ]
        ];
    }

    /**
     * @param array $parsedData
     * @param bool|int $expectedResult
     *
     * @dataProvider getDefaultPriceProvider
     */
    public function testGetDefaultPrice(array $parsedData, $expectedResult)
    {
        $salesPricesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\SalesPrices')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $salesPricesMock->setResults($parsedData);
        $this->assertSame($expectedResult, $salesPricesMock->getDefaultPrice(), 'Default price id does not match expected value');
    }

    public function getDefaultRrpProvider()
    {
        return [
            'No rrp price data parsed' => [
                [],
                false
            ],
            'Rrp price data provided, return lowest id' => [
                ['rrp' => [1, 2]],
                1
            ]
        ];
    }

    /**
     * @param array $parsedData
     * @param bool|int $expectedResult
     *
     * @dataProvider getDefaultRrpProvider
     */
    public function testGetDefaultRrp(array $parsedData, $expectedResult)
    {
        $salesPricesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\SalesPrices')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $salesPricesMock->setResults($parsedData);
        $this->assertSame($expectedResult, $salesPricesMock->getDefaultRrp(), 'Default rrp price id does not match expected value');
    }
}