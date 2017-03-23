<?php

namespace Findologic\Plentymarkets;

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

    public function getField($key)
    {
        if (isset($this->fields[$key])) {
            return $this->fields[$key];
        }

        return null;
    }

    public function setField($key, $value, $array = false)
    {
        if ($array) {
            $this->fields[$key][] = $value;
        } else {
            $this->fields[$key] = $value;
        }

        return $this;
    }

    public function setAttributeField($name, $value)
    {
        if (!$this->fields['attributes']) {
            $this->fields['attributes'] = array();
        }

        if (isset($this->fields['attributes'][$name]) && in_array($value, $this->fields['attributes'][$name])) {
            return $this;
        }

        $this->fields['attributes'][$name][] = $value;

        return $this;
    }

    public function getItemId()
    {
        return $this->itemId;
    }

    public function processInitialData($data)
    {
        //TODO: check if id is empty and throw exception
        $this->itemId = $this->getFromArray($data, 'id');

        $this->setField('id', $this->getItemId())
            ->setField('date_added', $this->getFromArray($data, 'createdAt'))
            ->setField('position', $this->getFromArray($data, 'position'));

        $this->processTexts($data);

        return $this;
    }

    public function processVariations($data)
    {
        if (!isset($data['entries'])) {
            return $this;
        }

        foreach ($data['entries'] as $variation) {
            $this->processVariationIdentifiers($variation)
                ->processVariationPrices($this->getFromArray($variation, 'variationSalesPrices'))
                ->processVariationAttributes($this->getFromArray($variation, 'variationAttributeValues'));
        }

        return $this;
    }

    public function processImages($data)
    {
        if (!is_array($data) || empty($data)) {
            return $this;
        }

        if (!isset($data['itemId'])) {
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

    protected function getRRP()
    {
        $prices = $this->registry->get('SalesPrices');
        if (!$prices) {
            return array();
        }

        return $prices->getRRP();
    }

    /**
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
            return $this;
        }

        foreach ($data as $price) {
            if (!$this->getField('price'))  {
                $this->setField('price', $price['price']);
            } else if ($this->getField('price') > $price['price']) {
                $this->setField('price', $price['price']);
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

    protected function processVariationAttributes($attributesData)
    {
        if (!count($attributesData)) {
            return $this;
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
     * @param array $data
     * @return $this
     */
    protected function processTexts($data)
    {
        if (!isset($data['texts']) || !count($data['texts'])) {
            return $this;
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