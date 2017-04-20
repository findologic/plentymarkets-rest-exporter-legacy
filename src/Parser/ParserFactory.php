<?php

namespace Findologic\Plentymarkets\Parser;

use Findologic\Plentymarkets\Parser\Categories;
use Findologic\Plentymarkets\Parser\Vat;

class ParserFactory
{
    public static function create($type, $registry)
    {
        $parser = '\Findologic\Plentymarkets\Parser\\' . ucwords($type);
        if (class_exists($parser)) {
            $object = new $parser($registry);
            if (!$object instanceof ParserInterface) {
                throw new \Exception("Invalid parser type given.");
            }

            return $object;
        } else {
            throw new \Exception("Invalid parser type given.");
        }
    }
}