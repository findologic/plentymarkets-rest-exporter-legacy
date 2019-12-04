<?php

namespace Findologic\Plentymarkets\Response;

use Findologic\Plentymarkets\Client;
use Findologic\Plentymarkets\Parser\Units;
use Findologic\Plentymarkets\Parser\ItemProperties;
use Findologic\Plentymarkets\Parser\Properties;
use Findologic\Plentymarkets\Registry;
use JsonStreamingParser\Listener\RegexListener;
use JsonStreamingParser\Parser;
use Psr\Http\Message\StreamInterface;

class Product extends Response
{
    const CATEGORY_ATTRIBUTE_FIELD = 'cat';
    const CATEGORY_URLS_ATTRIBUTE_FIELD = 'cat_url';
    const MANUFACTURER_ATTRIBUTE_FIELD = 'vendor';

    protected $fieldNames = [
        'id',
        'ordernumber',
        'name',
        'summary',
        'description',
        'price',
        'instead',
        'maxprice',
        'taxrate',
        'url',
        'image',
        'base_unit',
        'package_size',
        'price_id',
        'attributes',
        'keywords',
        'groups',
        'bonus',
        'sales_frequency',
        'date_added',
        'sort',
        'main_variation_id',
        'variation_id'
    ];

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
        'main_variation_id' => '',
        'variation_id' => ''
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
     * Holds the parsed values
     *
     * @var array
     */
    protected $results = array();

    /**
     * @var string
     */
    protected $storeUrl = '';

    /**
     * @var string
     */
    protected $languageCode = '';

    /**
     * @var string
     */
    protected $countryCode = '';

    /**
     * @var bool|int
     */
    protected $storePlentyId;

    /**
     * Product constructor.
     * @param StreamInterface $stream
     * @param Registry $registry
     * @param Client $client
     * @param int $index
     */
    public function __construct(StreamInterface $stream, Registry $registry, Client $client, int $index)
    {
        parent::__construct($stream, $registry, $client, $index);

        $this->itemId = $this->getIdFromStream($index);
        $this->index = $index;

        $this->processManufacturer($this->getFromStream('manufacturerId'));

        return $this;
    }

    /**
     * @return array
     */
    public function getFieldNames()
    {
        return $this->fieldNames;
    }

    /**
     * @return string|null
     */
    public function getProductPositionInStream()
    {
        $openedStream = $this->openStream($this->stream);

        $callbackResult = null;

        $getProductListener = new RegexListener();
        $parser = new Parser($openedStream, $getProductListener);
        $getProductListener->setMatch([
            "(/entries/.*/id)" => function ($result, $path) use ($parser, &$callbackResult) {
                if ($result === $this->itemId) {
                    $callbackResult = explode('/', $path)[2];

                    $parser->stop();
                }
            }
        ]);

        $parser->parse();

        $this->closeStream($openedStream);

        return $callbackResult;
    }

    public function getIdFromStream($index)
    {
        $openedStream = $this->openStream($this->stream);

        $callbackResult = null;

        $getProductListener = new RegexListener();
        $parser = new Parser($openedStream, $getProductListener);
        $getProductListener->setMatch([
            "/entries/$index/id" => function ($result) use ($parser, &$callbackResult) {
                $callbackResult = $result;

                $parser->stop();
            }
        ]);

        $parser->parse();

        $this->closeStream($openedStream);

        return $callbackResult;
    }

    public function getFromStream($field)
    {
        $positionInStream = $this->getProductPositionInStream();

        $openedStream = $this->openStream($this->stream);

        $callbackResult = null;

        $getProductListener = new RegexListener();
        $parser = new Parser($openedStream, $getProductListener);
        $getProductListener->setMatch([
            "(/entries/$positionInStream/$field)" => function ($result) use ($parser, &$callbackResult) {
                $callbackResult = $result;

                $parser->stop();
            }
        ]);

        $parser->parse();

        $this->closeStream($openedStream);

        return $callbackResult;
    }

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

