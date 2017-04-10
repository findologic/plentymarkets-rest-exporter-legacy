<?php

namespace Findologic\Plentymarkets;

use Findologic\Plentymarkets\Data\Units;
use Findologic\Plentymarkets\Parser\Attributes;
use Findologic\Plentymarkets\Registry;

class Product
{
    const CATEGORY_ATTRIBUTE_FIELD = 'cat';
    const CATEGORY_URLS_ATTRIBUTE_FIELD = 'cat_url';
    const MANUFACTURER_ATTRIBUTE_FIELD = 'vendor';

    /**
     * @var int
     */
    protected $itemId;

    /**
     * @var \Findologic\Plentymarkets\Registry
     */
    protected $registry;

    /**
     * @var array
     */
    protected $fields = array(
        'id' => '',
        'ordernumber' => '',
        'name' => '',
        'summary' => '',
        'description' => '',
        'price' => 0.00,
        'instead' => 0.00,
        'maxprice' => 0.00,
        'taxrate' => '',
        'url' => '',
        'image' => '',
        'base_unit' => '',
        'package_size' => '',
        'price_id' => '',
        'attributes' => '',
        'keywords' => '',
        'groups' => '',
        'bonus' => '',
        'sales_frequency' => '',
        'date_added' => '',
        'sort' => '',
    );

    protected $protocol = 'http://';

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Item id used for identification
     *
     * @return int
     */
    public function getItemId()
    {
        return $this->itemId;
    }

