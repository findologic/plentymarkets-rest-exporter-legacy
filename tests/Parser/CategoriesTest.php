<?php

namespace Findologic\PlentymarketsTest\Parser;

use Findologic\Plentymarkets\Parser\Categories;
use PHPUnit_Framework_TestCase;

class CategoriesTest extends PHPUnit_Framework_TestCase
{
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
            // Categories data given but there is no results for configured language
            array(
                array(
                    'entries' => array(
                        array(
                            'details' => array(
                                array('categoryId' => '1', 'name' => 'Test', 'lang' => 'lt')
                            )
                        )
                    )
                ),
                array()
            ),
            // Categories data given, results should contain array with category id => name
            array(
                array(
                    'entries' => array(
                        array(
                            'details' => array(
                                array('categoryId' => '1', 'name' => 'Test', 'lang' => 'en')
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
        $categoriesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Categories')
            ->disableOriginalConstructor()
            ->setMethods(array('getConfigLanguageCode'))
            ->getMock();

        $categoriesMock->expects($this->any())->method('getConfigLanguageCode')->willReturn('EN');

        $categoriesMock->parse($data);
        $this->assertSame($expectedResult, $categoriesMock->getResults());
    }
}