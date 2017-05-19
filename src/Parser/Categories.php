<?php

namespace Findologic\Plentymarkets\Parser;

use Findologic\Plentymarkets\Config;

class Categories extends ParserAbstract implements ParserInterface
{
    /**
     * @inheritdoc
     */
    public function parse($data)
    {
        if (!isset($data['entries'])) {
            $this->handleEmptyData('No data provided for parsing categories .');
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
                        'urlKey' => $details['nameUrl']
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
            return $this->results;
        }

        foreach ($data['entries'] as $branch) {
            $fullPath = '/';
            $fullNamePath = '';
            $lastCategoryId = false;
            $i = 0;
            // Unset first category as plentymarkets for some reason inserts last category
            unset($branch['categoryId']);
            // IMPORTANT! Insert fake element to array so if category tree has values in all branches the last value
            // also would be processed
            $branch['End'] = null;
            $total = count($branch);
            foreach ($branch as $level => $categoryId) {
                if (!$categoryId || $total == $i) {
                    if ($fullPath != '/') {
                        $this->results[$lastCategoryId]['fullPath'] = $fullPath;
                    }

                    if ($fullNamePath != '') {
                        $fullNamePath = rtrim($fullNamePath, '_');
                        $this->results[$lastCategoryId]['fullNamePath'] = $fullNamePath;
                    }
                    break;
                }

                if ($categoryPath = $this->getCategoryUrlKey($categoryId)) {
                    $fullPath .= $categoryPath . '/';
                    $fullNamePath .= $this->getCategoryName($categoryId) . '_';
                    $lastCategoryId = $categoryId;
                } else {
                    $this->handleEmptyData('Could not find the url path from parsed data key for category with id ' . $categoryId);
                }

                $i++;
            }
        }

        return $this->results;
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
        if (array_key_exists($categoryId, $this->results)) {
           if (isset($this->results[$categoryId]['fullPath'])) {
               return $this->results[$categoryId]['fullPath'];
           } else {
               return $this->results[$categoryId]['urlKey'];
           }
        }

        return $this->getDefaultEmptyValue();
    }

    /**
     * @param int $categoryId
     * @return string
     */
    public function getCategoryFullNamePath($categoryId)
    {
        if (array_key_exists($categoryId, $this->results)) {
            if (isset($this->results[$categoryId]['fullNamePath'])) {
                return $this->results[$categoryId]['fullNamePath'];
            } else {
                return $this->getCategoryName($categoryId);
            }
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
            return $this->results[$categoryId]['urlKey'];
        }

        return false;
    }
}