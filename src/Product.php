<?php

namespace Findologic\Plentymarkets;

use Findologic\Plentymarkets\Parser\ParserAbstract;
use Findologic\Plentymarkets\Parser\PropertySelections;
use Findologic\Plentymarkets\Parser\Units;
use Findologic\Plentymarkets\Parser\ItemProperties;
use Findologic\Plentymarkets\Parser\Properties;

class Product extends ParserAbstract
{
    const CATEGORY_ATTRIBUTE_FIELD = 'cat';
    const CATEGORY_URLS_ATTRIBUTE_FIELD = 'cat_url';
    const MANUFACTURER_ATTRIBUTE_FIELD = 'vendor';

    const AVAILABILITY_STORE = 'mandant';

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
        'main_variation_id' => ''
    );

    /**
     * Protocol which should be used when formatting product URL
     *
     * @var string
     */
    protected $protocol = 'http://';

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
     * @var bool|string
     */
    protected $productUrlPrefix = false;

    /**
     * Flag to know if store supports salesRank
     *
     * @var bool
     */
    protected $exportSalesFrequency = false;

    /**
     * Value for getting correct product name
     *
     * @var int
     */
    protected $productNameFieldId = 1;

    /**
     * Available values for product name field configuration
     *
     * @var array
     */
    protected $availableProductNameFieldIdValues = array(1, 2, 3);

    /**
     * @param int $productNameFieldId
     * @return $this
     */
    public function setProductNameFieldId($productNameFieldId)
    {
        if (!in_array($productNameFieldId, $this->availableProductNameFieldIdValues)) {
            return $this;
        }

        $this->productNameFieldId = $productNameFieldId;

        return $this;
    }

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
     * @return string
     */
    public function getProductUrlPrefix()
    {
        return $this->productUrlPrefix;
    }

    /**
     * @param string $productUrlPrefix
     * @return $this
     */
    public function setProductUrlPrefix($productUrlPrefix)
    {
        $this->productUrlPrefix = $productUrlPrefix;

        return $this;
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
     * Return a false value if there was no variation which was active or passed other
     * configured visibility filtering
     *
     * @return bool
     */
    public function hasValidData()
    {
        if (!$this->hasData) {
            return false;
        }

        $categories = $this->getAttributeField(self::CATEGORY_ATTRIBUTE_FIELD);
        if (empty($categories) || $categories == $this->getDefaultEmptyValue()) {
            return false;
        }

        return true;
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
        if ($name === null || $value === null) {
            return $this;
        }

        if ($this->exceedsAllowedAttributeCharacterLimit($value)) {
            return $this;
        }

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

    private function exceedsAllowedAttributeCharacterLimit(string $value): bool
    {
        return (strlen($value) > 16383);
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function getAttributeField($name)
    {
        if (isset($this->fields['attributes'][$name])) {
            return $this->fields['attributes'][$name];
        }

        return $this->getDefaultEmptyValue();
    }

    /**
     * Plentymarkets do not return full URL so it should be formatted by given information
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

        $prefix = '';

        if ($this->getProductUrlPrefix()) {
            $prefix .= '/' . $this->getProductUrlPrefix();
        }

        // Using trim just in case if path could be passed with and without forward slash
        $path = '/' . ltrim($path, '/');
        $path = rtrim($path, '/');

        return $this->protocol . $this->getStoreUrl() . $prefix . $path . '/' . 'a-' . $this->getItemId();
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
    public function processInitialData(array $data)
    {
        $this->itemId = $this->getFromArray($data, 'id');

        $this->setField('id', $this->getItemId())
            ->setField('date_added', strtotime($this->getFromArray($data, 'createdAt')));

        $this->processManufacturer($this->getFromArray($data, 'manufacturerId'));
        $this->processTexts($data);
        $this->processFreeTextFields($data);

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
    public function processVariation(array $variation)
    {
        if (!$this->shouldProcessVariation($variation)) {
            return false;
        }

        if ($variation['base']['isMain'] || $this->getField('sort') === '') {
            $this->setField('sort', $this->getFromArray($variation['base'], 'position'));
        }

        $this->setField(
            'taxrate',
            $this->getRegistry()->get('vat')->getVatRateByVatId($this->getFromArray($variation['base'], 'vatId'))
        );

        $this->processVariationIdentifiers($variation)
            ->processVariationCategories($this->getFromArray($variation, 'categories'))
            ->processVariationGroups($this->getFromArray($variation, 'clients'))
            ->processVariationPrices($this->getFromArray($variation, 'salesPrices'))
            ->processVariationAttributes($this->getFromArray($variation, 'attributeValues'))
            ->processUnits($this->getFromArray($variation, 'unit'))
            ->processTags($this->getFromArray($variation, 'tags'));

        if ($this->getExportSalesFrequency() && isset($variation['base']['position']) && $this->getField('sales_frequency') < $variation['base']['position']) {
            $this->setField('sales_frequency', $variation['base']['position']);
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
     * Process variation groups, currently Plentymarkets only provide data about variation store and the customer groups
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
     * Process characteristics (aka. ItemProperties). Characteristics are assigned directly to items not to variations.
     * Some characteristics (empty type) have mixed up value for actual characteristic name and value
     *
     * @param array $data
     * @return $this
     */
    public function processCharacteristics(array $data)
    {
        if (!is_array($data) || empty($data)) {
            $this->handleEmptyData();
            return $this;
        }

        /** @var ItemProperties $properties */
        $properties = $this->registry->get('ItemProperties');
        foreach ($data as $property) {
            $propertyData = $properties->getProperty($property['propertyId']);

            if (empty($propertyData) || isset($propertyData['isSearchable']) && !$propertyData['isSearchable']) {
                continue;
            }

            if (
                $propertyData['valueType'] === 'empty' &&
                (!isset($propertyData['propertyGroupId']) || empty($propertyData['propertyGroupId']))
            ) {
                continue;
            }

            $value = $this->getPropertyValue($propertyData, $property);

            // If there is no valid value for the property, use its name as value and the group name as the
            // property name.
            // Properties of type "empty" are a special case since they never have a value of their own.
            if ($propertyData['valueType'] === 'empty') {
                $propertyName = $this->getPropertyGroupForPropertyName($propertyData['propertyGroupId']);
            } elseif ($value === $this->getDefaultEmptyValue()) {
                $propertyName = $this->getPropertyGroupForPropertyName($propertyData['propertyGroupId']);
                $value = $this->getPropertyName($propertyData);
            } else {
                $propertyName = $this->getPropertyName($propertyData);
            }

            if ($propertyName != null && $value != "null" && $value != null && $value != $this->getDefaultEmptyValue()) {
                $this->setAttributeField($propertyName, $value);
            }
        }

        return $this;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function processProperties($data)
    {
        if (!is_array($data) || empty($data)) {
            $this->handleEmptyData();

            return $this;
        }

        /** @var Properties $properties */
        if (!$properties = $this->getVariationSpecificPropertiesFromRegistry()) {
            $this->handleEmptyData('Variation properties are missing');

            return $this;
        }

        foreach ($data as $property) {
            if ($property['property']['type'] != ItemProperties::PROPERTY_TYPE_ITEM) {
                continue;
            }

            $propertyName = $properties->getPropertyName($property['propertyId']);
            $value = null;

            if ($property['property']['cast'] == 'empty') {
                $value = $propertyName;
                $propertyName = $properties->getPropertyGroupName($property['propertyId']);
            } else if ($property['property']['cast'] == 'text' || $property['property']['cast'] == 'html') {
                foreach ($property['values'] as $relationValue) {
                    if (strtoupper($relationValue['lang']) != strtoupper($this->getLanguageCode())) {
                        continue;
                    }

                    $value = $relationValue['value'];
                }
            } else if ($property['property']['cast'] == 'selection') {
                if (isset($property['propertyId']) && isset($property['values']) && isset($property['values'][0])) {
                    $value = $properties->getPropertySelectionValue($property['propertyId'], $property['values'][0]['value']);
                }
            } else if ($property['property']['cast'] == 'multiSelection') {
                if (isset($property['propertyId']) && isset($property['values'])) {
                    /** @var PropertySelections $propertySelections */
                    $propertySelections = $this->registry->get('PropertySelections');
                    $value = $propertySelections->getPropertySelectionValue($property['propertyId'], $property['values']);
                }
            } else {
                $value = $property['values'][0]['value'] ?? null;
            }

            if ($this->isPropertyEmpty($propertyName, $value)) {
                continue; // Ignore empty properties.
            }

            if (is_array($value)) {
                foreach ($value as $singleValue) {
                    $this->setAttributeField($propertyName, $singleValue);
                }
            } else {
                $this->setAttributeField($propertyName, $value);
            }
        }

        return $this;
    }

    private function isPropertyEmpty($propertyName, $value): bool
    {
        return ($propertyName == null || $value == "null" || $value == null || $value == $this->getDefaultEmptyValue());
    }

    /**
     * Get the image for item
     *
     * @param array $data
     * @return $this
     */
    public function processImages($data)
    {
        if (!is_array($data) || empty($data)) {
            $this->handleEmptyData();
            return $this;
        }

        $data = $this->getAvailableImage($data);

        if (empty($data)) {
            return $this;
        }

        if (!$this->getField('image')) {
            $this->setField('image', $this->getFromArray($data, 'urlMiddle'));
        }

        return $this;
    }

    /**
     * Returns first image that is available in store
     */
    protected function getAvailableImage(array $data): array
    {
        foreach ($data as $image) {
            $imageAvailabilities = $image['availabilities'];

            if (empty($imageAvailabilities)) {
                continue;
            }

            // Ignore images without position, as these are usually internal or only visible on product detail
            // pages.
            if (!isset($image['position'])) {
                continue;
            }

            foreach ($imageAvailabilities as $imageAvailability) {

                if ($imageAvailability['type'] === self::AVAILABILITY_STORE) {
                    return $image;
                }
            }
        }

        return [];
    }

    /**
     * @return Properties|null
     */
    protected function getVariationSpecificPropertiesFromRegistry()
    {
        $properties = $this->registry->get('Properties');

        if (!$properties || empty($properties->getResults())) {
            return null;
        }

        return $properties;
    }

    /**
     * Wrap getting data from array to allow returning default empty field value if given key do not exist
     *
     * @param array $array
     * @param string $key
     * @return mixed
     */
    protected function getFromArray(array $array, $key)
    {
        if (isset($array[$key])) {
            return $array[$key];
        }

        return $this->getDefaultEmptyValue();
    }

    /**
     * @param array $property
     * @return mixed
     */
    protected function getPropertyName(array $property)
    {
        $name = $property['backendName'];

        /** @var ItemProperties $properties */
        $properties = $this->registry->get('ItemProperties');
        $propertyName = $properties->getPropertyName($property['id']);

        if ($propertyName && $propertyName != $this->getDefaultEmptyValue()) {
            $name = $propertyName;
        }
        return $name;
    }

    /**
     * Get property value by its type from property array
     *
     * @param array $property
     * @return string
     */
    protected function getPropertyValue(array $property, array $productProperty)
    {
        $propertyType = $property['valueType'];
        $value = $this->getDefaultEmptyValue();

        switch ($propertyType) {
            case 'empty':
                $value = $property['backendName'];
                if ($propertyName = $this->registry->get('ItemProperties')->getPropertyName($property['id'])) {
                    $value = $propertyName;
                }
                break;
            case 'text':
                if (isset($productProperty['valueTexts'])) {
                    foreach ($productProperty['valueTexts'] as $name) {
                        if (strtoupper($name['lang']) == $this->getLanguageCode()) {
                            $value = $name['value'];
                            break;
                        }
                    }
                }
                break;
            case 'selection':
                foreach ($productProperty['propertySelection'] as $selection) {
                    if (strtoupper($selection['lang']) != $this->getLanguageCode()) {
                        continue;
                    }

                    $value = $selection['name'];
                }
                break;
            case 'int':
                $value = $productProperty['valueInt'];
                break;
            case 'float':
                $value = $productProperty['valueFloat'];
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

        return $value;
    }

    /**
     * Check if product variation should be added to import or skipped
     *
     * @param array $variation
     * @return bool
     */
    protected function shouldProcessVariation(array $variation)
    {
        if (!$variation['base']['isActive']) {
            return false;
        }

        if (isset($variation['base']['automaticListVisibility']) && $variation['base']['automaticListVisibility'] < 1) {
            return false;
        }

        if (isset($variation['base']['availableUntil']) && !$this->isDateStillAvailable($variation['base']['availableUntil'])) {
            return false;
        }

        if (!$this->isProductAvailable($variation['base']['availability'])) {
            return false;
        }

        $this->hasData = true;

        return true;
    }

    /**
     * @param string $availableUntil
     * @return bool
     */
    protected function isDateStillAvailable($availableUntil)
    {
        $date = strtotime($availableUntil);
        $currentTime = time();

        if ($date && $date < $currentTime) {
            return false;
        }

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
    protected function processVariationIdentifiers(array $variation)
    {
        $identificators = array('number', 'model', 'id', 'itemId');

        if (!$this->getField('ordernumber')) {
            $this->setField('ordernumber', array());
        }

        if ($this->getField('variation_id') == $this->getDefaultEmptyValue() || $variation['base']['isMain']) {
            $this->setField('variation_id', $variation['id']);
        }

        foreach ($identificators as $identificator) {
            $data = $identificator === 'id' ? $variation : $variation['base'];

            $this->addVariationIdentifier($this->getFromArray($data, $identificator));
        }

        if (isset($variation['barcodes'])) {
            $this->processVariationsBarcodes($variation['barcodes']);
        }

        return $this;
    }

    /**
     * Each variation can have multiple barcodes
     *
     * @param array $barcodes
     * @return $this
     */
    protected function processVariationsBarcodes(array $barcodes)
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
     * Variation units processing.
     * Map variation 'unitId' with ISO value.
     *
     * @param array $data
     * @return $this|Product
     */
    protected function processUnits($data)
    {
        if (empty($data)) {
            $this->handleEmptyData();
            return $this;
        }

        $unitId = $this->getFromArray($data, 'unitId');
        $unit = $this->getDefaultEmptyValue();

        /** @var Units $units */
        if ($units = $this->registry->get('Units')) {
            $unit = $units->getUnitValue($unitId);
        }

        $this->setField('base_unit', $unit);
        $this->setField('package_size', $this->getFromArray($data, 'content'));

        return $this;
    }

    /**
     * @param array $data
     * @return $this
     */
    protected function processTexts(array $data)
    {
        if (!isset($data['texts']) || !count($data['texts'])) {
            $this->handleEmptyData();
            return $this;
        }

        foreach ($data['texts'] as $texts) {
            if (strtoupper($texts['lang']) != $this->getLanguageCode()) {
                continue;
            }

            $this->setField('name', $this->getFromArray($texts, 'name' . $this->productNameFieldId))
                ->setField('summary', $this->getFromArray($texts, 'shortDescription'))
                ->setField('description', $this->getFromArray($texts, 'description'))
                ->setField('url', $this->getProductFullUrl($this->getFromArray($texts, 'urlPath')))
                ->setField('keywords', $this->getFromArray($texts, 'keywords'));
        }

        return $this;
    }

    /**
     * @param array $data
     * @return $this
     */
    protected function processFreeTextFields(array $data)
    {
        $freeTexts = [];
        for ($i = 0; $i <= 20; $i++) {
            if (!isset($data['free' . $i]) || $data['free' . $i] == '') {
                continue;
            }
            $freeTexts['free' . $i] = $data['free' . $i];
        }

        if (empty($freeTexts)) {
            $this->handleEmptyData();

            return $this;
        }

        foreach ($freeTexts as $key => $freeText) {
            $this->setAttributeField($key, $freeText);
        }

        return $this;
    }

    /**
     * @param array $data
     * @return $this
     */
    protected function processTags(array $data)
    {
        if (empty($data)) {
            $this->handleEmptyData();

            return $this;
        }

        $keywordsArray = [];

        $keywords = $this->getField('keywords');
        if ($keywords !== $this->getDefaultEmptyValue()) {
            $keywordsArray[] = $keywords;
        }

        foreach ($data as $tag) {
            $this->setAttributeField('cat_id', $tag['tagId']);

            $correctTagName = $tag['tag']['tagName'];

            if (!empty($tag['tag']['names'])) {
                foreach ($tag['tag']['names'] as $tagName) {
                    if ($tagName['tagLang'] === strtolower($this->getLanguageCode())) {
                        $correctTagName = $tagName['tagName'];

                        break;
                    }
                }
            }

            $keywordsArray[] = $correctTagName;
        }

        $this->setField('keywords', implode(',', $keywordsArray));

        return $this;
    }
}
