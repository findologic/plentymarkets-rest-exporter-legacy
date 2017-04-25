<?php

namespace Findologic\Plentymarkets\Parser;

use Findologic\Plentymarkets\Config;

class Categories extends ParserAbstract implements ParserInterface
{
    protected $results = array();

    protected $fullUrls = array();

    /**
     * @inheritdoc
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * @inheritdoc
     */
    public function parse($data)
    {
        if (!isset($data['entries'])) {
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