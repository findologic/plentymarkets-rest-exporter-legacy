<?php

namespace Findologic\Plentymarkets\Wrapper;

use Findologic\Plentymarkets\Wrapper\WrapperInterface;

class Csv implements WrapperInterface
{
    /**
     * @inheritdoc
     */
     public function wrapProduct($product)
     {
         // TODO: Implement wrapResults() method.
         return $this;
     }

    /**
     * @inheritdoc
     */
     public function getResults()
     {
         // TODO: Implement getResults() method.
     }
}