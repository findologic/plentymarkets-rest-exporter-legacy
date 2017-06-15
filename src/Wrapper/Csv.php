<?php

namespace Findologic\Plentymarkets\Wrapper;

use Findologic\Plentymarkets\Wrapper\WrapperInterface;
use Findologic\Plentymarkets\Exception\CriticalException;

class Csv implements WrapperInterface
{
    /**
     * The csv delimiter character
     * @var string
     */
    const CSV_DELIMITER = "\t";

    /**
     * The csv enclosure character
     * @var string
     */
    const CSV_ENCLOSURE = '"';

    /**
     * The FINDOLOGIC delimiter for categories
     * @var string
     */
    const CATEGORY_DELIMITER = '_';

    /**
     * The plentymarkets delimiter for categories
     * @var string
     */
    const PLENTY_CATEGORY_DELIMITER = ';';

    /**
     * The FINDOLOGIC delimiter for ordernumbers
     * @var string
     */
    const ORDERNUMBER_DELIMITER = '|';

    /**
     * The plentymarkets delimiter for attribute sets
     * @var string
     */
    const ATTRIBUTE_SET_DELIMITER = ';';

    /**
     * The plentymarkets delimiter for attribute names and values
     * @var string
     */
    const ATTRIBUTE_DELIMITER = ':';

    /**
     * The FINDOLOGIC delimiter for groups
     * @var string
     */
    const GROUPS_DELIMITER = ',';

    /**
     * The key for category filters
     * @var string
     */
    const CATEGORY_FILTER_KEY = 'cat';

    /**
     * The key for vendor filters
     * @var string
     */
    const VENDOR_FILTER_KEY = 'vendor';

    /**
     * plentymarkets customer class for all customers
     * @var integer
     */
    const CUSTOMER_CLASS_ALL_CUSTOMERS = 0;

    /**
     * Flag for knowing if file header line was set already
     * @var bool
     */
    protected $headersSetFlag = false;

    /**
     * File where the results should be exported
     * @var null|string
     */
    protected $filename = 'Export.csv';

    /**
     * File handle for writing
     * @var mixed|bool
     */
    protected $stream = false;

    /**
     * Change file name if given.
     * @param string $filename
     */
    public function __construct($filename = null)
    {
        if ($filename) {
            $this->filename = $filename;
        }
    }

    /**
     * Get the file stream
     *
     * @return bool|mixed|resource
     * @throws CriticalException
     */
    public function getStream()
    {
        if (!$this->stream) {
            // If can not create the file throw the exception
            if (($this->stream = fopen($this->filename, 'w+')) === false ) {
                throw new CriticalException('File for saving export data could not be created!');
            }
        }

        return $this->stream;
    }


    /**
     * Because result for this wrapper is written to file return only a message
     *
     * @codeCoverageIgnore
     * @inheritdoc
     */
    public function getResults()
    {
        return 'Results wrapping was successful! File: ' . $this->filename;
    }

    /**
     * Write item data to file
     *
     * @param array
     * @return $this
     */
     public function wrapItem($data)
     {
         // Check if headers already set
         if (!$this->headersSetFlag) {
             $this->setHeaders($data);
         }

         $data = $this->convertData($data);

         fputcsv($this->getStream(), $data, self::CSV_DELIMITER, self::CSV_ENCLOSURE);

         return $this;
     }

    /**
     * Close stream
     *
     * @return $this
     */
     public function allItemsHasBeenProcessed()
     {
         // Close the stream
         if ($this->stream) {
             fclose($this->stream);
         }

         return $this;
     }

    /**
     * Convert arrays fields before rendering to csv to appropriate format
     *
     * @param array $data
     * @return array
     */
    public function convertData($data)
    {
        // Fields which values can not contain html
        $htmlFields = array('ordernumber', 'name', 'summary', 'description', 'keywords');
        $data['attributes'] = http_build_query($data['attributes']);
        $data['ordernumber'] = implode(self::ORDERNUMBER_DELIMITER, $data['ordernumber']);

        foreach ($htmlFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = $this->cleanText($data[$field], self::CSV_DELIMITER, self::CSV_ENCLOSURE);
            }
        }

        return $data;
    }

    /**
     * Set the header line of csv
     *
     * @param $headersData
     */
    protected function setHeaders($headersData)
    {
        $headers = array_keys($headersData);
        fputcsv($this->getStream(), $headers, self::CSV_DELIMITER, self::CSV_ENCLOSURE);
        $this->headersSetFlag = true;
    }

    /**
     * Remove HTML tags, multiple white-spaces, delimiters and enclosures from the given text
     *
     * @param string $text The text to clean
     * @param string $delimiter Delimiters which should be removed
     * @param string $enclosure Enclosures which should be removed
     *
     * @return string $text The cleaned text
     */
    protected function cleanText($text, $delimiter, $enclosure)
    {
        // Remove HTML tags.
        $text = strip_tags($text);
        // Replace all white-space, delimiter and enclosure with a single white-space.
        $text = preg_replace('/[\s' . preg_quote($delimiter) . preg_quote($enclosure) . ']+/', ' ', $text);

        return $text;
    }
}