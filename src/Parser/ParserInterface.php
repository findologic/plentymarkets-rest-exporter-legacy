<?php

namespace Findologic\Plentymarkets\Parser;

interface ParserInterface
{
    /**
     * @return array
     */
    public function getResults();

    /**
     * @param array $data
     * @return array
     */
    public function parse($data);
}