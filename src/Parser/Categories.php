<?php

namespace Findologic\Plentymarkets\Parser;

use Findologic\Plentymarkets\Config;

class Categories extends ParserAbstract implements ParserInterface
{
    protected $fullUrls = array();

    /**
     * Method for allowing to mock and test the url logic
     *
     * @codeCoverageIgnore
     * @param array $fullUrls
     * @return $this
     */
    public function setFullUrls($fullUrls)
    {
        $this->fullUrls = $fullUrls;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function parse($data)
    {
        if (!isset($data['entries'])) {
            $this->handleEmptyData('No data provided for parsing categories.');
            return $this->results;
        }

        foreach ($data['entries'] as $category) {
            foreach ($category['details'] as $details) {
                if (strtoupper($details['lang']) != $this->getConfigLanguageCode()) {
                    continue;
                }

                $this->results[$details['categoryId']] =
                    array(
                        'name' => $details['name'],
                        'url' => $details['nameUrl']
                    );
            }
        }

        return $this->results;
    }

    /**
     * @param array $data
     * @return array
     */
    public function parseCategoryFullUrls($data)
    {
        if (!is_array($data) || !isset($data['entries'])) {
            $this->handleEmptyData('No data provided for parsing categories urls.');
            return $this->fullUrls;
        }

        foreach ($data['entries'] as $branch) {
            $fullPath = '/';
            $lastCategoryId = false;
            foreach ($branch as $level => $categoryId) {
                if (!$categoryId) {
                    if ($fullPath != '/') {
                        $this->fullUrls[$lastCategoryId] = $fullPath;
                    }
                    break;
                }

                if ($categoryPath = $this->getCategoryUrlKey($categoryId)) {
                    $fullPath .= $categoryPath . '/';
                    $lastCategoryId = $categoryId;
                } else {
                    $this->handleEmptyData('Could not find the url path from parsed data key for category with id ' . $categoryId);
                }
            }
        }

        return $this->fullUrls;
    }

    /**
     * @param int $categoryId
     * @return string
     */
    public function getCategoryName($categoryId)
    {
        if (array_key_exists($categoryId, $this->results)) {
            return $this->results[$categoryId]['name'];
        }

        return $this->getDefaultEmptyValue();
    }

    /**
     * @param int $categoryId
     * @return string
     */
    public function getCategoryFullPath($categoryId)
    {
        if (array_key_exists($categoryId, $this->fullUrls)) {
            return $this->fullUrls[$categoryId];
        }

        return $this->getDefaultEmptyValue();
    }

    /**
     * @param $categoryId
     * @return string
     */
    protected function getCategoryUrlKey($categoryId)
    {
        if (array_key_exists($categoryId, $this->results)) {
            return $this->results[$categoryId]['url'];
        }

        return false;
    }
}