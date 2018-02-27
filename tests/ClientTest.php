<?php

namespace Findologic\PlentymarketsTest;

use Findologic\Plentymarkets\Exception\CriticalException;
use Findologic\Plentymarkets\Exception\CustomerException;
use Findologic\Plentymarkets\Exception\ThrottlingException;
use PHPUnit_Framework_TestCase;

class ClientTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test when login request was successful and API returns the token
     */
    public function testLogin()
    {
        $clientMock = $this->getClientMock(array('call'));

        $body = '{"accessToken":"TEST_TOKEN","tokenType":"Bearer","expiresIn":86400,"refreshToken":"REFERSH_TOKEN"}';
        $responseMock = $this->getResponseMock($body, 200);

        $clientMock->expects($this->once())->method('call')->will($this->returnValue($responseMock));
        $clientMock->login();

        $this->assertEquals('TEST_TOKEN', $clientMock->getToken());
    }

    /**
     * Test when login request should change the protocol used
     */
    public function testLoginProtocol()
    {
        $clientMock = $this->getClientMock(array('call'));

        $body = '{"accessToken":"TEST_TOKEN","tokenType":"Bearer","expiresIn":86400,"refreshToken":"REFERSH_TOKEN"}';
        $responseMock = $this->getResponseMock($body, 200);

        $clientMock->expects($this->exactly(2))->method('call')->will($this->onConsecutiveCalls($this->throwException(new \Exception()), $responseMock));
        $clientMock->login();

        $this->assertEquals('TEST_TOKEN', $clientMock->getToken());
    }

    /**
     * Exception should be thrown when response status is incorrect
     */
    public function testLoginResponseStatusException()
    {
        $clientMock = $this->getClientMock(array('call'));

        $body = 'No response!';
        $responseMock = $this->getResponseMock($body, 400);

        $clientMock->expects($this->exactly(2))->method('call')->will($this->returnValue($responseMock));

        $this->setExpectedException(\Findologic\Plentymarkets\Exception\CriticalException::class);

        $clientMock->login();
    }

    /**
     * Exception should be thrown when response do not have access token
     */
    public function testLoginAccessTokenException()
    {
        $clientMock = $this->getClientMock(array('call'));

        $body = '{"tokenType":"Bearer","expiresIn":86400,"refreshToken":"REFERSH_TOKEN"}';
        $responseMock = $this->getResponseMock($body, 200);

        $clientMock->expects($this->once())->method('call')->will($this->returnValue($responseMock));

        $this->setExpectedException(\Findologic\Plentymarkets\Exception\CriticalException::class);

        $clientMock->login();
    }

    /**
     * Get products API call
     */
    public function testGetProducts()
    {
        $clientMock = $this->getClientMock(array('call'));
        $body = '{"Test":"Test"}';
        $responseMock = $this->getResponseMock($body, 200);

        $clientMock->expects($this->once())->method('call')->will($this->returnValue($responseMock));
        $clientMock->setItemsPerPage(50)->setpage(1);
        $this->assertSame(array('Test' => 'Test'), $clientMock->getProducts('EN'));
    }

    /**
     * Test call to API when calls fails but last call is successful
     */
    public function testCallRetrySuccess()
    {
        $requestMock = $this->getMockBuilder('\HTTP_Request2')
            ->disableOriginalConstructor()
            ->setMethods(array('send'))
            ->getMock();

        $successResponse = $this->getResponseMock('{"Test": "Test"}', 200);
        $failedResponse = $this->getResponseMock('Failed', 404);

        $maxRetries = \Findologic\Plentymarkets\Client::RETRY_COUNT;
        // Fail for four out of five times, so we can succeed on the final attempt.
        for ($i = 0; $i < $maxRetries - 1; $i++) {
            $requestMock->expects($this->at($i))->method('send')->will($this->returnValue($failedResponse));
        }

        $requestMock->expects($this->at(($maxRetries - 1)))->method('send')->will($this->returnValue($successResponse));

        $clientMock = $this->getClientMock(array('createRequest'));
        $clientMock->expects($this->once())->method('createRequest')->will($this->returnValue($requestMock));

        $this->assertSame(array('Test' => 'Test'), $clientMock->getCategories());
    }

    /**
     * Test handling failed API call when retry max count is reached
     */
    public function testCallRetryFailed()
    {
        $requestMock = $this->getMockBuilder('\HTTP_Request2')
            ->disableOriginalConstructor()
            ->setMethods(array('send'))
            ->getMock();

        $debugMock = $this->getMockBuilder('\Findologic\Plentymarkets\Debugger')->disableOriginalConstructor()->getMock();
        $logMock = $this->getMockBuilder('\Logger')->disableOriginalConstructor()->getMock();
        $configMock = $this->getMockBuilder('PlentyConfig')->getMock();

        $clientMock = $this->getMockBuilder('Findologic\Plentymarkets\Client')
            ->setConstructorArgs(array($configMock, $logMock, $logMock, $debugMock))
            ->setMethods(array('createRequest'))
            ->getMock();

        $failedResponse = $this->getResponseMock('Failed', 404);
        $requestMock->expects($this->any())->method('send')->will($this->returnValue($failedResponse));
        $clientMock->expects($this->once())->method('createRequest')->will($this->returnValue($requestMock));

        $this->setExpectedException(CustomerException::class);

        $clientMock->getProductVariations('1', '123');
    }

    /**
     * Test if debugger is called and critical exception should be thrown if response return 401
     */
    public function testCallInvalidLogin()
    {
        $requestMock = $this->getMockBuilder('\HTTP_Request2')
            ->disableOriginalConstructor()
            ->setMethods(array('send'))
            ->getMock();

        $debugMock = $this->getMockBuilder('\Findologic\Plentymarkets\Debugger')
            ->disableOriginalConstructor()
            ->getMock();

        // Check if debugger is called
        $debugMock->expects($this->atMost(5))->method('debugCall');
        $logMock = $this->getMockBuilder('\Logger')->disableOriginalConstructor()->getMock();
        $configMock = $this->getMockBuilder('PlentyConfig')->getMock();

        $clientMock = $this->getMockBuilder('Findologic\Plentymarkets\Client')
            ->setConstructorArgs(array($configMock, $logMock, $logMock, $debugMock))
            ->setMethods(array('createRequest'))
            ->getMock();

        $failedResponse = $this->getResponseMock('Failed', 401);
        $failedResponse->expects($this->any())->method('getReasonPhrase')->willReturn('Unauthorized');
        $requestMock->expects($this->any())->method('send')->will($this->returnValue($failedResponse));
        $clientMock->expects($this->once())->method('createRequest')->will($this->returnValue($requestMock));

        $this->setExpectedException(CriticalException::class);

        $clientMock->getProductVariations('1');
    }

    /**
     * Test if correct request object was created by provided data
     */
    public function testCreateRequest()
    {
        $clientMock = $this->getClientMock(array('handleException', 'getToken', 'login'));
        // Set return value to false so method would call login() which sets the token
        $clientMock->expects($this->at(0))->method('getToken')->will($this->returnValue(false));
        $clientMock->expects($this->once())->method('login');
        // Token was set by login() method
        $clientMock->expects($this->at(1))->method('getToken')->will($this->returnValue('TEST_TOKEN'));

        // To test protected method create reflection class
        $reflection = new \ReflectionClass(get_class($clientMock));
        $method = $reflection->getMethod('createRequest');
        $method->setAccessible(true);

        $parameters = array(
            'POST',
            'http://test.com/rest/method',
            array('name' => 'test')
        );

        $request =  $method->invokeArgs($clientMock, $parameters);

        $this->assertInstanceOf('HTTP_Request2', $request);
        // Validate if correct URL is set
        $this->assertSame('http://test.com/rest/method', $request->getUrl()->getURL());
    }

    /**
     * Very basic test to make sure that REST call timing debug method is called.
     */
    public function testTiming()
    {
        $requestMock = $this->getMockBuilder('\HTTP_Request2')
            ->disableOriginalConstructor()
            ->setMethods(array('send'))
            ->getMock();

        $successResponse = $this->getResponseMock('{"Test": "Test"}', 200);

        $debugMock = $this->getMockBuilder('\Findologic\Plentymarkets\Debugger')->disableOriginalConstructor()->getMock();
        $debugMock->expects($this->once())->method('logCallTiming');
        $logMock = $this->getMockBuilder('\Logger')->disableOriginalConstructor()->getMock();
        $configMock = $this->getMockBuilder('PlentyConfig')->getMock();

        $clientMock = $this->getMockBuilder('Findologic\Plentymarkets\Client')
            ->setConstructorArgs(array($configMock, $logMock, $logMock, $debugMock))
            ->setMethods(array('createRequest'))
            ->getMock();

        $requestMock->expects($this->once())->method('send')->will($this->returnValue($successResponse));
        $clientMock->expects($this->once())->method('createRequest')->will($this->returnValue($requestMock));

        $clientMock->getCategories();
    }

    /**
     * Should throw exception if global limit is reached
     */
    public function testThrottlingGlobalLimitReached()
    {

        $requestMock = $this->getMockBuilder('\HTTP_Request2')
            ->disableOriginalConstructor()
            ->setMethods(array('send'))
            ->getMock();

        $response = $this->getResponseMock('Failed', 200, false);
        $response->expects($this->any())->method('getHeader')->willReturn(1);
        $requestMock->expects($this->any())->method('send')->will($this->returnValue($response));

        $clientMock = $this->getClientMock(['createRequest']);
        $clientMock->expects($this->once())->method('createRequest')->will($this->returnValue($requestMock));

        $this->setExpectedException(ThrottlingException::class);

        $clientMock->getAttributes();
    }

    /**
     * Should throw exception if global limit is reached
     */
    public function testThrottlingRouteCallsLimitReached()
    {
        $requestMock = $this->getMockBuilder('\HTTP_Request2')
            ->disableOriginalConstructor()
            ->setMethods(array('send'))
            ->getMock();

        $response = $this->getResponseMock('Failed', 200, false);
        $response->expects($this->any())->method('getHeader')->willReturnOnConsecutiveCalls(50, 2, 5);
        $requestMock->expects($this->any())->method('send')->will($this->returnValue($response));

        $logMock = $this->getMockBuilder('\Logger')->disableOriginalConstructor()->getMock();
        $logMock->expects($this->atLeastOnce())->method('warn')->with('Throttling limit reached. Will be waiting for 5 seconds.');
        $configMock = $this->getMockBuilder('PlentyConfig')->getMock();

        $clientMock = $this->getMockBuilder('Findologic\Plentymarkets\Client')
            ->setConstructorArgs(array($configMock, $logMock, $logMock, false))
            ->setMethods(array('createRequest', 'setLastTimeout', 'setThrottlingTimeout', 'getThrottlingTimeout'))
            ->getMock();

        $clientMock->expects($this->once())->method('createRequest')->will($this->returnValue($requestMock));
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
        $requestMock = $this->getMockBuilder('\HTTP_Request2')
            ->disableOriginalConstructor()
            ->setMethods(array('send'))
            ->getMock();

        $response = $this->getResponseMock('Failed', 200, false);
        $response->expects($this->any())->method('getHeader')->willReturnOnConsecutiveCalls(50, false, 0, 1, 5);
        $requestMock->expects($this->any())->method('send')->will($this->returnValue($response));

        $logMock = $this->getMockBuilder('\Logger')->disableOriginalConstructor()->getMock();
        $logMock->expects($this->once())->method('warn')->with('Throttling limit reached. Will be waiting for 5 seconds.');
        $configMock = $this->getMockBuilder('PlentyConfig')->getMock();

        $clientMock = $this->getMockBuilder('Findologic\Plentymarkets\Client')
            ->setConstructorArgs(array($configMock, $logMock, $logMock, false))
            ->setMethods(array('createRequest', 'setLastTimeout', 'setThrottlingTimeout', 'getThrottlingTimeout'))
            ->getMock();

        $clientMock->expects($this->once())->method('createRequest')->will($this->returnValue($requestMock));
        $clientMock->expects($this->atLeastOnce())->method('setLastTimeout');
        $clientMock->expects($this->atLeastOnce())->method('setThrottlingTimeout');
        $clientMock->expects($this->atLeastOnce())->method('getThrottlingTimeout')->willReturn(5);

        $clientMock->getAttributes();
    }

    /* ------ helper functions ------ */

    /**
     * @param $methods
     * @return \Findologic\Plentymarkets\Client|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getClientMock($methods)
    {
        $logMock = $this->getMockBuilder('\Logger')
            ->disableOriginalConstructor()
            ->setMethods(array())
            ->getMock();

        $configMock = $this->getMockBuilder('PlentyConfig')
            ->disableOriginalConstructor()
            ->setMethods(array('getDomain', 'getUsername', 'getPassword', 'getWsdlUrl', 'getLanguage', 'getMultishopId', 'getAvailabilityId', 'getPriceId', 'getRrpId', 'getCountry'))
            ->getMock();

        $clientMock = $this->getMockBuilder('\Findologic\Plentymarkets\Client')
            ->setConstructorArgs(array($configMock, $logMock, $logMock))
            ->setMethods($methods)
            ->getMock();

        return $clientMock;
    }

    /**
     * @param $body
     * @param $status
     * @param bool $defaultHeaders
     * @return \HTTP_Request2_Response|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getResponseMock($body, $status, $defaultHeaders = true)
    {
        $responseMock = $this->getMockBuilder('\HTTP_Request2_Response')
            ->disableOriginalConstructor()
            ->setMethods(array('getStatus', 'getBody', 'getReasonPhrase', 'getHeader'))
            ->getMock();

        $responseMock->expects($this->any())->method('getBody')->willReturn($body);
        $responseMock->expects($this->any())->method('getStatus')->willReturn($status);

        if ($defaultHeaders) {
            $responseMock->expects($this->any())->method('getHeader')->willReturn(5);
        }

        return $responseMock;
    }
}