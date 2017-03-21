<?php

namespace Findologic\Plentymarkets\Wrapper;

interface WrapperInterface
{
    /**
     * @param \Findologic\Plentymarkets\Product $product
     * @return mixed
     */
    public function wrapProduct($product);

    /**
     * @return mixed
     */
    public function getResults();
}