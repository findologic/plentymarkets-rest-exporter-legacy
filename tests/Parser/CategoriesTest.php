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
                                array('categoryId' => 1, 'name' => 'Test', 'lang' => 'lt', 'plentyId' => '1', 'nameUrl' => 'test', 'previewUrl' => 'http://example.com/test/')
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
                                array('categoryId' => 1, 'name' => 'Test 2', 'lang' => 'en', 'plentyId' => '2', 'nameUrl' => 'test-2', 'previewUrl' => 'http://example.com/test-2/'),
                                array('categoryId' => 1, 'name' => 'Test', 'lang' => 'en', 'plentyId' => '1', 'nameUrl' => 'test', 'previewUrl' => 'http://example.com/test/')
                            )
                        ),
                    )
                ),
                array(1 => array('name' => 'Test', 'urlKey' => 'test', 'fullPath' => '/test/'))
            )
        );
    }

    /**
     * @dataProvider parseProvider
     */
    public function testParse($data, $expectedResult)
    {
        $categoriesMock = $this->getCategoriesMock(array('getLanguageCode', 'getStorePlentyId'));

        $categoriesMock->expects($this->any())->method('getLanguageCode')->willReturn('EN');
        $categoriesMock->expects($this->any())->method('getStorePlentyId')->willReturn('1');

        $categoriesMock->parse($data);
        $this->assertSame($expectedResult, $categoriesMock->getResults());
    }

    public function parseCategoryFullNamesProvider()
    {
        return array(
            array(
                array(
                    'entries' => array(
                        array("categoryId" => 1, "category1Id" => 2, "category2Id" => 3, "category3Id" => null)
                    )
                ),
                array(
                    1 => array('name' => 'category'),
                    2 => array('name' => 'category1'),
                ),
                array(
                    1 => array('name' => 'category'),
                    2 => array('name' => 'category1', 'fullNamePath' => 'category1')
                )
            ),
            // Categories paths successfully parsed
            array(
                array(
                    'entries' => array(
                        array("categoryId" => 3, "category1Id" => 1, "category2Id" => 2, "category3Id" => 3)
                    )
                ),
                array(
                    1 => array('name' => 'category'),
                    2 => array('name' => 'category1'),
                    3 => array('name' => 'category2'),
                ),
                array(
                    1 => array('name' => 'category'),
                    2 => array('name' => 'category1'),
                    3 => array(
                        'name' => 'category2',
                        'fullNamePath' => 'category_category1_category2'
                    )
                )
            )
        );
    }

    /**
     * @dataProvider parseCategoryFullNamesProvider
     */
    public function testParseCategoryFullNames($data, $parsedCategories, $expectedResult)
    {
        $categoriesMock = $this->getCategoriesMock(array('parse'));

        $categoriesMock->setResults($parsedCategories);

        $this->assertSame($expectedResult, $categoriesMock->parseCategoryFullNames($data));
    }


    public function getCategoryNameProvider()
    {
        return array(
            // No categories was parsed, no data for id
            array(array(), 1, $this->defaultEmptyValue),
            // Categories data given but there is no results for this id
            array(
                array(
                    1 => array(
                        'name' => 'Test'
                    )
                ),
                2,
                $this->defaultEmptyValue
            ),
            // Categories data given, correct name should be returned
            array(
                array(
                    1 => array(
                        'name' => 'Test'
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
    public function testGetCategoryName($parsedCategories, $categoryId, $expectedResult)
    {
        $categoriesMock = $this->getCategoriesMock(array('getLanguageCode'));

        $categoriesMock->expects($this->any())->method('getLanguageCode')->willReturn('EN');

        $categoriesMock->setResults($parsedCategories);
        $this->assertSame($expectedResult, $categoriesMock->getCategoryName($categoryId));
    }

    public function getCategoryFullPathProvider()
    {
        return array(
            // No categories urls exists, results should be empty
            array(
                array(),
                1,
                $this->defaultEmptyValue,
                $this->defaultEmptyValue
            ),
            // Category paths data provided but such category path is not found
            array(
                array(1 => array('fullPath' => 'test/category'), 'fullNamePath' => 'Test_Category'),
                2,
                $this->defaultEmptyValue,
                $this->defaultEmptyValue
            ),
            // Category path found
            array(
                array(1 => array('fullPath' => 'test/category', 'fullNamePath' => 'Test_Category')),
                1,
                'test/category',
                'Test_Category'
            ),
            array(
                array(1 => array('name' => 'Category', 'urlKey' => 'category', 'fullPath' => null, 'fullNamePath' => null)),
                1,
                'category',
                'Category'
            )
        );
    }

    /**
     * @dataProvider getCategoryFullPathProvider
     */
    public function testGetCategoryFullPath($parsedUrls, $categoryId, $expectedUrlPath, $expectedCategoryPath)
    {
        $categoriesMock = $this->getCategoriesMock(array('getDefaultEmptyValue'));

        $categoriesMock->expects($this->any())->method('getDefaultEmptyValue')->willReturn($this->defaultEmptyValue);
        $categoriesMock->setResults($parsedUrls);

        $this->assertSame($expectedUrlPath, $categoriesMock->getCategoryFullPath($categoryId));
        $this->assertSame($expectedCategoryPath, $categoriesMock->getCategoryFullNamePath($categoryId));
    }

    /**
     * Helper function to avoid mock creation code duplication
     *
     * @param array $methods
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getCategoriesMock($methods = array())
    {
        $categoriesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Categories')
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();

        return $categoriesMock;
    }
}