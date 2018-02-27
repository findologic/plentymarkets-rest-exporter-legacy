<?php

namespace Findologic\Plentymarkets\Wrapper;

interface WrapperInterface
{
    /**
     * Method which writes the data to destination
     *
     * @param array $data
     * @return mixed
     */
    public function wrapItem(array $data);

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