<?php

namespace Findologic\PlentymarketsTest\Parser;

use Findologic\Plentymarkets\Parser\Properties;
use PHPUnit\Framework\TestCase;

class PropertySelectionsTest extends TestCase
{
    public function providerParse()
    {
        return [
            'No selections' => [
                [
                    'entries' => []
                ],
                []
            ],
            'Selections with translations' => [
                [
                    'entries' => [
                        [
                            'id' => 1,
                            'propertyId' => 10,
                            'property' => [
                                'cast' => 'multiSelection'
                            ],
                            'relation' => [
                                'selectionRelationId' => 100,
                                'relationValues' => [
                                    [
                                        'lang' => 'EN',
                                        'value' => 'enValue1'
                                    ],
                                    [
                                        'lang' => 'DE',
                                        'value' => 'deValue1'
                                    ]
                                ]
                            ]
                        ],
                        [
                            'id' => 2,
                            'propertyId' => 10,
                            'property' => [
                                'cast' => 'multiSelection'
                            ],
                            'relation' => [
                                'selectionRelationId' => 200,
                                'relationValues' => [
                                    [
                                        'lang' => 'EN',
                                        'value' => 'enValue2'
                                    ],
                                    [
                                        'lang' => 'DE',
                                        'value' => 'deValue2'
                                    ]
                                ]
                            ]
                        ],
                        [
                            'id' => 3,
                            'propertyId' => 20,
                            'property' => [
                                'cast' => 'selection'
                            ],
                            'relation' => [
                                'selectionRelationId' => 300,
                                'relationValues' => [
                                    [
                                        'lang' => 'EN',
                                        'value' => 'enValue3'
                                    ],
                                    [
                                        'lang' => 'DE',
                                        'value' => 'deValue3'
                                    ]
                                ]
                            ]
                        ]
                    ],
                ],
                [
                    10 => [
                        'selections' => [
                            100 => [
                             'EN' => 'enValue1',
                             'DE' => 'deValue1'
                            ],
                            200 => [
                                'EN' => 'enValue2',
                                'DE' => 'deValue2'
                            ]
                        ]
                    ]
                ]
            ],
        ];
    }

    /**
     * @dataProvider providerParse
     */
    public function testParse($data, $expectedResult)
    {
        $propertiesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\PropertySelections')
            ->disableOriginalConstructor()
            ->setMethods(array('getLanguageCode'))
            ->getMock();

        $propertiesMock->expects($this->any())->method('getLanguageCode')->willReturn('EN');
        $propertiesMock->parse($data);

        $this->assertEquals($expectedResult, $propertiesMock->getResults());
    }
}
