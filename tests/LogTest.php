<?php

namespace Findologic\PlentymarketsTest;

use PHPUnit_Framework_TestCase;

class LogTest extends PHPUnit_Framework_TestCase
{
    /**
     * If user call method which is not a part of Logger class it should ignore logging passed message
     * and add warning message about wrong call
     */
    public function testMagicMethodInvalidMethodCalled()
    {
        $customerLogger = $this->getMockBuilder('\Logger')->disableOriginalConstructor()->getMock();
        $logger = $this->getMockBuilder('\Logger')->disableOriginalConstructor()->getMock();
        $logger->expects($this->once())->method('warn');

        $loggers = array(
            'customerLogger' => $customerLogger,
            'logger' => $logger
        );

        $logMock = $this->getMockBuilder('\Findologic\Plentymarkets\Log')
            ->setConstructorArgs($loggers)
            ->setMethods(null)
            ->getMock();

        $this->assertFalse($logMock->test('Test Message!'));
    }

    /**
     * Internal messages should only be logged to internal logger
     */
    public function testMagicMethodInternalMessage()
    {
        $customerLogger = $this->getMockBuilder('\Logger')->disableOriginalConstructor()->getMock();
        $logger = $this->getMockBuilder('\Logger')->disableOriginalConstructor()->getMock();
        $logger->expects($this->once())->method('debug');

        $loggers = array(
            'customerLogger' => $customerLogger,
            'logger' => $logger
        );

        $logMock = $this->getMockBuilder('\Findologic\Plentymarkets\Log')
            ->setConstructorArgs($loggers)
            ->setMethods(null)
            ->getMock();

        $logMock->debug('Test Message!', true);
    }

    /**
     * Internal messages should only be logged to internal logger
     */
    public function testMagicMethodLogMessage()
    {
        $customerLogger = $this->getMockBuilder('\Logger')->disableOriginalConstructor()->getMock();
        $customerLogger->expects($this->once())->method('info');
        $logger = $this->getMockBuilder('\Logger')->disableOriginalConstructor()->getMock();
        $logger->expects($this->never())->method('info');

        $loggers = array(
            'customerLogger' => $customerLogger,
            'logger' => $logger
        );

        $logMock = $this->getMockBuilder('\Findologic\Plentymarkets\Log')
            ->setConstructorArgs($loggers)
            ->setMethods(null)
            ->getMock();

        $logMock->info('Test Message!');
    }

    public function testhandleEmptyData()
    {
        $customerLogger = $this->getMockBuilder('\Logger')->disableOriginalConstructor()->getMock();
        $logger = $this->getMockBuilder('\Logger')->disableOriginalConstructor()->getMock();

        $loggers = array(
            'customerLogger' => $customerLogger,
            'logger' => $logger
        );

        $logMock = $this->getMockBuilder('\Findologic\Plentymarkets\Log')
            ->setConstructorArgs($loggers)
            ->setMethods(array('trace'))
            ->getMock();

        $logMock->expects($this->once())->method('trace');

        $logMock->handleEmptyData('Test Message!');
    }
}