<?php

namespace Findologic\Plentymarkets\Stream;

use Findologic\Plentymarkets\Exception\InternalException;
use Findologic\Plentymarkets\Product;
use Findologic\Plentymarkets\Wrapper\WrapperInterface;
use GuzzleHttp\Psr7\Response;
use JsonStreamingParser\Listener\RegexListener;
use JsonStreamingParser\Parser;
use Log4Php\Logger;

/***
 * Class GetProductsStreamer
 * @package Findologic\Plentymarkets
 */
class GetProductsStreamer implements StreamerInterface
{
    const DIRECTORY = 'stream/getproducts/';

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
     * GetProductsStreamer constructor.
     * @param Logger $log
     * @param WrapperInterface $wrapper
     */
    public function __construct(Logger $log, WrapperInterface $wrapper)
    {
        $this->log = $log;
        $this->wrapper = $wrapper;
    }

    /**
     * @param Response $response
     * @return string
     * @throws InternalException
     */
    public function streamToFile(Response $response): string
    {
        $fileName = uniqid() . '.json';

        $fileHandle = $this->createFile(self::DIRECTORY, $fileName);

        while (!$response->getBody()->eof()) {
            fwrite($fileHandle, $response->getBody()->read(1024));
        }

        fclose($fileHandle);

        return self::DIRECTORY . $fileName;
    }

    /**
     * @param resource $destination
     * @param string $streamedFileName
     * @return resource
     */
    public function streamToFileFromStreamedFile($destination, string $streamedFileName)
    {
        $source = $this->openStream($streamedFileName);

        stream_copy_to_stream($source, $destination);

        $this->closeStream($source);

        return $destination;
    }

    /**
     * @param string $directory
     * @param string $file
     * @return bool|resource
     * @throws InternalException
     */
    protected function createFile($directory, $file)
    {
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }

        if (($fileHandle = fopen($directory . DIRECTORY_SEPARATOR . $file, 'wb+')) === false ) {
            throw new InternalException('Could not create or open the file for dumping the request data for debugging!');
        }

        return $fileHandle;
    }

    /**
     * @param $streamedFileName
     * @return bool|resource
     */
    protected function openStream($streamedFileName) {
        return fopen($streamedFileName, 'r');
    }

    /**
     * @param resource $stream
     * @return bool
     */
    protected function closeStream($stream) {
        return fclose($stream);
    }

    /**
     * @param string $streamedFileName
     * @return array
     */
    public function getMetadata($streamedFileName) {
        $stream = $this->openStream($streamedFileName);

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

        $parser = new Parser($stream, $listener);
        $parser->parse();

        $this->closeStream($stream);

        return $metadata;
    }

    /**
     * @param string $streamedFileName
     * @return int
     */
    public function getProductsCount($streamedFileName) {
        $metadata = $this->getMetadata($streamedFileName);

        if (!$metadata[self::METADATA_IS_LAST_PAGE]) {
            return $metadata[self::METADATA_ITEMS_PER_PAGE];
        }

        return abs($metadata[self::METADATA_ITEMS_PER_PAGE] * $metadata[self::METADATA_PAGE] - $metadata[self::METADATA_TOTALS_COUNT]);
    }

    /**
     * @param $streamedFileName
     * @return array|null
     */
    public function getProductsIds($streamedFileName)
    {
        $callbackResult = [];

        $stream = $this->openStream($streamedFileName);

        $productIdListener = new RegexListener([
            '/entries/*/id' => function ($productId) use (&$callbackResult) {
                $callbackResult[] = $productId;
            }
        ]);

        $parser = new Parser($stream, $productIdListener);
        $parser->parse();

        $this->closeStream($stream);

        return $callbackResult;
    }

    /**
     * @param $streamedFileName
     * @param array $validItemIds
     * @param array $variations
     * @param $createProduct
     * @return array
     */
    public function processProducts($streamedFileName, $validItemIds, $variations, callable $createProduct)
    {
        $productsCount = $this->getProductsCount($streamedFileName);

        if ($productsCount <= 0) {
            return [];
        }

        $trackSkippedProductsIds = [];

        for ($i = 0; $i < $productsCount; $i++) {
            $stream = $this->openStream($streamedFileName);

            $getProductListener = new RegexListener();
            $parser = new Parser($stream, $getProductListener);
            $getProductListener->setMatch([
                '/entries/' . $i  => function ($productData) use ($createProduct, $variations, $validItemIds, &$trackSkippedProductsIds, $parser) {
                    if (!in_array($productData['id'], $validItemIds)) {
                        return;
                    }

                    /** @var Product $product */
                    $product = $createProduct($productData);

                    while (($variation = array_shift($variations[$product->getItemId()]))) {
                        $continueProcess = $product->processVariation($variation);

                        if (!$continueProcess) {
                            continue;
                        }

                        if (isset($variation['itemImages'])) {
                            $product->processImages($variation['itemImages']);
                        }

                        if (isset($variation['variationProperties'])) {
                            $product->processVariationsProperties($variation['variationProperties']);
                        }

                        if (isset($variation['properties'])) {
                            $product->processVariationSpecificProperties($variation['properties']);
                        }
                    }

                    if ($product->hasValidData()) {
                        $this->wrapper->wrapItem($product->getResults());
                    } else {
                        $trackSkippedProductsIds[] = $product->getItemId();
                    }

                    $parser->stop();
                }
            ]);

            $parser->parse();

            $this->closeStream($stream);
        }

        return $trackSkippedProductsIds;
    }

    /**
     * @param string $streamedFileName
     * @return bool
     */
    public function isResponseValid(string $streamedFileName): bool
    {
        $stream = $this->openStream($streamedFileName);

        $isEntryFound = false;

        $getProductListener = new RegexListener();
        $parser = new Parser($stream, $getProductListener);
        $getProductListener->setMatch([
            '/entries/0'  => function () use ($parser, &$isEntryFound) {
                $isEntryFound = true;

                $parser->stop();
            }
        ]);

        $parser->parse();

        $this->closeStream($stream);

        return $isEntryFound;
    }
}
