<?php

namespace Findologic\Plentymarkets\Parser;

use Findologic\Plentymarkets\Config;

class Categories implements ParserInterface
{
    protected $results = array();

    /**
     * @codeCoverageIgnore - Ignore this method as it used for better mocking
     */
    public function getConfigLanguageCode()
    {
        return strtoupper(Config::TEXT_LANGUAGE_CODE);
    }

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
     * @param int $id
     * @return string
     */
    public function getCategoryName($id)
    {
        if (array_key_exists($id, $this->results)) {
            return $this->results[$id]['name'];
        }

        return '';
    }
}