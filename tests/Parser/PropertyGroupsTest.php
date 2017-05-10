<?php

namespace Findologic\PlentymarketsTest\Parser;

use Findologic\Plentymarkets\Config;
use PHPUnit_Framework_TestCase;

class PropertyGroupsTest extends PHPUnit_Framework_TestCase
{
    protected $defaultEmptyValue = Config::DEFAULT_EMPTY_VALUE;

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
            // Property groups data given, results should contain array with property group id => name
            array(
                array(
                    'entries' => array(
                        array(
                            'id' => 1,
                            'backendName' => 'Test'
                        )
                    )
                ),
                array(1 => 'Test')
            )
        );
    }

    /**
     * @dataProvider parseProvider
     */
    public function testParse($data, $expectedResult)
    {
        $propertiesGroupsMock = $this->getPropertiesGroupsMock(array('getDefaultEmptyValue'));

        $propertiesGroupsMock->expects($this->any())->method('getDefaultEmptyValue')->willReturn($this->defaultEmptyValue);

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
     * @return \PHPUnit_Framework_MockObject_MockObject
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