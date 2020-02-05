<?php

declare(strict_types=1);

namespace Findologic\Plentymarkets\Helper;

use Findologic\Plentymarkets\Exception\InvalidUrlException;

class Url
{
    /**
     * @throws InvalidUrlException
     */
    public static function getHost(string $url): string
    {
        $parseUrl = parse_url(trim($url));

        if (!$parseUrl) {
            throw new InvalidUrlException('Malformed url: ' . $url);
        }

        if (!empty($parseUrl['host'])) {
            return $parseUrl['host'];
        }

        $explodedUrl = explode('/', $parseUrl['path'], 2);

        return trim(array_shift($explodedUrl));
    }
}
