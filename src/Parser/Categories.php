<?php

namespace Findologic\Plentymarkets\Parser;

class Categories extends ParserAbstract implements ParserInterface
{
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
                if (
                    strtoupper($details['lang']) == $this->getLanguageCode() &&
                    (!isset($this->results[$details['categoryId']]) || $details['plentyId'] == $this->getStorePlentyId())
                ) {
                    $this->results[$details['categoryId']] = array(
                        'name' => $details['name'],
                        'urlKey' => $details['nameUrl'],
                        'fullPath' => parse_url($details['previewUrl'], PHP_URL_PATH)
                    );
                }
            }
        }

        return $this->results;
    }

    /**
     * @param array $data
     * @return array
     */
    public function parseCategoryFullNames($data)
    {
        if (!is_array($data) || !isset($data['entries'])) {
            $this->handleEmptyData('No data provided for parsing category URLs.');
            return $this->results;
        }

        foreach ($data['entries'] as $branch) {
            $fullNamePath = '';
            $lastCategoryId = false;
            $i = 0;
            // Unset first category as Plentymarkets for some reason inserts last category
            unset($branch['categoryId']);
            // IMPORTANT! Insert fake element to array so if category tree has values in all branches the last value
            // also would be processed
            $branch['End'] = null;
            $total = count($branch);
            foreach ($branch as $level => $categoryId) {
                if (!$categoryId || $total == $i) {
                    // If it is last category insert the data
                    if ($fullNamePath != '') {
                        $fullNamePath = rtrim($fullNamePath, '_');
                        $this->results[$lastCategoryId]['fullNamePath'] = $fullNamePath;
                    }
                    break;
                }

                if ($categoryNamePath = $this->getCategoryName($categoryId)) {
                    $fullNamePath .= $categoryNamePath . '_';
                    $lastCategoryId = $categoryId;
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
     * Return full path if category has any
     * Categories not returned in /rest/category_branches method do not have have full path so return URL key
     *
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
     * Return full category name path if category has any
     * Categories not returned in /rest/category_branches method do not have have full category name path so return name
     *
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
     * @param int $categoryId
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