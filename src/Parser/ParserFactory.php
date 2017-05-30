<?php

namespace Findologic\Plentymarkets\Parser;

use \Findologic\Plentymarkets\Parser\ParserInterface;

class ParserFactory
{
    /**
     * Create parser object by given type
     *
     * @param string $type
     * @param \Findologic\Plentymarkets\Registry $registry
     * @return \Findologic\Plentymarkets\Parser\ParserAbstract
     * @throws \Exception
     */
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