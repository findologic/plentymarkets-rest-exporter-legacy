<?php

namespace Findologic\Plentymarkets;

use Findologic\Plentymarkets\Parser\ParserAbstract;
use Findologic\Plentymarkets\Data\Units;
use Findologic\Plentymarkets\Parser\Attributes;

class Product extends ParserAbstract
{
    const CATEGORY_ATTRIBUTE_FIELD = 'cat';
    const CATEGORY_URLS_ATTRIBUTE_FIELD = 'cat_url';
    const MANUFACTURER_ATTRIBUTE_FIELD = 'vendor';

    /**
     * Item id in shops system
     *
     * @var int
     */
    protected $itemId;

    /**
     * Flag for allowing to skip this product if no active variations has been found
     *
     * @var bool
     */
    protected $hasData = false;

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
        'maxprice' => null,
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
        'sales_frequency' => null,
        'date_added' => '',
        'sort' => '',
    );

    /**
     * Protocol which should be used when formatting product url
     *
     * @var string
     */
    protected $protocol = 'http://';

    /**
     * Flag used to identify when the property value should be taken from property name and property name from
     * property group name
     *
     * @var bool
     */
    protected $swapPropertyValuesFlag = false;

    /**
     * @var mixed
     */
    protected $availabilityIds;

    /**
     * @var int
     */
    protected $priceId;

    /**
     * @var int
     */
    protected $rrpPriceId;

    /**
     * Flag to know if store supports salesRank
     *
     * @var bool
     */
    protected $exportSalesFrequency = false;

    /**
     * @param mixed $ids
     * @return $this
     */
    public function setAvailabilityIds($ids)
    {
        if (!empty($ids) && !is_array($ids)) {
            $ids = array($ids);
        }

        $this->availabilityIds = $ids ? $ids : null;

        return $this;
    }

    /**
     * @param int $priceId
     * @return $this
     */
    public function setPriceId($priceId)
    {
        $this->priceId = $priceId;

        return $this;
    }

    /**
     * @return int
     */
    public function getPriceId()
    {
        return $this->priceId;
    }

    /**
     * @param int $rrpPriceId
     * @return $this
     */
    public function setRrpPriceId($rrpPriceId)
    {
        $this->rrpPriceId = $rrpPriceId;

        return $this;
    }

    /**
     * @return int
     */
    public function getRrpPriceId()
    {
        return $this->rrpPriceId;
    }

    /**
     * @param bool|int $exportSalesFrequency
     * @return $this
     */
    public function setExportSalesFrequency($exportSalesFrequency)
    {
        $this->exportSalesFrequency = $exportSalesFrequency;

        if ($exportSalesFrequency) {
            $this->setField('sales_frequency', 0);
        }

        return $this;
    }

    /**
     * @return bool|int
     */
    public function getExportSalesFrequency()
    {
        return $this->exportSalesFrequency;
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
     * Return a false value if there was no variations which was active or passed other
     * configurated visibility filtering
     *
     * @codeCoverageIgnore
     * @return bool
     */
    public function hasData()
    {
        return $this->hasData;
    }

    /**
     * @return \Findologic\Plentymarkets\Registry
     */
    public function getRegistry()
    {
        return $this->registry;
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

        return $this->getDefaultEmptyValue();
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
     * Plentymarkets do not return full url so it should be formatted by given information
     *
     * @param string $path
     * @return string
     */
    public function getProductFullUrl($path)
    {
        if (!is_string($path) || $path == '') {
            $this->handleEmptyData();
            return $this->getDefaultEmptyValue();
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
                $this->getRegistry()->get('manufacturers')->getManufacturerName($manufacturerId)
            );
        }

        return $this;
    }

    /**
     * Process product variations
     *
     * @param array $variation
     * @return bool
     */
    public function processVariation($variation)
    {
        if (!$this->shouldProcessVariation($variation)) {
            return false;
        }

        $this->setField(
            'taxrate',
            $this->getRegistry()->get('vat')->getVatRateByVatId($this->getFromArray($variation, 'vatId'))
        );

        $this->processVariationIdentifiers($variation)
            ->processVariationCategories($this->getFromArray($variation, 'variationCategories'))
            ->processVariationGroups($this->getFromArray($variation, 'variationClients'))
            ->processVariationPrices($this->getFromArray($variation, 'variationSalesPrices'))
            ->processVariationAttributes($this->getFromArray($variation, 'variationAttributeValues'))
            ->processUnits($this->getFromArray($variation, 'unit'));

        if ($this->getExportSalesFrequency() && isset($variation['salesRank']) && $this->getField('sales_frequency') < $variation['salesRank']) {
            $this->setField('sales_frequency', $variation['salesRank']);
        }

        return true;
    }

    /**
     * Insert categories and category urls to attributes
     *
     * @param array $data
     * @return $this
     */
    public function processVariationCategories($data)
    {
        if (!is_array($data) || empty($data)) {
            $this->handleEmptyData();
            return $this;
        }

        foreach ($data as $category) {
            $categoryId = $this->getFromArray($category, 'categoryId');
            $categoryName = $this->getRegistry()->get('categories')->getCategoryFullNamePath($categoryId);
            $categoryUrl = $this->getRegistry()->get('categories')->getCategoryFullPath($categoryId);

            if ($categoryName != $this->getDefaultEmptyValue()) {
                $this->setAttributeField(self::CATEGORY_ATTRIBUTE_FIELD, $categoryName);
            }

            if ($categoryUrl != $this->getDefaultEmptyValue()) {
                $this->setAttributeField(self::CATEGORY_URLS_ATTRIBUTE_FIELD, $categoryUrl);
            }
        }

        return $this;
    }

    /**
     * Process variation groups, currently plentymarkets only provide data about variation store and the customer groups
     * information is not provided
     *
     * @param array $variationStores
     * @return $this
     */
    public function processVariationGroups($variationStores)
    {
        if (!is_array($variationStores) || empty($variationStores)) {
            $this->handleEmptyData();
            return $this;
        }

        $groups = '';

        foreach ($variationStores as $store) {
            $storeId = $this->getRegistry()->get('stores')->getStoreInternalIdByIdentifier($store['plentyId']);
            if ($storeId !== $this->getDefaultEmptyValue()) {
                $groups .= $storeId . '_' . ',';
            }
        }

        $groups = rtrim($groups, ',');

        $this->setField('groups', $groups);

        return $this;
    }

    /**
     * Process variation properties
     * Some properties (empty type) have mixed up value for actual property name and value
     *
     * @param array $data
     * @return $this
     */
    public function processVariationsProperties($data)
    {
        if (!is_array($data) || empty($data)) {
            $this->handleEmptyData();
            return $this;
        }

        foreach ($data as $property) {
            if (isset($property['property']['isSearchable']) && !$property['property']['isSearchable']) {
                continue;
            }

            $propertyName = $this->getPropertyName($property);
            $value = $this->getPropertyValue($property);

            if ($this->swapPropertyValuesFlag) {
                // This means that property value is saved as property 'backendName'
                // and actual property name should be taken from property group name
                $temp = $value;
                $value = $propertyName;
                $propertyName = $temp;
                $this->swapPropertyValuesFlag = false;
            }

            if ($value != null && $value != $this->getDefaultEmptyValue()) {
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
            $this->handleEmptyData();
            return $this;
        }

        // Data for images could be returned as array of images if there is multiple images assigned
        if (!isset($data['itemId'])) {
            $data = $data[0];
        }

        if (!$this->getField('image')) {
            $this->setField('image', $this->getFromArray($data, 'urlMiddle'));
        }

        return $this;
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

        return $this->getDefaultEmptyValue();
    }

    protected function getPropertyName($property)
    {
        $name = $property['property']['backendName'];
        if (!isset($property['names']) || !is_array($property['names'])) {
            return $name;
        }

        foreach ($property['names'] as $propertyName) {
            if ($propertyName['lang'] == $this->getLanguageCode()) {
                $name = $propertyName['name'];
                break;
            }
        }

        return $name;
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
        $value = $this->getDefaultEmptyValue();

        switch ($propertyType) {
            case 'empty':
                $value = $this->getPropertyGroupForPropertyName($property['property']['propertyGroupId']);
                break;
            case 'text':
                // For some specific shops the structure of text property is different and do not have 'names' field
                if (isset($property['valueTexts'])) {
                    foreach ($property['valueTexts'] as $name) {
                        if (strtoupper($name['lang']) != $this->getLanguageCode()) {
                            continue;
                        }

                        $value = $name['value'];

                        if (!$value) {
                            $value = $this->getPropertyGroupForPropertyName($property['property']['propertyGroupId']);
                        }
                    }
                } else {
                    $value = $this->getPropertyGroupForPropertyName($property['property']['propertyGroupId']);
                }
                break;
            case 'selection':
                foreach ($property['propertySelection'] as $selection) {
                    if (strtoupper($selection['lang']) != $this->getLanguageCode()) {
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
                $value = $this->getDefaultEmptyValue();
                break;
        }

        return $value;
    }

    /**
     * @param int $propertyGroupId
     * @return string|mixed
     */
    protected function getPropertyGroupForPropertyName($propertyGroupId)
    {
        $value = $this->getRegistry()->get('PropertyGroups')->getPropertyGroupName($propertyGroupId);
        $this->swapPropertyValuesFlag = true;

        return $value;
    }

    /**
     * Check if product variation should be added to import or skipped
     *
     * @param array $variation
     * @return bool
     */
    protected function shouldProcessVariation($variation)
    {
        if ($variation['isActive'] == false) {
            return false;
        }

        if (!$this->isProductAvailable($variation['availability'])) {
            return false;
        }

        $this->hasData = true;

        return true;
    }

    /**
     * @param int $itemAvailabilityId
     * @return bool
     */
    protected function isProductAvailable($itemAvailabilityId)
    {
        if (!empty($this->availabilityIds) && in_array($itemAvailabilityId, $this->availabilityIds)) {
            return false;
        }

        return true;
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
            $value = trim($value);
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
            $this->handleEmptyData();
            return $this;
        }

        foreach ($data as $price) {
            if ($price['price'] == 0) {
                continue;
            }

            if ($price['salesPriceId'] == $this->getPriceId()) {
                if (!$this->getField('price')) {
                    $this->setField('price', $price['price']);
                    $this->setField('price_id', $price['salesPriceId']);
                } else {
                    if ($this->getField('price') > $price['price']) {
                        $this->setField('price', $price['price']);
                        $this->setField('price_id', $price['salesPriceId']);
                    }
                }
            }

            if ($price['salesPriceId'] == $this->getRrpPriceId()) {
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
            $this->handleEmptyData();
            return $this;
        }

        /**
         * @var \Findologic\Plentymarkets\Parser\Attributes
         */
        $attributesValues = $this->getRegistry()->get('Attributes');

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
            $this->handleEmptyData();
            return $this;
        }

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
            $this->handleEmptyData();
            return $this;
        }

        foreach ($data['texts'] as $texts) {
            if (strtoupper($texts['lang']) != $this->getLanguageCode()) {
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
}