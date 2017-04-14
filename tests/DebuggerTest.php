<?php

namespace Findologic\PlentymarketsTest;

use PHPUnit_Framework_TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamFile;

class DebuggerTest extends PHPUnit_Framework_TestCase
{

    /**
     * @var \org\bovigo\vfs\vfsStreamDirectory
     */
    protected $fileSystemMock;

    public function setUp()
    {
        $this->fileSystemMock = vfsStream::setup('/tmp');
    }

    /**
     * Check if debugger will be called for enabled path
     */
    public function testDebugCall()
    {
        $requestMock = $this->getRequestMock('/rest/items');

        $debuggerMock = $this->getMockBuilder('\Findologic\Plentymarkets\Debugger')
            ->setConstructorArgs(array($this->getLogMock(), $this->fileSystemMock->url(), array('items')))
            ->setMethods(array('debugRequest', 'debugResponse'))
            ->getMock();

        $debuggerMock->expects($this->once())->method('debugRequest');
        $debuggerMock->expects($this->once())->method('debugResponse');

        $debuggerMock->debugCall($requestMock, false);

        $this->assertTrue($this->fileSystemMock->hasChild('items'));
    }

    /**
     * Check if debugger skips debug if path is not enabled
     */
    public function testDebugCallPathsConfiguration()
    {
        $requestMock = $this->getRequestMock('/rest/items');

        $debuggerMock = $this->getMockBuilder('\Findologic\Plentymarkets\Debugger')
            ->setConstructorArgs(array($this->getLogMock(), '/tmp/', array('login', 'some/path')))
            ->setMethods(array('debugRequest', 'debugResponse'))
            ->getMock();

        $debuggerMock->expects($this->never())->method('debugRequest');
        $debuggerMock->expects($this->never())->method('debugResponse');

        $debuggerMock->debugCall($requestMock, false);
    }

    /**
     * Check if file was created for saving the debug info
     */
    public function testDebugCallFileCreation()
    {
        $requestMock = $this->getRequestMock('/rest/items');

        $debuggerMock = $this->getMockBuilder('\Findologic\Plentymarkets\Debugger')
            ->setConstructorArgs(array($this->getLogMock(), $this->fileSystemMock->url(), array('items')))
            ->setMethods(array('debugResponse'))
            ->getMock();

        $debuggerMock->debugCall($requestMock, false);
        $dateFolder =  date('Y-m-d', time());

        $this->assertTrue($this->fileSystemMock->getChild('items')->hasChild($dateFolder));
    }

    /* ------ helper functions ------ */

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getLogMock()
    {
        $logMock = $this->getMockBuilder('\Findologic\Plentymarkets\Log')
            ->disableOriginalConstructor()
            ->setMethods(array())
            ->getMock();

        return $logMock;
    }

    /**
     * @param string $path
     * @return mixed
     */
    protected function getRequestMock($path)
    {
        $requestMock = $this->getMockBuilder('\HTTP_Request2')
            ->disableOriginalConstructor()
            ->setMethods(array('getUrl'))
            ->getMock();

        $requestMock->expects($this->any())->method('getUrl')->will($this->returnValue($this->getUrlMock($path)));

        return $requestMock;
    }

    /**
     * @param string $path
     * @return mixed
     */
    protected function getUrlMock($path)
    {
        $urlMock = $this->getMockBuilder('\Net_URL2')
            ->disableOriginalConstructor()
            ->setMethods(array('getPath'))
            ->getMock();

        $urlMock->expects($this->any())->method('getPath')->will($this->returnValue($path));

        return $urlMock;
    }
}