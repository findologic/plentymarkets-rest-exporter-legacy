<?php

namespace Findologic\Plentymarkets\Exception;

/**
 * Should be used for errors which causes plugin to not function properly so execution of the script should be stopped.
 * Example: can't create results file to write the results data
 *
 * Class CriticalException
 * @package Findologic\Plentymarkets\Exception
 */
class CriticalException extends \Exception
{
}