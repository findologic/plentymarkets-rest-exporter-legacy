<?php

namespace Findologic\Plentymarkets\Parser;

class Stores extends ParserAbstract implements ParserInterface
{
    /**
     * @inheritdoc
     */
    public function parse($data)
    {
        if (empty($data)) {
            $this->handleEmptyData('No data provided for parsing stores data.');
            return $this->results;
        }

        foreach ($data as $stores) {
            $this->results[$stores['storeIdentifier']] = $stores['id'];
        }

        return $this->results;
    }

    /**
     * @param string $identifier
     * @return string
     */
    public function getStoreInternalIdByIdentifier($identifier)
    {
        if (isset($this->results[$identifier])) {
            return $this->results[$identifier];
        }

        return $this->getDefaultEmptyValue();
    }
}