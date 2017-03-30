<?php

namespace Findologic\Plentymarkets\Parser;

use Findologic\Plentymarkets\Config;

class Categories implements ParserInterface
{
    protected $results = array();

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
                if (strtoupper($details['lang']) != Config::TEXT_LANGUAGE) {
                    continue;
                }

                $this->results[$details['categoryId']] = $details['name'];
            }
        }

        return $this->results;
    }
}