<?php

namespace Findologic\Plentymarkets\Parser;

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
            //TODO: select details by language
            $categoryDetails = $category['details'][0];
            $this->results[$categoryDetails['categoryId']] = $categoryDetails['name'];
        }

        return $this->results;
    }
}