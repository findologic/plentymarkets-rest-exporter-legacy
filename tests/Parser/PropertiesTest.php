<?php

namespace Findologic\PlentymarketsTest\Parser;

class PropertiesTest extends \PHPUnit_Framework_TestCase
{
    public function providerParse()
    {
        return array(
            array(
                array(
                    'entries' => array(
                        array('id' => '32', 'backendName' => 'Test', 'propertyGroupId' => '12'),
                    )
                ),
                array(
                    '32' => array('backendName' => 'Test', 'propertyGroupId' => '12')
                )
            ),
            array(
                array(
                    'entries' => array(
                        array('id' => '30', 'backendName' => 'Test', 'propertyGroupId' => '12'),
                        array('id' => '32', 'backendName' => 'Test 2', 'propertyGroupId' => '11', 'names' => array(
                            array(
                                'lang' => 'EN',
                                'name' => 'Test 2 EN',
                                'description' => 'Test 2 Description'
                            )
                        ))
                    )
                ),
                array(
                    '30' => array('backendName' => 'Test', 'propertyGroupId' => '12'),
                    '32' => array('backendName' => 'Test 2', 'propertyGroupId' => '11', 'names' => array(
                        'EN' => array('name' => 'Test 2 EN', 'description' => 'Test 2 Description')
                    ))
                )
            )
        );
    }

    /**
     * @dataProvider providerParse
     */
    public function testParse($data, $expectedResult)
    {
        $propertiesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Properties')
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
        $propertiesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Properties')
            ->disableOriginalConstructor()
            ->setMethods(array('getLanguageCode'))
            ->getMock();

        $propertiesMock->expects($this->atLeastOnce())->method('getLanguageCode')->willReturn($languageCode);
        $propertiesMock->setResults($results);

        $this->assertEquals($expectedResults, $propertiesMock->getPropertyName($propertyId));
    }
}