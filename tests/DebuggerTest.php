<?php

namespace Findologic\PlentymarketsTest;

use Findologic\Plentymarkets\Exception\InternalException;
use PHPUnit_Framework_TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamFile;
use org\bovigo\vfs\vfsStreamWrapper;
use org\bovigo\vfs\vfsStreamDirectory;

class DebuggerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    protected $testFilePath;

    /**
     * @var \org\bovigo\vfs\vfsStreamDirectory
     */
    protected $fileSystemMock;

    public function setUp()
    {
        $this->testFilePath = 'tmp';
        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory($this->testFilePath));
        $this->fileSystemMock = vfsStreamWrapper::getRoot();

    }

    /**
     * Check if debugger will be called for enabled path
     */
    public function testDebugCall()
    {
        $requestMock = $this->getRequestMock('/rest/items');

        $debuggerMock = $this->getMockBuilder('\Findologic\Plentymarkets\Debugger')
            ->setConstructorArgs(array($this->getLogMock(), $this->fileSystemMock->url(), array('items')))
            ->setMethods(array('debugRequest', 'debugResponse', 'getFilePrefix'))
            ->getMock();

        $debuggerMock->expects($this->once())->method('debugRequest');
        $debuggerMock->expects($this->once())->method('debugResponse');
        $debuggerMock->expects($this->once())->method('getFilePrefix')->willReturn('test');

        $debuggerMock->debugCall($requestMock, false);
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
        $responseMock = $this->getResponseMock(array('getStatus', 'getReasonPhrase', 'getHeader', 'getBody'));

        $debuggerMock = $this->getMockBuilder('\Findologic\Plentymarkets\Debugger')
            ->setConstructorArgs(array($this->getLogMock(), $this->fileSystemMock->url(), array('items')))
            ->setMethods(array('getFilePrefix'))
            ->getMock();

        $debuggerMock->expects($this->once())->method('getFilePrefix')->willReturn('test');

        $debuggerMock->debugCall($requestMock, $responseMock);
        $dateFolder =  date('Y-m-d', time());

        // Check if request dir was created
        $this->assertTrue($this->fileSystemMock->getChild('items')->hasChild($dateFolder));
        // Check if request dump file was created
        $dumpDir = $this->fileSystemMock->getChild('items')->getChild($dateFolder);
        $this->assertTrue($dumpDir->hasChild('testRequest.txt'));
    }

    /**
     * Check if exception handling was called when file could not be opened
     */
    public function testDebugCallFileCreationException()
    {
        $requestMock = $this->getRequestMock('/rest/items');

        // If exception is thrown the log handle method should be called
        $logMock = $this->getLogMock(array('handleException'));
        $logMock->expects($this->once())->method('handleException');

        $debuggerMock = $this->getMockBuilder('\Findologic\Plentymarkets\Debugger')
            ->setConstructorArgs(array($logMock, $this->fileSystemMock->url(), array('items')))
            ->setMethods(array('getApiCallDirectoryPath', 'createDirectory', 'getFilePrefix', 'debugResponse'))
            ->getMock();

        $debuggerMock->expects($this->once())->method('getApiCallDirectoryPath')->willReturn($this->fileSystemMock->url());
        $debuggerMock->expects($this->once())->method('getFilePrefix')->willReturn('test');

        $fileMock = new vfsStreamFile('testRequest.txt');
        $fileMock->setContent('Test');
        $fileMock->chmod(0);
        $this->fileSystemMock->addChild($fileMock);

        // Silence fopen warnings for tests
        @$debuggerMock->debugCall($requestMock, false);
    }

    /**
     * Check the file actually has some content
     */
    public function testDebugCallWriteToFile()
    {
        $requestMock = $this->getMockBuilder('\HTTP_Request2')
            ->disableOriginalConstructor()
            ->setMethods(array('getUrl'))
            ->getMock();

        $requestMock->expects($this->any())->method('getUrl')->will($this->returnValue($this->getUrlMock('/rest/items')));

        $debuggerMock = $this->getMockBuilder('\Findologic\Plentymarkets\Debugger')
            ->setConstructorArgs(array($this->getLogMock(), $this->fileSystemMock->url(), array('items')))
            ->setMethods(array('getFilePrefix', 'debugResponse'))
            ->getMock();

        $debuggerMock->expects($this->once())->method('getFilePrefix')->willReturn('test');

        $debuggerMock->debugCall($requestMock, false);

        $dateFolder =  date('Y-m-d', time());
        $dumpDir = $this->fileSystemMock->getChild('items')->getChild($dateFolder);
        $file = $dumpDir->getChild('testRequest.txt');
    }

    /* ------ helper functions ------ */

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getLogMock($methods = array())
    {
        $logMock = $this->getMockBuilder('\Findologic\Plentymarkets\Log')
            ->disableOriginalConstructor()
            ->setMethods($methods)
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
    protected function getResponseMock($methods = array())
    {
        $responseMock = $this->getMockBuilder('\HTTP_Request2_Response')
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();

        return $responseMock;
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