        if ($variation['isMain'] || $this->getField('sort') === '') {
            $this->setField('sort', $this->getFromArray($variation, 'position'));
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
            ->processUnits($this->getFromArray($variation, 'unit'))
            ->processTags($this->getFromArray($variation, 'tags'));

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
     * Process variation properties
     * Some properties (empty type) have mixed up value for actual property name and value
     *
     * @param array $data
     * @return $this
     */
    public function processVariationsProperties(array $data)
    {
        if (!is_array($data) || empty($data)) {
            $this->handleEmptyData();
            return $this;
        }

        foreach ($data as $property) {
            if (isset($property['property']['isSearchable']) && !$property['property']['isSearchable']) {
                continue;
            }

            if (
                $property['property']['valueType'] === 'empty' &&
                (!isset($property['property']['propertyGroupId']) || empty($property['property']['propertyGroupId']))
            ) {
                continue;
            }

            $value = $this->getPropertyValue($property);

            // If there is no valid value for the property, use its name as value and the group name as the
            // property name.
            // Properties of type "empty" are a special case since they never have a value of their own.
            if ($property['property']['valueType'] === 'empty') {
                $propertyName = $this->getPropertyGroupForPropertyName($property['property']['propertyGroupId']);
            } elseif ($value === $this->getDefaultEmptyValue()) {
                $propertyName = $this->getPropertyGroupForPropertyName($property['property']['propertyGroupId']);
                $value = $this->getPropertyName($property);
            } else {
                $propertyName = $this->getPropertyName($property);
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
    public function processVariationSpecificProperties($data)
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
            if ($property['relationTypeIdentifier'] != ItemProperties::PROPERTY_TYPE_ITEM) {
                continue;
            }

            $propertyName = $properties->getPropertyName($property['propertyId']);
            $value = null;

            if ($property['propertyRelation']['cast'] == 'empty') {
                $value = $propertyName;
                $propertyName = $properties->getPropertyGroupName($property['propertyId']);
            } else if ($property['propertyRelation']['cast'] == 'shortText' || $property['propertyRelation']['cast'] == 'longText') {
                foreach ($property['relationValues'] as $relationValue) {
                    if (strtoupper($relationValue['lang']) != strtoupper($this->getLanguageCode())) {
                        continue;
                    }

                    $value = $relationValue['value'];
                }
            } else if ($property['propertyRelation']['cast'] == 'selection') {
                $value = $properties->getPropertySelectionValue($property['propertyId'], $property['relationValues'][0]['value']);
            } else {
                $value = $property['relationValues'][0]['value'];
            }

            if ($propertyName != null && $value != "null" && $value != null && $value != $this->getDefaultEmptyValue()) {
                $this->setAttributeField($propertyName, $value);
            }
        }

        return $this;
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
        $name = $property['property']['backendName'];

        /** @var ItemProperties $properties */
        $properties = $this->registry->get('ItemProperties');
        $propertyName = $properties->getPropertyName($property['property']['id']);

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
    protected function getPropertyValue(array $property)
    {
        $propertyType = $property['property']['valueType'];
        $value = $this->getDefaultEmptyValue();

        switch ($propertyType) {
            case 'empty':
                $value = $property['property']['backendName'];
                if ($propertyName = $this->registry->get('ItemProperties')->getPropertyName($property['property']['id'])) {
                    $value = $propertyName;
                }
                break;
            case 'text':
                // For some specific shops the structure of text property is different and do not have 'names' field
                if (isset($property['names'])) {
                    foreach ($property['names'] as $name) {
                        if (strtoupper($name['lang']) == $this->getLanguageCode()) {
                            $value = $name['value'];
                            break;
                        }
                    }
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
        if ($variation['isActive'] === false) {
            return false;
        }

        if (isset($variation['automaticListVisibility']) && $variation['automaticListVisibility'] < 1) {
            return false;
        }

        if (isset($variation['availableUntil']) && !$this->isDateStillAvailable($variation['availableUntil'])) {
            return false;
        }

        if (!$this->isProductAvailable($variation['availability'])) {
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

        if ($this->getField('variation_id') == $this->getDefaultEmptyValue() || $variation['isMain']) {
            $this->setField('variation_id', $variation['id']);
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
            if ($tag['tagType'] !== 'variation') {
                continue;
            }

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

    /**
     * Set results, only should be used for testing and mocking
     *
     * @param array $data
     * @return $this
     */
    public function setResults(array $data)
    {
        $this->results = $data;

        return $this;
    }

    /**
     * Get method name which is missing some data and pass the message to log class
     *
     * @param string $additionalInfo
     * @return $this
     */
    protected function handleEmptyData($additionalInfo = '')
    {
        /**
         * @var \Logger $log
         */
        if ($this->registry && ($log = $this->registry->get('log'))) {
            $method = debug_backtrace();
            $method = $method[1]['function'];
            $message = 'Class ' . get_class($this) . ' method: ' . $method . ' is missing some data.';

            if ($additionalInfo) {
                $message .= ' Class message: ' . $additionalInfo;
            }

            $log->trace($message);
        }

        return $this;
    }

    /**
     * @param string $lang
     * @return $this
     */
    public function setLanguageCode($lang)
    {
        $this->languageCode = strtoupper($lang);

        return $this;
    }

    /**
     * @codeCoverageIgnore - Ignore this method as it used for better mocking
     */
    public function getLanguageCode()
    {
        return $this->languageCode;
    }

    /**
     * @param string $country
     * @return $this
     */
    public function setTaxRateCountryCode($country)
    {
        $this->countryCode = strtoupper($country);

        return $this;
    }

    /**
     * @codeCoverageIgnore - Ignore this method as it used for better mocking
     */
    public function getTaxRateCountryCode()
    {
        return $this->countryCode;
    }

    /**
     * @param string $url
     * @return $this
     */
    public function setStoreUrl($url)
    {
        $this->storeUrl = rtrim($url, '/');

        return $this;
    }

    /**
     * @codeCoverageIgnore - Ignore this method as it used for better mocking
     */
    public function getStoreUrl()
    {
        return $this->storeUrl;
    }

    /**
     * @param int $storePlentyId
     * @return $this
     */
    public function setStorePlentyId($storePlentyId)
    {
        $this->storePlentyId = $storePlentyId;

        return $this;
    }

    /**
     * @codeCoverageIgnore - Ignore this method as it used for better mocking
     */
    public function getStorePlentyId()
    {
        return $this->storePlentyId;
    }

    /**
     * @codeCoverageIgnore - Ignore this method as it used for better mocking
     */
    public function getDefaultEmptyValue()
    {
        return '';
    }

    /**
     * @return string
     */
    public function getFieldId()
    {
        return $this->itemId;
    }

    /**
     * @return string
     */
    public function getFieldOrdernumber()
    {
        return $this->fields['ordernumber'];
    }

    /**
     * @return string
     */
    public function getFieldName()
    {
        $name = '';

        $textsResponse = $this->getFromStream('texts');

        if (!isset($textsResponse) || !count($textsResponse)) {
            $this->handleEmptyData();

            return '';
        }

        foreach ($textsResponse as $texts) {
            if (strtoupper($texts['lang']) != $this->getLanguageCode()) {
                continue;
            }

            $name = $texts['name' . $this->productNameFieldId];

            break;
        }

        return $name;
    }

    /**
     * @return string
     */
    public function getFieldSummary()
    {
        $summary = '';

        $textsResponse = $this->getFromStream('texts');

        if (!isset($textsResponse) || !count($textsResponse)) {
            $this->handleEmptyData();

            return '';
        }

        foreach ($textsResponse as $texts) {
            if (strtoupper($texts['lang']) != $this->getLanguageCode()) {
                continue;
            }

            $summary = $texts['shortDescription'];

            break;
        }

        return $summary;
    }

    /**
     * @return string
     */
    public function getFieldDescription()
    {
        $description = '';

        $textsResponse = $this->getFromStream('texts');

        if (!isset($textsResponse) || !count($textsResponse)) {
            $this->handleEmptyData();

            return '';
        }

        foreach ($textsResponse as $texts) {
            if (strtoupper($texts['lang']) != $this->getLanguageCode()) {
                continue;
            }

            $description = $texts['description'];

            break;
        }

        return $description;
    }

    /**
     * @return float
     */
    public function getFieldPrice()
    {
        return $this->fields['price'];
    }

    /**
     * @return float
     */
    public function getFieldInstead()
    {
        return $this->fields['instead'];
    }

    /**
     * @return null
     */
    public function getFieldMaxprice()
    {
        return null;
    }

    /**
     * @return string
     */
    public function getFieldTaxrate()
    {
        return $this->fields['taxrate'];
    }

    /**
     * @return string
     */
    public function getFieldUrl()
    {
        $url = '';

        $textsResponse = $this->getFromStream('texts');

        if (!isset($textsResponse) || !count($textsResponse)) {
            $this->handleEmptyData();

            return '';
        }

        foreach ($textsResponse as $texts) {
            if (strtoupper($texts['lang']) != $this->getLanguageCode()) {
                continue;
            }

            $url = $this->getProductFullUrl($texts['urlPath']);
            break;
        }

        return $url;
    }

    /**
     * @return string
     */
    public function getFieldImage()
    {
        return $this->fields['image'];
    }

    /**
     * @return string
     */
    public function getFieldBaseUnit()
    {
        return $this->fields['base_unit'];
    }

    /**
     * @return string
     */
    public function getFieldPackageSize()
    {
        return $this->fields['package_size'];
    }

    /**
     * @return string
     */
    public function getFieldPriceId()
    {
        return $this->fields['price_id'];
    }

    /**
     * @return string
     */
    public function getFieldAttributes()
    {
        return $this->fields['attributes'];
    }

    /**
     * @return string
     */
    public function getFieldKeywords()
    {
        return $this->fields['keywords'];
    }

    /**
     * @return string
     */
    public function getFieldGroups()
    {
        return $this->fields['groups'];
    }

    /**
     * @return null
     */
    public function getFieldBonus()
    {
        return null;
    }

    /**
     * @return string
     */
    public function getFieldSalesFrequency()
    {
        return $this->fields['sales_frequency'];
    }

    /**
     * @return int
     */
    public function getFieldDateAdded()
    {
        return strtotime($this->getFromStream('createdAt'));
    }

    /**
     * @return string
     */
    public function getFieldSort()
    {
        return $this->fields['sort'];
    }

    /**
     * @return null
     */
    public function getFieldMainVariationId()
    {
        return null;
    }

    /**
     * @return string
     */
    public function getFieldVariationId()
    {
        return $this->fields['variation_id'];
    }
}