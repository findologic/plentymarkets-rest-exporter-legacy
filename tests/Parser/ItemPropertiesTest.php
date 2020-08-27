<?php

namespace Findologic\PlentymarketsTest\Parser;

use Findologic\Plentymarkets\Tests\MockResponseHelper;
use PHPUnit\Framework\TestCase;

class ItemPropertiesTest extends TestCase
{
    use MockResponseHelper;

    protected $defaultEmptyValue = '';

    public function providerParse()
    {
        return [
            'Properties without translations' => [
                'data' => $this->getMockResponse('/item/properties/properties_without_translations.json'),
                'expectedResult' => [
                    '32' => [
                        'backendName' => 'Test',
                        'propertyGroupId' => '12',
                        'propertyGroups' => [],
                        'id' => '32',
                        'isSearchable' => true,
                        'valueType' => 'text',
                        'selections' => [],
                        'valueInt' => null,
                        'valueFloat' => null
                    ]
                ]
            ],
            'Properties with translations' => [
                'data' => $this->getMockResponse('/item/properties/properties_with_translations.json'),
                'expectedResult' => [
                    '30' => [
                        'backendName' => 'Test',
                        'propertyGroupId' => '12',
                        'propertyGroups' => [],
                        'id' => '30',
                        'isSearchable' => true,
                        'valueType' => 'text',
                        'selections' => [],
                        'valueInt' => null,
                        'valueFloat' => null,
                    ],
                    '32' => [
                        'backendName' => 'Test 2',
                        'propertyGroupId' => '11',
                        'names' => [
                            'EN' => [
                                'name' => 'Test 2 EN',
                                'description' => 'Test 2 Description'
                            ]
                        ],
                        'propertyGroups' => [],
                        'id' => '32',
                        'isSearchable' => true,
                        'valueType' => 'text',
                        'selections' => [],
                        'valueInt' => null,
                        'valueFloat' => null
                    ]
                ]
            ]
        ];
    }

    /**
     * @dataProvider providerParse
     */
    public function testParse($data, $expectedResult)
    {
        $propertiesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\ItemProperties')
            ->disableOriginalConstructor()
            ->setMethods(array('getLanguageCode'))
            ->getMock();

        $propertiesMock->expects($this->any())->method('getLanguageCode')->willReturn('EN');
        $propertiesMock->parse($data);

        $this->assertEquals($expectedResult, $propertiesMock->getResults());
    }

    public function providerGetPropertyName()
    {
        return array(
            array(
                array(),
                '1',
                'EN',
                ''
            ),
            array(
                array(
                    '1' => array(
                        'backendName' => 'Test 2',
                        'propertyGroupId' => '11',
                        'names' => array(
                            'EN' => array('name' => 'Test 2 EN', 'description' => 'Test 2 Description')
                        )
                    )
                ),
                '1',
                'EN',
                'Test 2 EN'
            ),
            array(
                array(
                    '1' => array(
                        'backendName' => 'Test 2',
                        'propertyGroupId' => '11',
                        'names' => array(
                            'EN' => array('name' => 'Test 2 EN', 'description' => 'Test 2 Description')
                        )
                    )
                ),
                '1',
                'LT',
                'Test 2'
            )
        );
    }

    /**
     * @dataProvider providerGetPropertyName
     */
    public function testGetPropertyName($results, $propertyId, $languageCode, $expectedResults)
    {
        $propertiesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\ItemProperties')
            ->disableOriginalConstructor()
            ->setMethods(array('getLanguageCode'))
            ->getMock();

        $propertiesMock->expects($this->atLeastOnce())->method('getLanguageCode')->willReturn($languageCode);
        $propertiesMock->setResults($results);

        $this->assertEquals($expectedResults, $propertiesMock->getPropertyName($propertyId));
    }
}