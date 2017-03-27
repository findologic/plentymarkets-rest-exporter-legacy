<?php

namespace Findologic\PlentymarketsTest\Parser;

use Findologic\Plentymarkets\Parser\Categories;
use PHPUnit_Framework_TestCase;

class CategoriesTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \Findologic\Plentymarkets\Parser\Categories
     */
    protected $parser;

    public function setUp()
    {
        $this->parser = new Categories();
    }

    /**
     *  Method $data property example:
     *  array (
     *      ...
     *      'entries' => array (
     *          0 => array (
     *              'id' => 16,
     *              'level' => 1,
     *              'type' => 'item',
     *              ...
     *              'details' => array(
     *                  '0' => array(
     *                      'categoryId' => '16',
     *                      'name' => 'Test'
     *                      ...
     *                  )
     *              )
     *          ),
     *      )
     *  )
     *
     */
    public function parseProvider()
    {
        return array(
            // No categories given, results should be empty
            array(array(), array()),
            // Categories data given, results should contain array with category id => name
            array(
                array(
                    'entries' => array(
                        array(
                            'details' => array(
                                array('categoryId' => '1', 'name' => 'Test')
                            )
                        )
                    )
                ),
                array('1' => 'Test')
            )
        );
    }

    /**
     * @dataProvider parseProvider
     */
    public function testParse($data, $expectedResult)
    {
        $this->parser->parse($data);
        $this->assertSame($expectedResult, $this->parser->getResults());
    }
}