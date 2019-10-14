<?php

namespace Findologic\PlentymarketsTest;

use Exception;
use Findologic\Plentymarkets\Client;
use Findologic\Plentymarkets\Debugger;
use Findologic\Plentymarkets\Exception\AuthorizationException;
use Findologic\Plentymarkets\Exception\CriticalException;
use Findologic\Plentymarkets\Exception\CustomerException;
use Findologic\Plentymarkets\Exception\ThrottlingException;
use Findologic\Plentymarkets\Tests\ClientHelper;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Log4Php\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PlentyConfig;
use ReflectionClass;

class ClientTest extends TestCase
{
    use ClientHelper;

    /**
     * Test when login request was successful and API returns the token
     */
    public function testLogin()
    {
        $clientMock = $this->getClientMock(['call']);

        $body = '{"accessToken":"TEST_TOKEN","tokenType":"Bearer","expiresIn":86400,"refreshToken":"REFERSH_TOKEN"}';
        $responseMock = $this->buildResponseMock($body, 200);

        $clientMock->expects($this->once())->method('call')->will($this->returnValue($responseMock));
        $clientMock->login();

        $this->assertEquals('TEST_TOKEN', $clientMock->getAccessToken());
    }

    public function testRefreshLogin()
    {
        $clientMock = $this->getClientMock(['call', 'getEndpoint']);

        $body = '{"accessToken":"TEST_TOKEN","tokenType":"Bearer","expiresIn":86400,"refreshToken":"REFERSH_TOKEN"}';
        $responseMock = $this->buildResponseMock($body, 200);

        $clientMock->expects($this->once())->method('getEndpoint')->with('login/refresh')->willReturn('http://testing.com/rest/login/refresh');
        $clientMock->expects($this->once())->method('call')->with('POST', 'http://testing.com/rest/login/refresh')->willReturn($responseMock);
        $clientMock->refreshLogin();

        $this->assertEquals('TEST_TOKEN', $clientMock->getAccessToken());
        $this->assertEquals('REFERSH_TOKEN', $clientMock->getRefreshToken());
    }

