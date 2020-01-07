<?php

namespace Findologic\Plentymarkets\Exception;

use Exception;

/**
 * Thrown in case of malformed URLs
 * Example: domain url is malformed and parse_url() can't parse it
 *
 * Class InvalidUrlException
 * @package Findologic\Plentymarkets\Exception
 */
class InvalidUrlException extends Exception
{

}
