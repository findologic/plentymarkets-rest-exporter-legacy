<?php

namespace Findologic\PlentymarketsTest\Parser;

use Findologic\Plentymarkets\Parser\ParserFactory;
use Findologic\Plentymarkets\Parser\Categories;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ParserFactoryTest extends TestCase
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
        $this->expectException(\Exception::class);
        $parser = ParserFactory::create('ParserFactory', $this->getRegistryMock());
    }

    /**
     * Test if factory throws exception if given type class do not exist
     */
    public function testCreateNotExistingClass()
    {
        $this->expectException(\Exception::class);
        $parser = ParserFactory::create('TestParser', $this->getRegistryMock());
    }

    /**
     * Helper function to get registry mock
     *
     * @return MockObject
     * @throws \ReflectionException
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