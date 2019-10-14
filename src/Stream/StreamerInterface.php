<?php

namespace Findologic\Plentymarkets\Stream;

use Psr\Http\Message\StreamInterface;

interface StreamerInterface
{
    public function isResponseValid(StreamInterface $stream): bool;
}
