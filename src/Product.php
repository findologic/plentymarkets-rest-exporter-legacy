<?php

namespace Findologic\Plentymarkets;

use Findologic\Plentymarkets\Data\Units;
use Findologic\Plentymarkets\Parser\Attributes;
use Findologic\Plentymarkets\Registry;

class Product
{
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
        'id' => null,
        'ordernumber' => null,
        'name' => null,
        'summary' => null,
        'description' => null,
        'price' => null,
        'instead' => null,
        'maxprice' => null,
        'taxrate' => null,
        'url' => null,
        'image' => null,
        'base_unit' => null,
        'package_size' => null,
        'price_id' => null,
        'attributes' => null,
        'keywords' => null,
        'groups' => null,
        'bonus' => null,
        'sales_frequency' => null,
        'date_added' => null,
        'sort' => null,
    );

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
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

        return null;
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

    public function getItemId()
    {
        return $this->itemId;
    }

    public function getResults()
    {
        return $this->fields;
    }

    /**
     * Process initial data from /items response
     *
     * @param array $data
     * @return $this
     */
    public function processInitialData($data)
    {
        //TODO: check if id is empty and maybe throw exception
        $this->itemId = $this->getFromArray($data, 'id');

        $this->setField('id', $this->getItemId())
            ->setField('date_added', $this->getFromArray($data, 'createdAt'))
            ->setField('position', $this->getFromArray($data, 'position'));

        $this->processTexts($data);

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
                ->processVariationPrices($this->getFromArray($variation, 'variationSalesPrices'))
                ->processVariationAttributes($this->getFromArray($variation, 'variationAttributeValues'))
                ->processUnits($this->getFromArray($variation, 'unit'));
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
            $this->setAttributeField($propertyName, $value);
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

        //data for images could be returned as array of images if there is multiple images assigned
        if (!isset($data['itemId'])) {
            //TODO: check which image to use if there is multiple (last image was mentioned in the call)
            $data = $data[0];
        }

        $this->setField('image', $this->getFromArray($data, 'urlMiddle'));

        return $this;
    }

    /**
     * Wrap getting data from array to allow returning default data if key do not exist
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

        return null;
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
            case 'text':
                foreach ($property['names'] as $name) {
                    //TODO: filter by language
                    $value = $name['value'];
                }
                break;
            case 'selection':
                foreach ($property['propertySelection'] as $selection) {
                    //TODO: filter by language
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

    protected function handleEmptyData()
    {
        // TODO: maybe log the caller method name wheres the data is missing
        return $this;
    }

    /**
     * Get all the fields used for 'ordernumber'
     *
     * @param array $variation
     * @return $this
     */
    protected function processVariationIdentifiers($variation)
    {
        $identificators = array('number', 'model', 'id');

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
     * @param array $data
     * @return $this
     */
    protected function processVariationPrices($data)
    {
        if (!$data) {
            return $this->handleEmptyData();
        }

        foreach ($data as $price) {
            if (!$this->getField('price'))  {
                $this->setField('price', $price['price']);
                $this->setField('price_id', $price['salesPriceId']);
            } else if ($this->getField('price') > $price['price']) {
                $this->setField('price', $price['price']);
                $this->setField('price_id', $price['salesPriceId']);
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

        //TODO: filters texts data by language
        $texts = $data['texts']['0'];

        $this->setField('name', $this->getFromArray($texts, 'name1'));
        $this->setField('summary', $this->getFromArray($texts, 'shortDescription'));
        $this->setField('description', $this->getFromArray($texts, 'description'));
        $this->setField('url', $this->getFromArray($texts, 'urlPath'));
        $this->setField('keywords', $this->getFromArray($texts, 'keywords'));

        return $this;
    }
}