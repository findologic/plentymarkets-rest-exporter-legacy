<?php

namespace Findologic\PlentymarketsTest\Parser;

use Findologic\Plentymarkets\Config;
use PHPUnit_Framework_TestCase;

class CategoriesTest extends PHPUnit_Framework_TestCase
{
    protected $defaultEmptyValue = Config::DEFAULT_EMPTY_VALUE;

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
     *                      'nameUrl' => 'test'
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
                                array('categoryId' => 1, 'name' => 'Test', 'lang' => 'lt', 'nameUrl' => 'test')
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
                                array('categoryId' => 1, 'name' => 'Test', 'lang' => 'en', 'nameUrl' => 'test')
                            )
                        )
                    )
                ),
                array(1 => array('name' => 'Test', 'url' => 'test'))
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

    public function parseCategoryFullUrlsProvider()
    {
        return array(
            array(
                array(
                    'entries' => array(
                        array("categoryId" => 1, "category1Id" => 2, "category2Id" => 3, "category3Id" => null)
                    )
                ),
                array('category', 'category1', 'category2'),
                array(3 => '/category/category1/category2/')
            )
        );
    }

    /**
     * @dataProvider parseCategoryFullUrlsProvider
     */
    public function testParseCategoryFullUrls($data, $urls, $expectedResult)
    {
        $categoriesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Categories')
            ->setMethods(array('getCategoryUrlKey'))
            ->getMock();

        $i = 0;
        foreach ($urls as $url) {
            $categoriesMock->expects($this->at($i))->method('getCategoryUrlKey')->willReturn($url);
            $i++;
        }

        $this->assertSame($expectedResult, $categoriesMock->parseCategoryFullUrls($data));
    }


    public function getCategoryNameProvider()
    {
        return array(
            // No categories was parsed, no data for id
            array(array(), 1, $this->defaultEmptyValue),
            // Categories data given but there is no results for configured language
            array(
                array(
                    'entries' => array(
                        array(
                            'details' => array(
                                array('categoryId' => 1, 'name' => 'Test', 'lang' => 'lt', 'nameUrl' => 'test')
                            )
                        )
                    )
                ),
                2,
                $this->defaultEmptyValue
            ),
            // Categories data given, results should contain array with category id => name
            array(
                array(
                    'entries' => array(
                        array(
                            'details' => array(
                                array('categoryId' => 1, 'name' => 'Test', 'lang' => 'en', 'nameUrl' => 'test')
                            )
                        )
                    )
                ),
                1,
                'Test'
            )
        );
    }

    /**
     * @dataProvider getCategoryNameProvider
     */
    public function testGetCategoryName($data, $categoryId, $expectedResult)
    {
        $categoriesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Categories')
            ->disableOriginalConstructor()
            ->setMethods(array('getConfigLanguageCode'))
            ->getMock();

        $categoriesMock->expects($this->any())->method('getConfigLanguageCode')->willReturn('EN');

        $categoriesMock->parse($data);
        $this->assertSame($expectedResult, $categoriesMock->getCategoryName($categoryId));
    }
}