<?php

namespace Findologic\PlentymarketsTest\Parser;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PropertyGroupsTest extends TestCase
{
    /**
     * @var string
     */
    protected $defaultEmptyValue = '';

    /**
     *  Method $data property example:
     *  array (
     *      ...
     *      'entries' => array (
     *          0 => array (
     *              'id' => 1,
     *              'backendName' => 'Test,
     *          ),
     *      )
     *  )
     *
     */
    public function parseProvider()
    {
        return array(
            // No property groups data given, results should be empty
            array(array(), array()),
            // Property groups data given, but name is empty
            array(
                array(
                    'entries' => array(
                        array(
                            'id' => 1,
                            'backendName' => 'Test',
                            'names' => array(
                                array('lang' => 'DE', 'name' => 'Test 2 DE'),
                                array('lang' => 'LT', 'name' => ''),
                            )
                        )
                    )
                ),
                array(1 => 'Test')
            ),
            // Property groups data given, results should contain array with property group id => name
            array(
                array(
                    'entries' => array(
                        array(
                            'id' => 1,
                            'backendName' => 'Test'
                        ),
                        array(
                            'id' => 2,
                            'backendName' => 'Test 2',
                            'names' => array(
                                array('lang' => 'DE', 'name' => 'Test 2 DE'),
                                array('lang' => 'LT', 'name' => 'Test 2 LT'),
                            )
                        )
                    )
                ),
                array(1 => 'Test', 2 => 'Test 2 LT')
            )
        );
    }

    /**
     * @dataProvider parseProvider
     */
    public function testParse($data, $expectedResult)
    {
        $propertiesGroupsMock = $this->getPropertiesGroupsMock(array('getDefaultEmptyValue', 'getLanguageCode'));

        $propertiesGroupsMock->expects($this->any())->method('getDefaultEmptyValue')->willReturn($this->defaultEmptyValue);
        $propertiesGroupsMock->expects($this->any())->method('getLanguageCode')->willReturn('LT');

        $propertiesGroupsMock->parse($data);
        $this->assertSame($expectedResult, $propertiesGroupsMock->getResults());
    }

    public function getPropertyGroupNameProvider()
    {
        return array(
            // No property groups parsed, results should be empty
            array(
                array(),
                1,
                $this->defaultEmptyValue
            ),
            // Property groups data provided but such property group is not found
            array(
                array(1 => 'Test 1', 3 => 'Test 3'),
                2,
                $this->defaultEmptyValue
            ),
            // Property group found
            array(
                array(1 => 'Test 1', 2 => 'Test 2', 3 => 'Test 3'),
                2,
                'Test 2'
            )
        );
    }

    /**
     * @dataProvider getPropertyGroupNameProvider
     */
    public function testGetPropertyGroupName($parsedPropertyGroups, $propertyGroupId, $expectedPropertyGroupName)
    {
        $propertyGroupsMock = $this->getPropertiesGroupsMock(array('getDefaultEmptyValue'));

        $propertyGroupsMock->expects($this->any())->method('getDefaultEmptyValue')->willReturn($this->defaultEmptyValue);
        $propertyGroupsMock->setResults($parsedPropertyGroups);

        $this->assertSame($expectedPropertyGroupName, $propertyGroupsMock->getPropertyGroupName($propertyGroupId));
    }

    /**
     * Helper function to avoid mock creation code duplication
     *
     * @param array $methods
     * @return MockObject
     */
    protected function getPropertiesGroupsMock($methods = array())
    {
        $propertyGroupsMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\PropertyGroups')
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();

        return $propertyGroupsMock;
    }
}