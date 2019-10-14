<?php

namespace Findologic\Plentymarkets\Response;

use Findologic\Plentymarkets\Client;
use Findologic\Plentymarkets\Registry;
use Psr\Http\Message\StreamInterface;

abstract class Response
{
    /**
     * @var StreamInterface
     */
    protected $stream;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var Client
     */
    protected $client;

    /**
     * Index of the response item in the stream
     *
     * @var int
     */
    protected $index;

    /**
     * @param StreamInterface $stream
     * @param Registry $registry
     * @param Client $client
     * @param int $index
     */
    public function __construct(
        StreamInterface $stream,
        Registry $registry,
        Client $client,
        $index
    ) {
        $this->stream = $stream;
        $this->registry = $registry;
        $this->client = $client;
        $this->index = $index;
    }

    /**
     * @param StreamInterface $stream
     * @return bool|resource
     */
    protected function openStream($stream) {
        $streamContext = stream_context_create([
                'http' => [
                    'header' => ['Authorization: Bearer ' . $this->client->getAccessToken()]
                ]
            ]
        );

        return fopen($stream->getMetadata()['uri'], 'r', null, $streamContext);
    }

    /**
     * @param resource $stream
     * @return bool
     */
    protected function closeStream($stream) {
        return fclose($stream);
    }
}
