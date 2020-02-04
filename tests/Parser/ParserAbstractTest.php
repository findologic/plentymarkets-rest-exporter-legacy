<?php

namespace Findologic\PlentymarketsTest\Parser;

use Findologic\Plentymarkets\Exception\InvalidUrlException;
use Findologic\Plentymarkets\Parser\ParserAbstract;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ParserAbstractTest extends TestCase
{
    public function exceptionWasThrownOnMalformedUrlProvider(): array
    {
        return [
            ['http:///example.com'],
            ['http://:80'],
            ['http://user@:80']
        ];
    }

    public function correctDomainUrlProvider(): array
    {
        return [
            ['www.example.com/lt', 'www.example.com'],
            ['https://www.example.com/lt', 'www.example.com'],
            ['http://www.example.com/lt', 'www.example.com'],
            ['http://www.example.com/lt/en/de/ru', 'www.example.com'],
            ['http://www.example.com/lt/en/de/ru/a/very/long/url', 'www.example.com'],
            ['https://www.example.com', 'www.example.com'],
            ['http://www.example.com', 'www.example.com'],
            ['http://word.example.com', 'word.example.com'],
            ['http://word.example.com/lt', 'word.example.com'],
            ['http://word.example.com/lt/en/ru', 'word.example.com']
        ];
    }

    /**
     * @dataProvider correctDomainUrlProvider
     */
    public function testCorrectDomainUrlIsSet(string $domainUrl, string $expectedDomainUrl)
    {
        /** @var ParserAbstract|MockObject $parserAbstract */
        $parserAbstract = $this->getMockBuilder(ParserAbstract::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $parserAbstract->setStoreUrl($domainUrl);

        $this->assertSame($expectedDomainUrl, $parserAbstract->getStoreUrl());
    }

    /**
     * @dataProvider exceptionWasThrownOnMalformedUrlProvider
     */
    public function testExceptionWasThrownOnMalformedUrl(string $malformedDomainUrl)
    {
        /** @var ParserAbstract|MockObject $parserAbstract */
        $parserAbstract = $this->getMockBuilder(ParserAbstract::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $this->expectException(InvalidUrlException::class);

        $parserAbstract->setStoreUrl($malformedDomainUrl);
    }
}
