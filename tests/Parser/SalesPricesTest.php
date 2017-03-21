<?php

namespace Findologic\PlentymarketsTest\Parser;

use Findologic\Plentymarkets\Parser\SalesPrices;
use PHPUnit_Framework_TestCase;

class SalesPricesTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \Findologic\Plentymarkets\Parser\SalesPrices
     */
    protected $parser;

    public function setUp()
    {
        $this->parser = new SalesPrices();
    }

    public function parseProvider()
    {
        return array(
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
            ),
            array(array(), array())
        );
    }

    /**
     * @dataProvider parseProvider
     */
    public function testParse($data, $expectedResult)
    {
        $this->parser->parse($data);
        $this->assertSame($this->parser->getResults(), $expectedResult);
    }

    public function getRRPProvider()
    {
        return array(
            array(array('1' => 'default', '2' => SalesPrices::RRP_TYPE, '3' => SalesPrices::RRP_TYPE), array(2, 3))
        );
    }

    /**
     * @dataProvider getRRPProvider
     */
    public function testGetRRPProvider($salesPrices, $expectedResult)
    {
        $this->parser->setResults($salesPrices);
        $this->assertSame($this->parser->getRRP(), $expectedResult);
    }
}