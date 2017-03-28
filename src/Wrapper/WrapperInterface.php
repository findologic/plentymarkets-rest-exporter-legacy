<?php

namespace Findologic\Plentymarkets\Wrapper;

interface WrapperInterface
{
    /**
     * @param array
     * @return mixed
     */
    public function wrapItem($data);

    /**
     * Function called after all products has been processed so each wrapper
     * could execute neccessary code for ending the wrapping
     *
     * @return mixed
     */
    public function allItemsHasBeenProcessed();

    /**
     * @return mixed
     */
    public function getResults();
}