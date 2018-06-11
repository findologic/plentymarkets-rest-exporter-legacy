<?php

namespace Findologic\PlentymarketsTest\Parser;

use Findologic\Plentymarkets\Parser\Properties;

class PropertiesTest extends \PHPUnit_Framework_TestCase
{
    protected $defaultEmptyValue = '';

    public function providerParse()
    {
        return array(
            'No item properties' => array(
                array(
                    'entries' => array(
                        array('id' => '1', 'typeIdentifier' => 'test', 'propertyGroupId' => '11', 'names' => array(), 'propertyGroups' => array())
                    )
                ),
                array()
            ),
            'Properties with translations' => array(
                array(
                    'entries' => array(
                        array('id' => '1', 'typeIdentifier' => 'item', 'propertyGroupId' => '11', 'names' => array(
                            array(
                                'lang' => 'EN',
                                'name' => 'Test EN',
                            ),
                        ),
                            'propertyGroups' => array()
                        )
                    )
                ),
                array(
                    '1' => array('propertyGroupId' => '11', 'propertyGroups' => array(), 'names' => array('EN' => 'Test EN'))
                )
            ),
            'Properties with groups' => array(
                array(
                    'entries' => array(
                        array('id' => '1', 'propertyGroupId' => '11', 'names' => array(
                                array(
                                    'lang' => 'EN',
                                    'name' => 'Test EN',
                                ),
                            ),
                            'propertyGroups' => array(),
                            'groups' => array(
                                array(
                                    'id' => '1',
                                    'names' => array(
                                        array('lang' => 'en', 'name' => 'Test Group EN')
                                    )
                                )
                            )
                        )
                    )
                ),
                array(
                    '1' => array(
                        'propertyGroupId' => '11',
                        'propertyGroups' => array('1' => array('EN' => 'Test Group EN')),
                        'names' => array('EN' => 'Test EN')
                    )
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
                        'propertyGroupId' => '11',
                        'names' => array('EN' => 'Test 2 EN')
                    )
                ),
                '1',
                'EN',
                'Test 2 EN'
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

    public function providerGetPropertyGroupName()
    {
        return array(
            'No property' => array(
                array(),
                1,
                null,
                $this->defaultEmptyValue
            ),
            'Property group id provided' => array(
                array(
                    '1' => array('propertyGroupId' => '11', 'propertyGroups' => array('1' => array('EN' => 'Test EN'))),
                ),
                '1',
                '1',
                'Test EN'
            ),
            'Property group id is not provided' => array(
                array(
                    '1' => array('propertyGroupId' => '11', 'propertyGroups' => array('1' => array('DE' => 'Test DE', 'EN' => 'Test EN'), '2' => array('EN' => 'Test 2 EN'))),
                ),
                '1',
                null,
                'Test EN'
            )
        );
    }

    /**
     * @dataProvider providerGetPropertyGroupName
     */
    public function testGetPropertyGroupName($previousParsedData, $propertyId, $groupId, $expectedResult)
    {
        /** @var Properties|\PHPUnit_Framework_MockObject_MockObject $propertiesMock */
        $propertiesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Properties')
            ->disableOriginalConstructor()
            ->setMethods(array('getDefaultEmptyValue', 'getLanguageCode'))
            ->getMock();

        $propertiesMock->expects($this->any())->method('getDefaultEmptyValue')->willReturn($this->defaultEmptyValue);
        $propertiesMock->expects($this->any())->method('getLanguageCode')->willReturn('en');
        $propertiesMock->setResults($previousParsedData);

        $this->assertEquals($expectedResult, $propertiesMock->getPropertyGroupName($propertyId, $groupId));
    }
}