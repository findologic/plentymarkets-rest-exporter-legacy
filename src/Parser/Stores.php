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
            $this->handleEmptyData("No data provided for parsing store's data.");
            return $this->results;
        }

        foreach ($data as $stores) {
            $this->results[$stores['storeIdentifier']] = [
                'id' => $stores['id'],
                'defaultLanguage' => $stores['configuration']['defaultLanguage'],
                'languageList' => array_map('trim', explode(',', $stores['configuration']['languageList']))
            ];
        }

        return $this->results;
    }

    /**
     * @param int $identifier
     * @return int|string
     */
    public function getStoreInternalIdByIdentifier($identifier)
    {
        if (isset($this->results[$identifier]['id'])) {
            return $this->results[$identifier]['id'];
        }

        return $this->getDefaultEmptyValue();
    }

    /**
     * @param int $identifier
     * @return string
     */
    public function getStoreDefaultLanguage($identifier)
    {
        if (isset($this->results[$identifier]['defaultLanguage'])) {
            return strtolower($this->results[$identifier]['defaultLanguage']);
        }

        return $this->getDefaultEmptyValue();
    }

    /**
     * @param $identifier
     * @param $language
     * @return bool
     */
    public function isLanguageAvailableInStore($identifier, $language)
    {
        if (isset($this->results[$identifier]['languageList']) && in_array(strtolower($language), $this->results[$identifier]['languageList'])) {
            return true;
        }

        return false;
    }
}