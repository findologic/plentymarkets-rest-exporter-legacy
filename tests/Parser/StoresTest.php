<?php

namespace Findologic\PlentymarketsTest\Parser;

use Findologic\Plentymarkets\Config;
use PHPUnit_Framework_TestCase;

class StoresTest extends PHPUnit_Framework_TestCase
{
    protected $defaultEmptyValue = Config::DEFAULT_EMPTY_VALUE;

    /**
     *  array (
     *      array (
     *          'id' => 0,
     *          'type' => 'plentymarkets',
     *          'storeIdentifier' => 31776,
     *          ...
     *      ),
     *      ...
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
            // One store data provided
            array(
                array(
                    array(
                        'id' => 0,
                        'type' => 'plentymarkets',
                        'storeIdentifier' => 31776,
                    )
                ),
                array(31776 => 0)
            ),
        );
    }

    /**
     * @dataProvider parseProvider
     */
    public function testParse($data, $expectedResult)
    {
        $storesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Stores')
            ->disableOriginalConstructor()
            ->setMethods(array('getLanguageCode'))
            ->getMock();

        $storesMock->parse($data);
        $this->assertSame($expectedResult, $storesMock->getResults());
    }


    public function getStoreInternalIdByIdentifierProvider()
    {
        return array(
            // Store by provided identifier is not found
            array(
                array(
                    31776 => 0,
                    31777 => 1,
                ),
                31744,
                ''
            ),
            // Vat rate was found
            array(
                array(
                    31776 => 0,
                    31777 => 1,
                ),
                31776,
                0
            )
        );
    }

    /**
     * @dataProvider getStoreInternalIdByIdentifierProvider
     */
    public function testGetStoreInternalIdByIdentifier($parsedStores, $storeIdentifier, $expectedResult)
    {
        $vatMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Stores')
            ->disableOriginalConstructor()
            ->setMethods(array('getDefaultEmptyValue'))
            ->getMock();

        $vatMock->expects($this->any())->method('getDefaultEmptyValue')->willReturn($this->defaultEmptyValue);

        $vatMock->setResults($parsedStores);

        $this->assertSame($expectedResult, $vatMock->getStoreInternalIdByIdentifier($storeIdentifier));

    }
}