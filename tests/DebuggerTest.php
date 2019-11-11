<?php

/**
 * Use tested class but not test namespace to allow overriding global php functions
 */
namespace Findologic\Plentymarkets;

use Findologic\Plentymarkets\Exception\InternalException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Log4Php\Logger;
use Net_URL2;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStreamFile;
use org\bovigo\vfs\vfsStreamWrapper;
use org\bovigo\vfs\vfsStreamDirectory;
use Psr\Http\Message\StreamInterface;

/**
 * Override microtime function for easier testing
 */
function microtime($get_as_float = null)
{
    return DebuggerTest::$now ? DebuggerTest::$now : \microtime($get_as_float);
}

class DebuggerTest extends TestCase
{
    /**
     * @var string
     */
    protected $testFilePath;

    /**
     * @var \org\bovigo\vfs\vfsStreamDirectory
     */
    protected $fileSystemMock;

    /**
     * @var int $now Timestamp that will be returned by time()
     */
    public static $now;

    public function setUp(): void
    {
        $this->testFilePath = 'tmp';
        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory($this->testFilePath));
        $this->fileSystemMock = vfsStreamWrapper::getRoot();

    }

    /**
     * Reset custom time after test
     */
    protected function tearDown(): void
    {
        self::$now = null;
    }

    /**
     * Check if debugger will be called for enabled path
     */
    public function testDebugCall()
    {
        $requestMock = $this->getRequestMock('/rest/items');

        $debuggerMock = $this->getMockBuilder(Debugger::class)
            ->setConstructorArgs([$this->getLogMock(), $this->fileSystemMock->url(), ['items']])
            ->setMethods(['debugRequest', 'debugResponse', 'getFilePrefix'])
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

        $debuggerMock = $this->getMockBuilder(Debugger::class)
            ->setConstructorArgs([$this->getLogMock(), '/tmp/', ['login', 'some/path']])
            ->setMethods(['debugRequest', 'debugResponse'])
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
        $responseMock = $this->getResponseMock(['getStatus', 'getReasonPhrase', 'getHeader', 'getBody']);
        $streamMock = $this->getStreamMock();

        $responseMock->expects($this->once())->method('getBody')->willReturn($streamMock);

        /** @var Debugger|MockObject $debuggerMock */
        $debuggerMock = $this->getMockBuilder(Debugger::class)
            ->setConstructorArgs([$this->getLogMock(), $this->fileSystemMock->url(), ['items']])
            ->setMethods(null)
            ->getMock();

        //1970-01-01
        self::$now = 1493895229;
        $dateFolder =  date('Y-m-d', time());
        $debuggerMock->debugCall($requestMock, $responseMock);

        // Check if request dir was created
        $this->assertTrue($this->fileSystemMock->getChild('items')->hasChild($dateFolder));
        // Check if request dump file was created
        $dumpDir = $this->fileSystemMock->getChild('items')->getChild($dateFolder);
        $this->assertTrue($dumpDir->hasChild('1493895229000_Request.txt'));
    }

    /**
     * Check if exception was thrown when file could not be opened
     */
    public function testDebugCallFileCreationException()
    {
        $requestMock = $this->getRequestMock('/rest/items');
        $logMock = $this->getLogMock();
        $debuggerMock = $this->getMockBuilder(Debugger::class)
            ->setConstructorArgs([$logMock, $this->fileSystemMock->url(), ['items']])
            ->setMethods(['getApiCallDirectoryPath', 'createDirectory', 'getFilePrefix', 'debugResponse'])
            ->getMock();

        $debuggerMock->expects($this->once())->method('getApiCallDirectoryPath')->willReturn($this->fileSystemMock->url());
        $debuggerMock->expects($this->once())->method('getFilePrefix')->willReturn('test');

        $fileMock = new vfsStreamFile('testRequest.txt');
        $fileMock->setContent('Test');
        $fileMock->chmod(0);
        $this->fileSystemMock->addChild($fileMock);

        $this->expectException(InternalException::class);

        // Silence fopen warnings for tests
        @$debuggerMock->debugCall($requestMock, false);
    }

    /**
     * Check the file actually has some content
     */
    public function testDebugCallWriteToFile()
    {
        $requestMock = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->setMethods(['getUri', 'getHeaders', 'getMethod'])
            ->getMock();

        $requestMock->expects($this->any())->method('getUri')->will($this->returnValue($this->getUrlMock('/rest/items')));
        $requestMock->expects($this->any())->method('getHeaders')->will($this->returnValue(['Authorization' => 'Test']));
        $requestMock->expects($this->any())->method('getMethod')->willReturn('GET');

        $debuggerMock = $this->getMockBuilder(Debugger::class)
            ->setConstructorArgs([$this->getLogMock(), $this->fileSystemMock->url(), ['items']])
            ->setMethods(['getFilePrefix', 'debugResponse'])
            ->getMock();

        $debuggerMock->expects($this->once())->method('getFilePrefix')->willReturn('test');

        $debuggerMock->debugCall($requestMock, false);

        $dateFolder =  date('Y-m-d', time());
        $dumpDir = $this->fileSystemMock->getChild('items')->getChild($dateFolder);

        //expected file content
        $content = ' ---- Request ---- 

Requested URL : --- EMPTY ---
Port : --- EMPTY ---
Method type : GET
Headers :     
    Authorization : Test
';

        $file = $dumpDir->getChild('testRequest.txt');
        $this->assertEquals($content, $file->getContent());
    }

    public function testResetCallTiming()
    {
        /** @var Debugger $debuggerMock */
        $debuggerMock = $this->getMockBuilder(Debugger::class)
            ->setConstructorArgs([$this->getLogMock(), $this->fileSystemMock->url(), []])
            ->setMethods(null)
            ->getMock();

        $debuggerMock->logCallTiming('test', 1, 2);

        $this->assertTrue((count($debuggerMock->getCallTiming()) == 1));

        $debuggerMock->resetCallTiming();

        $this->assertTrue(empty($debuggerMock->getCallTiming()));
    }

    public function providerLogCallTiming()
    {
        return [
            [
                [
                    ['uri' => 'test/1', 'begin' => 1, 'end' => 2],
                    ['uri' => 'test/2', 'begin' => 3, 'end' => 6],
                    ['uri' => 'testing/1', 'begin' => 6, 'end' => 7]
                ],
                [
                    'test/x' => [
                        Debugger::TIMING_COUNT => 2,
                        Debugger::TIMING_TOTAL_TIME => 4,
                        Debugger::TIMING_AVERAGE_TIME => 2
                    ],
                    'testing/x' => [
                        Debugger::TIMING_COUNT => 1,
                        Debugger::TIMING_TOTAL_TIME => 1,
                        Debugger::TIMING_AVERAGE_TIME => 1
                    ],
                ]
            ]
        ];
    }

    /**
     * @dataProvider providerLogCallTiming
     */
    public function testLogCallTiming($timingData, $expectedResult)
    {
        /** @var Debugger $debuggerMock */
        $debuggerMock = $this->getMockBuilder(Debugger::class)
            ->setConstructorArgs([$this->getLogMock(), $this->fileSystemMock->url(), []])
            ->setMethods(null)
            ->getMock();

        foreach ($timingData as $timing) {
            $debuggerMock->logCallTiming($timing['uri'], $timing['begin'], $timing['end']);
        }

        $this->assertEquals($expectedResult, $debuggerMock->getCallTiming());
    }

    /**
     * If timing information is empty there is no need to create file
     */
    public function testWriteCallTimingLogNoTimingInfo()
    {
        /** @var Debugger|MockObject $debuggerMock */
        $debuggerMock = $this->getMockBuilder(Debugger::class)
            ->setConstructorArgs([$this->getLogMock(), $this->fileSystemMock->url(), []])
            ->setMethods(['createDirectory', 'createFile'])
            ->getMock();

        $debuggerMock->expects($this->never())->method('createDirectory');
        $debuggerMock->expects($this->never())->method('createFile');

        $debuggerMock->writeCallTimingLog(Debugger::TIMING_DEFAULT_FILE, 'test');
    }

    /**
     * Test if timing information file is created with specific content
     */
    public function testWriteCallTimingLog()
    {
        /** @var Debugger $debuggerMock */
        $debuggerMock = $this->getMockBuilder(Debugger::class)
            ->setConstructorArgs([$this->getLogMock(), $this->fileSystemMock->url(), []])
            ->setMethods(null)
            ->getMock();

        $dir = 'test';
        $fullPath = $this->fileSystemMock->url() . DIRECTORY_SEPARATOR . $dir;

        $debuggerMock->logCallTiming('test/1', 1, 2);
        $debuggerMock->writeCallTimingLog(Debugger::TIMING_DEFAULT_FILE, $fullPath);

        $this->assertTrue($this->fileSystemMock->hasChild($dir));

        $dumpDir = $this->fileSystemMock->getChild($dir);

        //expected file content
        $content = 'test/x :     
    count : 1
    time : 1
    average_time : 1
';

        $file = $dumpDir->getChild(Debugger::TIMING_DEFAULT_FILE);
        $this->assertEquals($content, $file->getContent());
    }

    /* ------ helper functions ------ */

    /**
     * @return MockObject
     */
    protected function getLogMock($methods = [])
    {
        $logMock = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();

        return $logMock;
    }

    /**
     * @param string $path
     * @return Request|MockObject
     * @throws \ReflectionException
     */
    protected function getRequestMock($path)
    {
        $requestMock = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->setMethods(['getUri'])
            ->getMock();

        $requestMock->expects($this->any())->method('getUri')->will($this->returnValue($this->getUrlMock($path)));

        return $requestMock;
    }

    /**
     * @param array $methods
     * @return Response|MockObject
     * @throws \ReflectionException
     */
    protected function getResponseMock($methods = [])
    {
        $responseMock = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();

        return $responseMock;
    }

    /**
     * @return StreamInterface|MockObject
     * @throws \ReflectionException
     */
    protected function getStreamMock()
    {
        $streamMock = $this->getMockBuilder(StreamInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        return $streamMock;
    }

    /**
     * @param string $path
     * @return Net_URL2|MockObject
     * @throws \ReflectionException
     */
    protected function getUrlMock($path)
    {
        $urlMock = $this->getMockBuilder(Net_URL2::class)
            ->disableOriginalConstructor()
            ->setMethods(['getPath', '__toString', 'getPort'])
            ->getMock();

        $urlMock->expects($this->any())->method('getPath')->willReturn($path);

        return $urlMock;
    }
}
