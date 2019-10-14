<?php

namespace Findologic\Plentymarkets\Wrapper;

use Findologic\Plentymarkets\Response\Product;

interface WrapperInterface
{
    /**
     * Method which writes the data to destination
     *
     * @param Product $product
     * @return mixed
     */
    public function wrapItem(Product $product);

    /**
     * Function called after all products has been processed so each wrapper
     * could execute necessary code for ending the wrapping
     *
     * @return mixed
     */
    public function allItemsHasBeenProcessed();

    /**
     * Return results of wrapping
     *
     * @return mixed
     */
    public function getResults();
}