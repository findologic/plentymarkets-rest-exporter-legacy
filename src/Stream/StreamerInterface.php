<?php

namespace Findologic\Plentymarkets\Stream;

use GuzzleHttp\Psr7\Response;

interface StreamerInterface
{
    /**
     * @param Response $response
     * @return string
     */
    public function streamToFile(Response $response): string;

    /**
     * @param resource $destination
     * @param string $streamedFileName
     * @return resource
     */
    public function streamToFileFromStreamedFile($destination, string $streamedFileName);

    /**
     * @param string $streamedFileName
     * @return bool
     */
    public function isResponseValid(string $streamedFileName): bool;
}
