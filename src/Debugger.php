<?php

namespace Findologic\Plentymarkets;

use Findologic\Plentymarkets\Exception\InternalException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use HTTP_Request2_Exception;
use HTTP_Request2_Response;
use Psr\Log\LoggerInterface;

class Debugger
{
    const TIMING_COUNT = 'count';
    const TIMING_TOTAL_TIME = 'time';
    const TIMING_AVERAGE_TIME = 'average_time';
    const TIMING_DEFAULT_DIR = 'dump/timing';
    const TIMING_DEFAULT_FILE = 'timing.txt';

    /**
     * Directory where call debug information should be save
     *
     * @var string
     */
    protected $directory = 'dump';

    /**
     * Array for holding specific paths of API calls to debug
     * If empty all calls will be logged
     * Example: if you pass array('items') only /rest/items call will be logged
     *
     * @var array
     */
    protected $pathsToDebug = array();

    /**
     * @var LoggerInterface $log
     */
    protected $log;

    /**
     * @var array Store timing info on REST calls.
     */
    protected $timing = array();

    /**
     * @param LoggerInterface $log
     * @param string|bool $directory
     * @param array $pathsToDebug
     */
    public function __construct(LoggerInterface $log, $directory = false, array $pathsToDebug = array())
    {
        $this->log = $log;

        if ($directory) {
            $this->directory = rtrim($directory, '/');
        }

        $this->pathsToDebug = $pathsToDebug;
    }

    /**
     * @return array
     */
    public function getCallTiming()
    {
        return $this->timing;
    }

