<?php

namespace Findologic\PlentymarketsTest\Parser;

use PHPUnit_Framework_TestCase;

class StoresTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    protected $defaultEmptyValue = '';

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
        return [
            'No data provided, results should be empty' => [
                [],
                []
            ],
            'One store data provided' => [
                [
                    [
                        'id' => 0,
                        'type' => 'plentymarkets',
                        'storeIdentifier' => 31776,
                        'configuration' => [
                            'defaultLanguage' => 'de',
                            'languageList' => 'en, de'
                        ]
                    ]
                ],
                [
                    31776 => [
                        'id' => 0,
                        'defaultLanguage' => 'de',
                        'languageList' => ['en', 'de']
                    ]
                ]
            ],
        ];
    }

    /**
     * @dataProvider parseProvider
     */
    public function testParse($data, $expectedResult)
    {
        $storesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Stores')
            ->disableOriginalConstructor()
            ->setMethods(['getLanguageCode'])
            ->getMock();

        $storesMock->parse($data);
        $this->assertSame($expectedResult, $storesMock->getResults());
    }


    public function getStoreInternalIdByIdentifierProvider()
    {
        return [
            // Store by provided identifier is not found
            [
                [
                    31776 => ['id' => 0],
                    31777 => ['id' => 1],
                ],
                31744,
                ''
            ],
            // Vat rate was found
            [
                [
                    31776 => ['id' => 0],
                    31777 => ['id' => 1],
                ],
                31776,
                0
            ]
        ];
    }

    /**
     * @dataProvider getStoreInternalIdByIdentifierProvider
     */
    public function testGetStoreInternalIdByIdentifier($parsedStores, $storeIdentifier, $expectedResult)
    {
        $vatMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Stores')
            ->disableOriginalConstructor()
            ->setMethods(['getDefaultEmptyValue'])
            ->getMock();

        $vatMock->expects($this->any())->method('getDefaultEmptyValue')->willReturn($this->defaultEmptyValue);

        $vatMock->setResults($parsedStores);

        $this->assertSame($expectedResult, $vatMock->getStoreInternalIdByIdentifier($storeIdentifier));

    }
}