    public function testTokensAreRefreshed()
    {
        $logMock = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods(['warning', 'info', 'alert'])
            ->getMock();

        $configMock = $this->getMockBuilder(PlentyConfig::class)
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'getDomain',
                    'getUsername',
                    'getPassword',
                    'getWsdlUrl',
                    'getLanguage',
                    'getMultishopId',
                    'getAvailabilityId',
                    'getPriceId',
                    'getRrpId',
                    'getCountry'
                ]
            )->getMock();

        $httpClientMock = $this->getHttpClientMock(['send']);

        $clientMock = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([$configMock, $logMock, $logMock, $httpClientMock])
            ->setMethods(['getUrl'])
            ->getMock();

        $refreshBody = '{"accessToken":"TEST_TOKEN","tokenType":"Bearer","expiresIn":86400,"refreshToken":"REFERSH_TOKEN"}';
        $refreshResponse = $this->buildResponseMock($refreshBody, 200);

        $webstoresUnauthorizedResponse = $this->buildResponseMock('Failed', 401);
        $webstoresUnauthorizedResponse->expects($this->any())->method('getReasonPhrase')->willReturn('Unauthorized');

        $webstoresAuthorizedResponse = $this->buildResponseMock('{"test": "success"}', 200);

        $httpClientMock->expects($this->at(0))->method('send')->willReturn($webstoresUnauthorizedResponse);
        $httpClientMock->expects($this->at(1))->method('send')->willReturn($refreshResponse);
        $httpClientMock->expects($this->at(2))->method('send')->willReturn($webstoresAuthorizedResponse);

        $clientMock->expects($this->any())->method('getUrl')->willReturn('test.com/');

        $clientMock->getWebstores();

        $this->assertEquals('TEST_TOKEN', $clientMock->getAccessToken());
        $this->assertEquals('REFERSH_TOKEN', $clientMock->getRefreshToken());
    }

    /**
     * Test when login request should change the protocol used
     */
    public function testLoginProtocol()
    {
        $clientMock = $this->getClientMock(['call']);

        $body = '{"accessToken":"TEST_TOKEN","tokenType":"Bearer","expiresIn":86400,"refreshToken":"REFERSH_TOKEN"}';
        $responseMock = $this->buildResponseMock($body, 200);

        $clientMock->expects($this->exactly(2))->method('call')->will($this->onConsecutiveCalls($this->throwException(new Exception()), $responseMock));
        $clientMock->login();

        $this->assertEquals('TEST_TOKEN', $clientMock->getAccessToken());
    }

    /**
     * Exception should be thrown when response status is incorrect
     */
    public function testLoginResponseStatusException()
    {
        $clientMock = $this->getClientMock(['call']);

        $body = 'No response!';
        $responseMock = $this->buildResponseMock($body, 400);

        $clientMock->expects($this->exactly(2))->method('call')->will($this->returnValue($responseMock));

        $this->expectException(CriticalException::class);

        $clientMock->login();
    }

    /**
     * Exception should be thrown when response do not have access token
     */
    public function testLoginAccessTokenException()
    {
        $clientMock = $this->getClientMock(['call']);

        $body = '{"tokenType":"Bearer","expiresIn":86400,"refreshToken":"REFERSH_TOKEN"}';
        $responseMock = $this->buildResponseMock($body, 200);

        $clientMock->expects($this->once())->method('call')->will($this->returnValue($responseMock));

        $this->expectException(CriticalException::class);

        $clientMock->login();
    }

    /**
     * Get products API call
     */
    public function testGetProducts()
    {
        $testStreamedFileName = 'test.json';

        $clientMock = $this->getClientMock(['call']);
        $clientMock->expects($this->once())->method('call')->willReturn($testStreamedFileName);
        $clientMock->setItemsPerPage(50)->setpage(1);

        $this->assertSame($testStreamedFileName, $clientMock->getProducts('EN'));
    }

    /**
     * Test call to API when calls fails but last call is successful
     */
    public function testCallRetrySuccess()
    {
        $successResponse = $this->buildResponseMock('{"Test": "Test"}', 200);
        $failedResponse = $this->buildResponseMock('Failed', 404);

        $maxRetries = Client::RETRY_COUNT;

        $logMock = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods(['warning', 'info', 'alert'])
            ->getMock();

        $configMock = $this->getMockBuilder(PlentyConfig::class)
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'getDomain',
                    'getUsername',
                    'getPassword',
                    'getWsdlUrl',
                    'getLanguage',
                    'getMultishopId',
                    'getAvailabilityId',
                    'getPriceId',
                    'getRrpId',
                    'getCountry'
                ]
            )->getMock();

        $httpClientMock = $this->getHttpClientMock(['send']);

        //Fail for four out of five times, so we can succeed on final attempt.
        for($i = 0; $i < $maxRetries - 1; $i++) {
            $httpClientMock->expects($this->at($i))->method('send')->willReturn($failedResponse);
        }

        $httpClientMock->expects($this->at(($maxRetries - 1)))->method('send')->willReturn($successResponse);

        $clientMock = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([$configMock, $logMock, $logMock, $httpClientMock])
            ->setMethods(['createRequest', 'getUrl'])
            ->getMock();
        $clientMock->expects($this->any())->method('getUrl')->willReturn('test.com/');
        $clientMock->expects($this->any())->method('createRequest')->willReturn($this->getRequestMock());

        $this->assertSame(['Test' => 'Test'], $clientMock->getCategories());
    }

    /**
     * Test handling failed API call when retry max count is reached
     */
    public function testCallRetryFailed()
    {
        $failedResponse = $this->buildResponseMock('Failed', 404);

        $debugMock = $this->getMockBuilder(Debugger::class)->disableOriginalConstructor()->getMock();
        $logMock = $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->getMock();
        $configMock = $this->getMockBuilder(PlentyConfig::class)->setMethods(['getDomain'])->getMock();
        $httpClientMock = $this->getHttpClientMock(['send']);
        $httpClientMock->expects($this->any())->method('send')->willReturn($failedResponse);

        $clientMock = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([$configMock, $logMock, $logMock, $httpClientMock, $debugMock])
            ->setMethods(['createRequest', 'getUrl'])
            ->getMock();

        $clientMock->expects($this->once())->method('createRequest')->willReturn($this->getRequestMock());

        $this->expectException(CustomerException::class);

        $clientMock->getProductVariations(['1'], '123');
    }

    /**
     * Test if debugger is called and critical exception should be thrown if response return 401
     */
    public function testCallInvalidLogin()
    {
        $debugMock = $this->getMockBuilder(Debugger::class)
            ->disableOriginalConstructor()
            ->getMock();

        $failedResponse = $this->buildResponseMock('Failed', 401);
        $failedResponse->expects($this->any())->method('getReasonPhrase')->willReturn('Unauthorized');

        // Check if debugger is called
        $debugMock->expects($this->atMost(5))->method('debugCall');
        $logMock = $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->getMock();
        $configMock = $this->getMockBuilder(PlentyConfig::class)->setMethods(['getDomain'])->getMock();
        $httpClientMock = $this->getHttpClientMock(['send']);
        $httpClientMock->expects($this->any())->method('send')->willReturn($failedResponse);

        $clientMock = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([$configMock, $logMock, $logMock, $httpClientMock, $debugMock])
            ->setMethods(['createRequest', 'getLoginFlag'])
            ->getMock();

        $clientMock->expects($this->any())->method('getLoginFlag')->willReturn(true);
        $clientMock->expects($this->any())->method('createRequest')->willReturn($this->getRequestMock());

        $this->expectException(AuthorizationException::class);

        $clientMock->getProductVariations(['1']);
    }

    /**
     * Test if correct request object was created by provided data
     */
    public function testCreateRequest()
    {
        $clientMock = $this->getClientMock(['handleException', 'getAccessToken', 'login']);
        // Set return value to false so method would call login() which sets the token
        $clientMock->expects($this->at(0))->method('getAccessToken')->will($this->returnValue(false));
        $clientMock->expects($this->once())->method('login');
        // Token was set by login() method
        $clientMock->expects($this->at(1))->method('getAccessToken')->will($this->returnValue('TEST_TOKEN'));

        // To test protected method create reflection class
        $reflection = new ReflectionClass(get_class($clientMock));
        $method = $reflection->getMethod('createRequest');
        $method->setAccessible(true);

        $parameters = [
            'POST',
            'http://test.com/rest/method',
            ['name' => 'test']
        ];

        $request =  $method->invokeArgs($clientMock, $parameters);

        $this->assertInstanceOf(Request::class, $request);
        // Validate if correct URL is set
        $this->assertSame('http://test.com/rest/method', $request->getUri()->__toString());
    }

    /**
     * Very basic test to make sure that REST call timing debug method is called.
     */
    public function testTiming()
    {
        $successResponse = $this->buildResponseMock('{"Test": "Test"}', 200);

        $debugMock = $this->getMockBuilder(Debugger::class)->disableOriginalConstructor()->getMock();
        $debugMock->expects($this->once())->method('logCallTiming');
        $logMock = $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->getMock();
        $configMock = $this->getMockBuilder(PlentyConfig::class)->setMethods(['getDomain'])->getMock();
        $httpClientMock = $this->getHttpClientMock(['send']);
        $httpClientMock->expects($this->once())->method('send')->willReturn($successResponse);

        $clientMock = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([$configMock, $logMock, $logMock, $httpClientMock, $debugMock])
            ->setMethods(['createRequest', 'getUrl'])
            ->getMock();

        $clientMock->expects($this->once())->method('createRequest')->willReturn($this->getRequestMock());

        $clientMock->getCategories();
    }

    public function testGetPropertiesReturnsEmptyArrayOnCustomerException()
    {
        $clientMock = $this->getClientMock(['call']);
        $clientMock->expects($this->once())->method('call')->will($this->throwException(new CustomerException()));

        $this->assertSame($clientMock->getProperties(), []);
    }

    public function testGetPropertiesThrowsThrottlingException()
    {
        $clientMock = $this->getClientMock(['call']);
        $clientMock->expects($this->once())->method('call')->will($this->throwException(new ThrottlingException()));
        $this->expectException(ThrottlingException::class);

        $clientMock->getProperties();
    }

    /**
     * Should throw exception if api method requires permissions (403 status code is returned)
     */
    public function testApiMethodNeedsPermissions()
    {
        $response = $this->buildResponseMock('Access denied!', 403, false);
        $response->expects($this->any())->method('getHeader')->willReturn('1');

        $logMock = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods(['warning', 'info', 'alert'])
            ->getMock();

        $configMock = $this->getMockBuilder(PlentyConfig::class)
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'getDomain',
                    'getUsername',
                    'getPassword',
                    'getWsdlUrl',
                    'getLanguage',
                    'getMultishopId',
                    'getAvailabilityId',
                    'getPriceId',
                    'getRrpId',
                    'getCountry'
                ]
            )->getMock();

        $httpClientMock = $this->getHttpClientMock(['send']);
        $httpClientMock->expects($this->any())->method('send')->willReturn($response);

        $clientMock = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([$configMock, $logMock, $logMock, $httpClientMock])
            ->setMethods(['createRequest'])
            ->getMock();
        $clientMock->expects($this->once())->method('createRequest')->willReturn($this->getRequestMock());

        $this->expectException(CustomerException::class);

        $clientMock->getAttributes();
    }

    public function testApiMethodResponseBodyIsEmpty()
    {
        $response = $this->buildResponseMock('', 200, false);
        $response->expects($this->any())->method('getHeader')->willReturn('1');

        $logMock = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods(['warning', 'info', 'alert'])
            ->getMock();

        $configMock = $this->getMockBuilder(PlentyConfig::class)
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'getDomain',
                    'getUsername',
                    'getPassword',
                    'getWsdlUrl',
                    'getLanguage',
                    'getMultishopId',
                    'getAvailabilityId',
                    'getPriceId',
                    'getRrpId',
                    'getCountry'
                ]
            )->getMock();

        $httpClientMock = $this->getHttpClientMock(['send']);
        $httpClientMock->expects($this->any())->method('send')->willReturn($response);

        $clientMock = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([$configMock, $logMock, $logMock, $httpClientMock])
            ->setMethods(['createRequest'])
            ->getMock();
        $clientMock->expects($this->once())->method('createRequest')->willReturn($this->getRequestMock());

        $this->expectException(CustomerException::class);
        $this->expectExceptionMessage("API responded with 200 but didn't return any data.");

        $clientMock->getAttributes();
    }

    /**
     * Should throw exception if global limit is reached
     */
    public function testThrottlingGlobalLimitReached()
    {
        $response = $this->buildResponseMock('{}', 200, false);
        $response->expects($this->any())->method('getHeader')->willReturn('1');

        $logMock = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods(['warning', 'info', 'alert'])
            ->getMock();

        $configMock = $this->getMockBuilder(PlentyConfig::class)
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'getDomain',
                    'getUsername',
                    'getPassword',
                    'getWsdlUrl',
                    'getLanguage',
                    'getMultishopId',
                    'getAvailabilityId',
                    'getPriceId',
                    'getRrpId',
                    'getCountry'
                ]
            )->getMock();

        $httpClientMock = $this->getHttpClientMock(['send']);
        $httpClientMock->expects($this->any())->method('send')->willReturn($response);

        $clientMock = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([$configMock, $logMock, $logMock, $httpClientMock])
            ->setMethods(['createRequest'])
            ->getMock();
        $clientMock->expects($this->once())->method('createRequest')->willReturn($this->getRequestMock());

        $this->expectException(ThrottlingException::class);

        $clientMock->getAttributes();
    }

    /**
     * Should throw exception if global limit is reached
     */
    public function testThrottlingGlobalLimitReachedIndicatedByStatusCode()
    {
        $response = $this->buildResponseMock('Failed', 429, false);
        $response->expects($this->any())->method('getHeader')->willReturn('1');

        $logMock = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods(['warning', 'info', 'alert'])
            ->getMock();

        $configMock = $this->getMockBuilder(PlentyConfig::class)
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'getDomain',
                    'getUsername',
                    'getPassword',
                    'getWsdlUrl',
                    'getLanguage',
                    'getMultishopId',
                    'getAvailabilityId',
                    'getPriceId',
                    'getRrpId',
                    'getCountry'
                ]
            )->getMock();

        $httpClientMock = $this->getHttpClientMock(['send']);
        $httpClientMock->expects($this->any())->method('send')->willReturn($response);

        $clientMock = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([$configMock, $logMock, $logMock, $httpClientMock])
            ->setMethods(['createRequest'])
            ->getMock();
        $clientMock->expects($this->once())->method('createRequest')->willReturn($this->getRequestMock());

        $this->expectException(ThrottlingException::class);

        $clientMock->getAttributes();
    }

    /**
     * Should throw exception if global limit is reached
     */
    public function testThrottlingRouteCallsLimitReached()
    {
        $response = $this->buildResponseMock('{}', 200, false);
        $response->expects($this->any())->method('getHeader')->willReturnOnConsecutiveCalls(50, 1);

        $logMock = $this->getMockBuilder(Logger::class)
            ->setMethods(['warning'])
            ->disableOriginalConstructor()
            ->getMock();
        $logMock->expects($this->atLeastOnce())
            ->method('warning')
            ->with('Throttling limit reached. Will be waiting for 5 seconds.');
        $configMock = $this->getMockBuilder(PlentyConfig::class)->setMethods(['getDomain'])->getMock();
        $httpClientMock = $this->getHttpClientMock(['send']);
        $httpClientMock->expects($this->any())->method('send')->willReturn($response);

        $clientMock = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([$configMock, $logMock, $logMock, $httpClientMock])
            ->setMethods(['createRequest', 'setLastTimeout', 'setThrottlingTimeout', 'getThrottlingTimeout'])
            ->getMock();

        $clientMock->expects($this->once())->method('createRequest')->willReturn($this->getRequestMock());
        $clientMock->expects($this->atLeastOnce())->method('setLastTimeout');
        $clientMock->expects($this->atLeastOnce())->method('setThrottlingTimeout');
        $clientMock->expects($this->atLeastOnce())->method('getThrottlingTimeout')->willReturn(5);

        $clientMock->getAttributes();
    }

    /**
     * Should throw exception if global limit is reached
     */
    public function testThrottlingGlobalShortLimitReached()
    {
        $response = $this->buildResponseMock('{}', 200, false);
        $response->expects($this->any())->method('getHeader')->willReturnOnConsecutiveCalls(50, 15, 1);

        $logMock = $this->getMockBuilder(Logger::class)
            ->setMethods(['warning'])
            ->disableOriginalConstructor()
            ->getMock();
        $logMock
            ->expects($this->once())
            ->method('warning')
            ->with('Throttling limit reached. Will be waiting for 5 seconds.');
        $configMock = $this->getMockBuilder(PlentyConfig::class)->setMethods(['getDomain'])->getMock();
        $httpClientMock = $this->getHttpClientMock(['send']);
        $httpClientMock->expects($this->any())->method('send')->willReturn($response);

        $clientMock = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([$configMock, $logMock, $logMock, $httpClientMock])
            ->setMethods(['createRequest', 'setLastTimeout', 'setThrottlingTimeout', 'getThrottlingTimeout'])
            ->getMock();

        $clientMock->expects($this->once())->method('createRequest')->willReturn($this->getRequestMock());
        $clientMock->expects($this->atLeastOnce())->method('setLastTimeout');
        $clientMock->expects($this->atLeastOnce())->method('setThrottlingTimeout');
        $clientMock->expects($this->atLeastOnce())->method('getThrottlingTimeout')->willReturn(5);

        $clientMock->getAttributes();
    }

    public function providerGetProductVariationsSetsWithParameter(): array
    {
        return [
            'With parameter is an empty array' => [
                [],
                []
            ],
            'With parameter is not an empty array' => [
                [
                    'variationCategories',
                    'variationSalesPrices',
                    'variationAttributeValues',
                    'variationProperties',
                    'properties',
                    'units'
                ],
                [
                    'variationCategories',
                    'variationSalesPrices',
                    'variationAttributeValues',
                    'variationProperties',
                    'properties',
                    'units'
                ]
            ]
        ];
    }

    /**
     * @dataProvider providerGetProductVariationsSetsWithParameter
     *
     * @param array $with
     * @param array $expectedWith
     * @throws ReflectionException
     */
    public function testGetProductVariationsSetsWithParameter(array $with, array $expectedWith)
    {
        $debugMock = $this->getMockBuilder(Debugger::class)->disableOriginalConstructor()->getMock();
        $logMock = $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->getMock();
        $configMock = $this->getMockBuilder(PlentyConfig::class)->setMethods(['getDomain'])->getMock();
        $httpClientMock = $this->getHttpClientMock(['send']);

        $clientMock = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([$configMock, $logMock, $logMock, $httpClientMock, $debugMock])
            ->setMethods(['getEndpoint', 'call'])
            ->getMock();

        $response = $this->buildResponseMock('{}', 200);

        $clientMock->expects($this->once())->method('getEndpoint')->with(
            'items/variations',
            [
                'with' => $expectedWith,
                'isActive' => true,
                'itemId' => ['1']
            ]
        );

        $clientMock->expects($this->any())->method('call')->willReturn($response);

        $clientMock->getProductVariations(['1'], $with);
    }

    /* ------ helper functions ------ */

    /**
     * @param $methods
     * @return Client|MockObject
     * @throws \ReflectionException
     */
    protected function getClientMock($methods): Client
    {
        $logMock = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods(['warning', 'info', 'alert'])
            ->getMock();

        $configMock = $this->getMockBuilder(PlentyConfig::class)
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'getDomain',
                    'getUsername',
                    'getPassword',
                    'getWsdlUrl',
                    'getLanguage',
                    'getMultishopId',
                    'getAvailabilityId',
                    'getPriceId',
                    'getRrpId',
                    'getCountry'
                ]
            )->getMock();

        $httpClientMock = $this->getHttpClientMock(['send']);

        $clientMock = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([$configMock, $logMock, $logMock, $httpClientMock])
            ->setMethods($methods)
            ->getMock();

        return $clientMock;
    }
}
