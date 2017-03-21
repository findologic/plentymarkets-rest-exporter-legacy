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
        $parser = ParserFactory::create('Categories');
        $this->assertInstanceOf(Categories::class, $parser);
    }

    /**
     * Test if factory throws exception if created object is not instance of ParserInterface
     */
    public function testCreateInterfaceException()
    {
        $this->setExpectedException(\Exception::class);
        $parser = ParserFactory::create('ParserFactory');
    }

    /**
     * Test if factory throws exception if given type class do not exist
     */
    public function testCreateNotExistingClass()
    {
        $this->setExpectedException(\Exception::class);
        $parser = ParserFactory::create('TestParser');
    }
}