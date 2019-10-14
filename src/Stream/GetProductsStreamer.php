<?php

namespace Findologic\Plentymarkets\Stream;

use Findologic\Plentymarkets\Client;
use Findologic\Plentymarkets\Wrapper\WrapperInterface;
use JsonStreamingParser\Listener\RegexListener;
use JsonStreamingParser\Parser;
use Log4Php\Logger;
use Psr\Http\Message\StreamInterface;

class GetProductsStreamer implements StreamerInterface
{
    const METADATA_PAGE = 'page';
    const METADATA_TOTALS_COUNT = 'totalsCount';
    const METADATA_IS_LAST_PAGE = 'isLastPage';
    const METADATA_LAST_PAGE_NUMBER = 'lastPageNumber';
    const METADATA_FIRST_ON_PAGE = 'firstOnPage';
    const METADATA_LAST_ON_PAGE = 'lastOnPage';
    const METADATA_ITEMS_PER_PAGE = 'itemsPerPage';

    /**
     * @var Logger
     */
    protected $log;

    /**
     * @var WrapperInterface
     */
    protected $wrapper;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @param Logger $log
     * @param WrapperInterface $wrapper
     * @param Client $client
     */
    public function __construct(Logger $log, WrapperInterface $wrapper, Client $client)
    {
        $this->log = $log;
        $this->wrapper = $wrapper;
        $this->client = $client;
    }

    /**
     * @return bool|resource
     */
    protected function openStream(StreamInterface $stream)
    {
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
     */
    protected function closeStream($stream): bool
    {
        return fclose($stream);
    }

    public function getMetadata(StreamInterface $stream): array
    {
        $openedStream = $this->openStream($stream);

        $metadata = [];

        $listener = new RegexListener([
            '/' . self::METADATA_PAGE => function ($page) use (&$metadata) {
                $metadata[self::METADATA_PAGE] = $page;
            },
            '/' . self::METADATA_TOTALS_COUNT => function ($totalsCount) use (&$metadata) {
                $metadata[self::METADATA_TOTALS_COUNT] = $totalsCount;
            },
            '/' . self::METADATA_IS_LAST_PAGE => function ($isLastPage) use (&$metadata) {
                $metadata[self::METADATA_IS_LAST_PAGE] = $isLastPage;
            },
            '/' . self::METADATA_LAST_PAGE_NUMBER => function ($lastPageNumber) use (&$metadata) {
                $metadata[self::METADATA_LAST_PAGE_NUMBER] = $lastPageNumber;
            },
            '/' . self::METADATA_FIRST_ON_PAGE => function ($firstOnPage) use (&$metadata) {
                $metadata[self::METADATA_FIRST_ON_PAGE] = $firstOnPage;
            },
            '/' . self::METADATA_LAST_ON_PAGE => function ($lastOnPage) use (&$metadata) {
                $metadata[self::METADATA_LAST_ON_PAGE] = $lastOnPage;
            },
            '/' . self::METADATA_ITEMS_PER_PAGE => function ($itemsPerPage) use (&$metadata) {
                $metadata[self::METADATA_ITEMS_PER_PAGE] = $itemsPerPage;
            }
        ]);

        $parser = new Parser($openedStream, $listener);
        $parser->parse();

        $this->closeStream($openedStream);

        return $metadata;
    }

    public function getProductsCount(StreamInterface $stream): int
    {
        $metadata = $this->getMetadata($stream);

        if (!$metadata[self::METADATA_IS_LAST_PAGE]) {
            return $metadata[self::METADATA_ITEMS_PER_PAGE];
        }

        return abs($metadata[self::METADATA_ITEMS_PER_PAGE] * $metadata[self::METADATA_PAGE] - $metadata[self::METADATA_TOTALS_COUNT]);
    }

    public function getProductsIds(StreamInterface $stream): array
    {
        $callbackResult = [];

        $openedStream = $this->openStream($stream);

        $productIdListener = new RegexListener([
            '/entries/*/id' => function ($productId) use (&$callbackResult) {
                $callbackResult[] = $productId;
            }
        ]);

        $parser = new Parser($openedStream, $productIdListener);
        $parser->parse();

        $this->closeStream($openedStream);

        return $callbackResult;
    }

    public function isResponseValid(StreamInterface $stream): bool
    {
        $openedStream = $this->openStream($stream);

        $isEntryFound = false;

        $getProductListener = new RegexListener();
        $parser = new Parser($openedStream, $getProductListener);
        $getProductListener->setMatch([
            '/entries/0'  => function () use ($parser, &$isEntryFound) {
                $isEntryFound = true;

                $parser->stop();
            }
        ]);

        $parser->parse();

        $this->closeStream($openedStream);

        return $isEntryFound;
    }
}
