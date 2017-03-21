<?php

namespace Findologic\PlentymarketsTest;

use PHPUnit_Framework_TestCase;

class ClientTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test when login request was successful
     */
    public function testLogin()
    {
        $clientMock = $this->getClientMock(array('call'));

        $body = '{"accessToken":"TEST_TOKEN","tokenType":"Bearer","expiresIn":86400,"refreshToken":"REFERSH_TOKEN"}';
        $responseMock = $this->getResponseMock($body, 200);

        $clientMock->expects($this->once())->method('call')->will($this->returnValue($responseMock));
        $clientMock->login();

        $this->assertEquals($clientMock->getToken(), 'TEST_TOKEN');
    }

    /**
     * Exception should be thrown when response status is incorrect
     */
    public function testLoginResponseStatusException()
    {
        $clientMock = $this->getClientMock(array('call'));

        $body = 'No response!';
        $responseMock = $this->getResponseMock($body, 400);

        $clientMock->expects($this->once())->method('call')->will($this->returnValue($responseMock));

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
     * Get products api call
     */
    public function testGetProducts()
    {
        $clientMock = $this->getClientMock(array('call'));
        $body = '{"Test":"Test"}';
        $responseMock = $this->getResponseMock($body, 200);

        $clientMock->expects($this->once())->method('call')->will($this->returnValue($responseMock));

        $this->assertSame($clientMock->getProducts(), array('Test' => 'Test'));
    }

    /**
     * Test call to api when calls fails but last call is successful
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

        $this->assertSame($clientMock->getCategories(), array('Test' => 'Test'));
    }

    /**
     * Test handling failed api call when retry max count is reached
     */
    public function testCallRetryFailed()
    {
        $requestMock = $this->getMockBuilder('\HTTP_Request2')
            ->disableOriginalConstructor()
            ->setMethods(array('send'))
            ->getMock();

        $failedResponse = $this->getResponseMock('Failed', 404);
        $requestMock->expects($this->any())->method('send')->will($this->returnValue($failedResponse));

        $clientMock = $this->getClientMock(array('createRequest', 'handleException'));
        $clientMock->expects($this->once())->method('createRequest')->will($this->returnValue($requestMock));
        $clientMock->expects($this->once())->method('handleException');
        $clientMock->getProductVariations('1');
    }

    /* ------ helper functions ------ */

    protected function getClientMock($methods)
    {
        $clientMock = $this->getMockBuilder('\Findologic\Plentymarkets\Client')
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();

        return $clientMock;
    }

    protected function getResponseMock($body, $status)
    {
        $responseMock = $this->getMockBuilder('\HTTP_Request2_Response')
            ->disableOriginalConstructor()
            ->setMethods(array('getStatus', 'getBody'))
            ->getMock();

        $responseMock->expects($this->any())->method('getBody')->will($this->returnValue($body));
        $responseMock->expects($this->any())->method('getStatus')->will($this->returnValue($status));

        return $responseMock;
    }
}