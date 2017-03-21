<?php

namespace Findologic\Plentymarkets\Parser;

class Vat implements ParserInterface
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

        foreach ($data['entries'] as $vat) {
            //TODO: vat by country ???
            //$this->results[$vat['id']] = $vat['type'];
        }

        return $this->results;
    }
}