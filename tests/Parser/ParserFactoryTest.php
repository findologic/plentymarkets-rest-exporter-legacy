<?php

namespace Findologic\PlentymarketsTest\Parser;

use Findologic\Plentymarkets\Parser\ParserFactory;
use Findologic\Plentymarkets\Parser\Categories;

use PHPUnit_Framework_TestCase;

class ParserFactoryTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test creation of parser by provided type
     */
    public function testCreate()
    {
        $parser = ParserFactory::create('Categories', $this->getRegistryMock());
        $this->assertInstanceOf(Categories::class, $parser);
    }

    /**
     * Test if factory throws exception if created object is not instance of ParserInterface
     */
    public function testCreateInterfaceException()
    {
        $this->setExpectedException(\Exception::class);
        $parser = ParserFactory::create('ParserFactory', $this->getRegistryMock());
    }

    /**
     * Test if factory throws exception if given type class do not exist
     */
    public function testCreateNotExistingClass()
    {
        $this->setExpectedException(\Exception::class);
        $parser = ParserFactory::create('TestParser', $this->getRegistryMock());
    }

    /**
     * Helper function to get registry mock
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getRegistryMock()
    {
        $mock = $this->getMockBuilder('\Findologic\Plentymarkets\Registry')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        return $mock;
    }
}