<?php

namespace Findologic\PlentymarketsTest\Parser;

use Findologic\Plentymarkets\Parser\Vat;
use PHPUnit_Framework_TestCase;

class VatTest extends PHPUnit_Framework_TestCase
{
    /**
     *  array (
     *      'entries' => array (
     *          0 =>  array (
     *              'id' => 1,
     *              'countryId' => 1,
     *              'vatRates' => array (
     *                  0 => array (
     *                      'id' => 0,
     *                      'name' => NULL,
     *                      'vatRate' => '19.00',
     *                  ),
     *              ),
     *          ),
     *      ),
     *  ),
     */
    public function parseProvider()
    {
        return array(
            // No data provided, results should be empty
            array(
                array(),
                array()
            ),
            // One country with one vat rate provided, results should have country with vat rates as array
            array(
                array(
                    'entries' => array(
                        array(
                            'id' => '1',
                            'countryId' => 1,
                            'vatRates' => array(
                                array(
                                    'id' => 0,
                                    'name' => NULL,
                                    'vatRate' => '19.00',
                                )
                            )
                        )
                    )
                ),
                array(1 => array(0 => '19.00'))
            ),
        );
    }

    /**
     * @dataProvider parseProvider
     */
    public function testParse($data, $expectedResult)
    {
        $vatMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Vat')
            ->disableOriginalConstructor()
            ->setMethods(array('getConfigLanguageCode'))
            ->getMock();

        $vatMock->expects($this->any())->method('getConfigLanguageCode')->willReturn('GB');

        $vatMock->parse($data);
        $this->assertSame($expectedResult, $vatMock->getResults());
    }
}