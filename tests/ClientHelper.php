<?php

namespace Findologic\Plentymarkets\Tests;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response;
use http\Message\Body;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionException;

trait ClientHelper
{
    /**
     * @param array|null $methods
     * @throws ReflectionException
     * @return GuzzleClient|MockObject
     */
    public function getHttpClientMock($methods = null): GuzzleClient
    {
        return $this->getMockBuilder(GuzzleClient::class)
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();
    }

    /**
     * @param array|null $methods
     * @throws ReflectionException
     */
    public function getRequestMock($methods = null): Request
    {
        return $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();
    }

    /**
     * @param $body
     * @param $status
     * @param bool $defaultHeaders
     */
    public function buildResponseMock($body, $status, $defaultHeaders = true): Response
    {
        $responseMock = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->setMethods(['getStatusCode', 'getBody', 'getReasonPhrase', 'getHeaderLine'])
            ->getMock();

        $responseBody = (new Response(200, [], $body))->getBody();
        $responseMock->expects($this->any())->method('getBody')->willReturn($responseBody);
        $responseMock->expects($this->any())->method('getStatusCode')->willReturn($status);

        if ($defaultHeaders) {
            $responseMock->expects($this->any())->method('getHeaderLine')->willReturn(5);
        }

        return $responseMock;
    }
}