    /**
     * Reset timing array
     */
    public function resetCallTiming()
    {
        unset($this->timing);

        $this->timing = [];
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return bool
     */
    public function debugCall(Request $request, $response)
    {
        if (!$this->isPathDebuggable($request->getUri()->getPath())) {
            return false;
        }

        $path = $this->getApiCallDirectoryPath($request->getUri()->getPath());
        $filePrefix = $this->getFilePrefix();
        $this->createDirectory($path);
        $fileHandle = $this->createFile($path, $filePrefix . 'Request.txt');
        $this->debugRequest($request, $fileHandle);
        $this->debugResponse($response, $fileHandle);
        fclose($fileHandle);

        return true;
    }

    /**
     * Log the timing of a REST call.
     *
     * @param string $uri The URI that was used.
     * @param float $begin Timestamp when the request was started.
     * @param float $end Timestamp when the request finished.
     */
    public function logCallTiming($uri, $begin, $end)
    {
        $diff = $end - $begin;

        // Replace numbers with 'x' in order to aggregate calls that contain an item ID, eg.
        // eg. /rest/items/123/images -> /rest/items/x/images
        $uriWithoutNumbers = preg_replace('/\d+/', 'x', $uri);

        // Initiate the data structure
        if (!isset($this->timing[$uriWithoutNumbers])) {
            $this->timing[$uriWithoutNumbers] = array(
                self::TIMING_COUNT => 0,
                self::TIMING_TOTAL_TIME => 0,
            );
        }
        $this->timing[$uriWithoutNumbers][self::TIMING_COUNT]++;
        $this->timing[$uriWithoutNumbers][self::TIMING_TOTAL_TIME] += $diff;
        $this->timing[$uriWithoutNumbers][self::TIMING_AVERAGE_TIME] =
            $this->timing[$uriWithoutNumbers][self::TIMING_TOTAL_TIME] / $this->timing[$uriWithoutNumbers][self::TIMING_COUNT];
    }

    /**
     * Write timing information into file
     *
     * @param string|null $customFileName
     * @param string|null $customDirectory
     */
    public function writeCallTimingLog($customFileName = null, $customDirectory = null)
    {
        $directory = $customDirectory ? $customDirectory : self::TIMING_DEFAULT_DIR;
        $fileName = $customFileName ? $customFileName : self::TIMING_DEFAULT_FILE;

        if (empty($this->timing)) {
            return;
        }

        $this->createDirectory($directory);
        $fileHandle = $this->createFile($directory, $fileName);

        foreach ($this->timing as $uri => $timingData) {
            $this->writeToFile($fileHandle, $uri, $timingData);
        }

        fclose($fileHandle);
    }

    /**
     * Get full directory path where the request and response should be saved
     *
     * @param string $callPath
     * @return mixed
     */
    protected function getApiCallDirectoryPath($callPath)
    {
        $callPath = $this->removeApiPrefix($callPath);
        $dateFolder =  date('Y-m-d', time());

        $path = $this->directory . DIRECTORY_SEPARATOR . ltrim($callPath, '/') . DIRECTORY_SEPARATOR . $dateFolder;

        return $path;
    }

    /**
     * Create prefix for files
     *
     * @return string
     */
    protected function getFilePrefix()
    {
        return round(microtime(true) * 1000) . '_';
    }

    /**
     * Remove API prefix from method path
     *
     * @param string $path
     * @return mixed
     */
    protected function removeApiPrefix($path)
    {
        return str_replace('/rest/', '', $path);
    }

    /**
     * Create directory by given path
     *
     * @param string $path
     */
    protected function createDirectory($path)
    {
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
    }

    /**
     * Check if this API path should be debugged
     *
     * @param string $path
     * @return bool
     */
    protected function isPathDebuggable($path)
    {
        $path = $this->removeApiPrefix($path);

        if (!empty($this->pathsToDebug) && !in_array($path, $this->pathsToDebug)) {
            return false;
        }

        return true;
    }

    /**
     * Create file for writing
     *
     * @param string $directory
     * @param string $file
     * @return bool|resource
     * @throws InternalException
     */
    protected function createFile($directory, $file)
    {
        if (($fileHandle = fopen($directory . DIRECTORY_SEPARATOR . $file, 'wb+')) === false ) {
            throw new InternalException('Could not create or open the file for dumping the request data for debugging!');
        }

        return $fileHandle;
    }

    /**
     * Write request data to file
     *
     * @param Request $request
     * @param resource $fileHandle
     * @return bool
     */
    protected function debugRequest($request, $fileHandle)
    {
        $this->addSeparatorToFile($fileHandle, 'Request', false);

        if ($url = $request->getUri()) {
            $this->writeToFile($fileHandle, 'Requested URL', $url->__toString());
            $this->writeToFile($fileHandle, 'Port', $url->getPort());
        }

        $this->writeToFile($fileHandle, 'Method type', $request->getMethod());
        $this->writeToFile($fileHandle, 'Headers', $request->getHeaders());

        return true;
    }

    /**
     * Write response data to file
     *
     * @param Response $response
     * @param resource $fileHandle
     * @return bool
     * @throws HTTP_Request2_Exception
     */
    protected function debugResponse($response, $fileHandle)
    {
        $this->addSeparatorToFile($fileHandle, 'Response');

        $this->writeToFile($fileHandle, 'Response Status', $response->getStatusCode());
        $this->writeToFile($fileHandle, 'Response Phrase', $response->getReasonPhrase());
        $this->writeToFile($fileHandle, 'Headers', $response->getHeaders());
        $this->writeToFile($fileHandle, 'Body', $response->getBody()->getContents());

        return true;
    }

    /**
     * Write debug data to file and add some formatting if needed
     *
     * @param resource $fileHandle
     * @param string $title
     * @param string|int|array $data
     * @param int $nestingLevel
     */
    protected function writeToFile($fileHandle, $title, $data, $nestingLevel = 0)
    {
        if (empty($data)) {
            // Insert some predefined value if data for this field is empty
            $data = '--- EMPTY ---';
        }

        // Get initial field nesting level sting
        $nesting = $this->getNestingString($nestingLevel);
        fwrite($fileHandle, print_r($nesting . $title . ' : ', TRUE));

        // Get field value nesting level sting
        $nesting = $this->getNestingString(++$nestingLevel);

        if (is_array($data)) {
            // If data is array call the method again for each array field
            fwrite($fileHandle, $nesting . print_r("\n", TRUE));
            foreach ($data as $key => $value) {
                $this->writeToFile($fileHandle, $key, $value, $nestingLevel);
            }
        } else {
            fwrite($fileHandle, print_r($data, TRUE) . "\n");
        }
    }

    /**
     * Add separator for better debug files readability
     *
     * @param resource $fileHandle
     * @param string $title
     * @param bool $firstLine
     */
    protected function addSeparatorToFile($fileHandle, $title, $firstLine = true)
    {
        if ($firstLine) {
            fwrite($fileHandle, "\n");
        }

        fwrite($fileHandle, " ---- " . $title . " ---- \n");
        fwrite($fileHandle, "\n");
    }

    /**
     * Format a nesting string using the level parameter for better readability
     *
     * @param int $nestingLevel
     * @return string
     */
    protected function getNestingString($nestingLevel)
    {
        $nesting = '';
        if ($nestingLevel < 1) {
            return $nesting;
        }

        for ($i = 0; $i < $nestingLevel; $i++) {
            $nesting .= '    ';
        }

        return $nesting;
    }
}