    /**
     * @codeCoverageIgnore - Ignore this method as it used for better mocking
     */
    public function getConfigLanguageCode()
    {
        return strtoupper(Config::TEXT_LANGUAGE_CODE);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as it used for better mocking
     */
    public function getStoreUrl()
    {
        return rtrim(Config::URL, '/');
    }

    /**
     * @param string $protocol
     * @return $this
     */
    public function setProtocol($protocol)
    {
        $this->protocol = $protocol;

        return $this;
    }

    /**
     * @param mixed $key
     * @return mixed|null
     */
    public function getField($key)
    {
        if (isset($this->fields[$key])) {
            return $this->fields[$key];
        }

        return '';
    }

    /**
     * Setter methods for easier setting 'fields' property
     * Allows to use $array flag if values of the field should be store as array of values
     *
     * @param string $key
     * @param mixed $value
     * @param bool $array
     * @return $this
     */
    public function setField($key, $value, $array = false)
    {
        if ($array) {
            $this->fields[$key][] = $value;
        } else {
            $this->fields[$key] = $value;
        }

        return $this;
    }

    /**
     * Set $attribute values
     * If such $value for attribute already exist it will be ignored to avoid multiple same values
     *
     * @param string $name
     * @param string $value
     * @return $this
     */
    public function setAttributeField($name, $value)
    {
        if (!$this->fields['attributes']) {
            $this->fields['attributes'] = array();
        }

        if (isset($this->fields['attributes'][$name]) && in_array($value, $this->fields['attributes'][$name])) {
            // Value already exists so skip this value
            return $this;
        }

        $this->fields['attributes'][$name][] = $value;

        return $this;
    }

    /**
     * @param string $path
     * @return string
     */
    public function getProductFullUrl($path)
    {
        if (!is_string($path) || $path == '') {
            $this->handleEmptyData();
            return '';
        }

        // Using trim just in case if path could be passed with and without forward slash
        $path = '/' . ltrim($path, '/');
        $path = rtrim($path, '/');

        return $this->protocol . $this->getStoreUrl() . $path . '/' . 'a-' . $this->getItemId();
    }

    /**
     * Return fields array
     *
     * @return array
     */
    public function getResults()
    {
        return $this->fields;
    }

    /**
     * Process initial data from '/items' call response
     *
     * @param array $data
     * @return $this
     */
    public function processInitialData($data)
    {
        $this->itemId = $this->getFromArray($data, 'id');

        $this->setField('id', $this->getItemId())
            ->setField('date_added', strtotime($this->getFromArray($data, 'createdAt')))
            ->setField('sort', $this->getFromArray($data, 'position'));

        $this->processManufacturer($this->getFromArray($data, 'manufacturerId'));
        $this->processTexts($data);

        return $this;
    }

    /**
     * Get mapped manufacturer name from parser
     *
     * @param int $manufacturerId
     * @return $this
     */
    public function processManufacturer($manufacturerId)
    {
        if ($manufacturerId) {
            $this->setAttributeField(
                self::MANUFACTURER_ATTRIBUTE_FIELD,
                $this->registry->get('manufacturers')->getManufacturerName($manufacturerId)
            );
        }

        return $this;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function processVariations($data)
    {
        if (!isset($data['entries'])) {
            return $this->handleEmptyData();
        }

        foreach ($data['entries'] as $variation) {
            $this->setField(
                'taxrate',
                $this->registry->get('vat')->getVatRateByVatId($this->getFromArray($variation, 'vatId'))
            );

            $this->processVariationIdentifiers($variation)
                ->proccessVariationCategories($this->getFromArray($variation, 'variationCategories'))
                ->processVariationPrices($this->getFromArray($variation, 'variationSalesPrices'))
                ->processVariationAttributes($this->getFromArray($variation, 'variationAttributeValues'))
                ->processUnits($this->getFromArray($variation, 'unit'));
        }

        return $this;
    }

    /**
     * Process variation categories
     *
     * @param array $data
     * @return $this
     */
    public function proccessVariationCategories($data)
    {
        if (!is_array($data)) {
            return $this->handleEmptyData();
        }

        foreach ($data as $category) {
            $categoryId = $this->getFromArray($category, 'categoryId');
            $this->setAttributeField(
                self::CATEGORY_ATTRIBUTE_FIELD,
                $this->registry->get('categories')->getCategoryName($categoryId)
            );
            $this->setAttributeField(
                self::CATEGORY_URLS_ATTRIBUTE_FIELD,
                $this->registry->get('categories')->getCategoryFullPath($categoryId)
            );
        }

        return $this;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function processVariationsProperties($data)
    {
        if (!is_array($data) || empty($data)) {
            return $this->handleEmptyData();
        }

        foreach ($data as $property) {
            $propertyName = $property['property']['backendName'];
            $value = $this->getPropertyValue($property);
            if ($value != null) {
                $this->setAttributeField($propertyName, $value);
            }
        }

        return $this;
    }

    /**
     * Get the image for item
     *
     * @param $data
     * @return $this
     */
    public function processImages($data)
    {
        if (!is_array($data) || empty($data)) {
            return $this->handleEmptyData();
        }

        // Data for images could be returned as array of images if there is multiple images assigned
        if (!isset($data['itemId'])) {
            $data = $data[0];
        }

        $this->setField('image', $this->getFromArray($data, 'urlMiddle'));

        return $this;
    }

    /**
     * Get rrp prices ids array
     *
     * @return array
     */
    protected function getRRP()
    {
        $prices = $this->registry->get('SalesPrices');
        if (!$prices) {
            return array();
        }

        return $prices->getRRP();
    }

    /**
     * Wrap getting data from array to allow returning default empty field value if given key do not exist
     *
     * @param array $array
     * @param string $key
     * @return mixed
     */
    protected function getFromArray($array, $key)
    {
        if (isset($array[$key])) {
            return $array[$key];
        }

        return '';
    }

    /**
     * Get property value by its type from property array
     *
     * @param array $property
     * @return string
     */
    protected function getPropertyValue($property)
    {
        $propertyType = $property['property']['valueType'];
        $value = '';

        switch ($propertyType) {
            //TODO: handling 'empty' type properties
            case 'text':
                foreach ($property['names'] as $name) {
                    if (strtoupper($name['lang']) != $this->getConfigLanguageCode()) {
                        continue;
                    }

                    $value = $name['value'];
                }
                break;
            case 'selection':
                foreach ($property['propertySelection'] as $selection) {
                    if (strtoupper($selection['lang']) != $this->getConfigLanguageCode()) {
                        continue;
                    }

                    $value = $selection['name'];
                }
                break;
            case 'int':
                $value = $property['valueInt'];
                break;
            case 'float':
                $value = $property['valueFloat'];
                break;
            default:
                $value = '';
                break;
        }

        return $value;
    }

    /**
     * Get all the fields used for 'ordernumber' field
     *
     * @param array $variation
     * @return $this
     */
    protected function processVariationIdentifiers($variation)
    {
        $identificators = array('number', 'model', 'id', 'itemId');

        if (!$this->getField('ordernumber')) {
            $this->setField('ordernumber', array());
        }

        foreach ($identificators as $identificator) {
            $this->addVariationIdentifier($this->getFromArray($variation, $identificator));
        }

        if (isset($variation['variationBarcodes'])) {
            $this->processVariationsBarcodes($variation['variationBarcodes']);
        }

        return $this;
    }

    /**
     * Each variation can have multiple barcodes
     *
     * @param array $barcodes
     * @return $this
     */
    protected function processVariationsBarcodes($barcodes)
    {
        foreach ($barcodes as $barcode) {
            $this->addVariationIdentifier($this->getFromArray($barcode, 'code'));
        }

        return $this;
    }

    /**
     * Add all identifiers to 'ordernumber' field as array set
     *
     * @param string $value
     * @return $this
     */
    protected function addVariationIdentifier($value)
    {
        if ($value && !in_array($value, $this->getField('ordernumber'))) {
            $this->setField('ordernumber', $value, true);
        }

        return $this;
    }

    /**
     * Get the price values
     * Prices has types ('salesPriceId' value)
     * 'instead' field should hold retail recommended price (Filter prices by 'salesPriceId' to find which is RRP)
     *
     * @param array $data
     * @return $this
     */
    protected function processVariationPrices($data)
    {
        if (!$data) {
            return $this->handleEmptyData();
        }

        foreach ($data as $price) {
            if ($price['price'] == 0) {
                continue;
            }

            if (!$this->getField('price')) {
                $this->setField('price', $price['price']);
                $this->setField('price_id', $price['salesPriceId']);
            } else {
                if ($this->getField('price') > $price['price']) {
                    $this->setField('price', $price['price']);
                    $this->setField('price_id', $price['salesPriceId']);
                }
            }

            if ($this->getField('maxprice') < $price['price']) {
                $this->setField('maxprice', $price['price']);
            }

            if (in_array($price['salesPriceId'], $this->getRRP())) {
                //TODO: rrp with highest id should be used
                $this->setField('instead', $price['price']);
            }
        }

        return $this;
    }

    /**
     * Iterate over variation attributes and map attributes value ids to actual values
     *
     * @param array $attributesData
     * @return $this
     */
    protected function processVariationAttributes($attributesData)
    {
        if (!count($attributesData)) {
            return $this->handleEmptyData();
        }

        /**
         * @var \Findologic\Plentymarkets\Parser\Attributes
         */
        $attributesValues = $this->registry->get('Attributes');


        foreach ($attributesData as $attribute) {
            // Check if attribute exist in attributes data parsed on export initialization
            if ($attributesValues->attributeValueExists($attribute['attributeId'], $attribute['valueId'])) {
                $this->setAttributeField(
                    $attributesValues->getAttributeName($attribute['attributeId']),
                    $attributesValues->getAttributeValueName($attribute['attributeId'], $attribute['valueId'])
                );
            }
        }

        return $this;
    }

    /**
     * Variation units processing
     * Map variation 'unitId' with ISO value
     *
     * @param $data
     * @return $this|Product
     */
    protected function processUnits($data)
    {
        if (empty($data)) {
            return $this->handleEmptyData();
        }

        //TODO: it seems variations could have different unit values per variation ???
        $unitId = $this->getFromArray($data, 'unitId');
        $this->setField('base_unit', Units::getUnitValue($unitId));
        $this->setField('package_size', $this->getFromArray($data, 'content'));

        return $this;
    }

    /**
     * @param array $data
     * @return $this
     */
    protected function processTexts($data)
    {
        if (!isset($data['texts']) || !count($data['texts'])) {
            return $this->handleEmptyData();
        }

        foreach ($data['texts'] as $texts) {
            if (strtoupper($texts['lang']) != $this->getConfigLanguageCode()) {
                continue;
            }

            $this->setField('name', $this->getFromArray($texts, 'name1'))
                ->setField('summary', $this->getFromArray($texts, 'shortDescription'))
                ->setField('description', $this->getFromArray($texts, 'description'))
                ->setField('url', $this->getProductFullUrl($this->getFromArray($texts, 'urlPath')))
                ->setField('keywords', $this->getFromArray($texts, 'keywords'));
        }

        return $this;
    }

    /**
     * Log information about missing data
     *
     * @return $this
     */
    protected function handleEmptyData()
    {
        // TODO: maybe log the caller method name wheres the data is missing
        return $this;
    }
}