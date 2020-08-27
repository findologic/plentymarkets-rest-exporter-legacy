<?php

namespace Findologic\Plentymarkets\Tests;

trait MockResponseHelper
{
    public function getMockResponse(string $path): array
    {
        return json_decode($this->getRawMockResponse($path), true);
    }

    public function getRawMockResponse(string $path): string
    {
        return file_get_contents(__DIR__ . '/MockResponses' . $path);
    }
}
