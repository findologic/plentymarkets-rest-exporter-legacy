<?php

namespace Findologic\PlentymarketsTest\Parser;

use Findologic\Plentymarkets\Config;
use PHPUnit_Framework_TestCase;

class VatTest extends PHPUnit_Framework_TestCase
{
    protected $defaultEmptyValue = Config::DEFAULT_EMPTY_VALUE;

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
            ->setMethods(array('getLanguageCode'))
            ->getMock();

        $vatMock->expects($this->any())->method('getLanguageCode')->willReturn('GB');

        $vatMock->parse($data);
        $this->assertSame($expectedResult, $vatMock->getResults());
    }

    public function getVatRateByVatIdProvider()
    {
        return array(
            // Vat rate by provided id is not found
            array(
                array(
                    1 => array(
                        0 => '19',
                        1 => '17'
                    )
                ),
                3,
                ''
            ),
            // Vat rate was found
            array(
                array(
                    1 => array(
                        0 => '19',
                        1 => '17'
                    )
                ),
                0,
                '19'
            )
        );
    }

    /**
     * @dataProvider getVatRateByVatIdProvider
     */
    public function testGetVatRateByVatId($parsedVat, $vatId, $expectedResult)
    {
        $vatMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Vat')
            ->disableOriginalConstructor()
            ->setMethods(array('getDefaultEmptyValue'))
            ->getMock();

        $vatMock->expects($this->any())->method('getDefaultEmptyValue')->willReturn($this->defaultEmptyValue);

        $vatMock->setResults($parsedVat);

        $this->assertSame($expectedResult, $vatMock->getVatRateByVatId($vatId));

    }
}