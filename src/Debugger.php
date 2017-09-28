<?php

namespace Findologic\Plentymarkets;

use \Findologic\Plentymarkets\Exception\InternalException;
use \HTTP_Request2;
use \HTTP_Request2_Response;
use \Logger;

class Debugger
{
    /**
     * Directory where call debug information should be save
     *
     * @var string
     */
    protected $directory = 'dump';

    /**
     * Array for holding specific paths of api calls to debug
     * If empty all calls will be logged
     * Example: if you pass array('items') only /rest/items call will be logged
     *
     *
     * @var array
     */
    protected $pathsToDebug = array();

    /**
     * @var \Logger $log
     */
    protected $log;

    /**
     * @param \Logger $log
     * @param string|bool $directory
     * @param array $pathsToDebug
     */
    public function __construct($log, $directory = false, $pathsToDebug = array())
    {
        $this->log = $log;

        if ($directory) {
            $this->directory = rtrim($directory, '/');
        }

        $this->pathsToDebug = $pathsToDebug;
    }

    /**
     * @param \HTTP_Request2 $request
     * @param \HTTP_Request2_Response $response
     * @return bool
     */
    public function debugCall($request, $response)
    {
        if (!$this->isPathDebuggable($request->getUrl()->getPath())) {
            return false;
        }

        $path = $this->getApiCallDirectoryPath($request->getUrl()->getPath());
        $filePrefix = $this->getFilePrefix();
        $this->createDirectory($path);
        $fileHandle = $this->createFile($path, $filePrefix . 'Request.txt');
        $this->debugRequest($request, $fileHandle);
        $this->debugResponse($response, $fileHandle);
        fclose($fileHandle);

        return true;
    }

    /**
     * Get full directory path where the request and response should be saved
     *
     * @param $callPath
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
        return time() . '_';
    }

    /**
     * Remove api prefix from method path
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
     * Check if this api path should be debugged
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
            throw new InternalException("Could not create or open the file for dumping the request data for debugging!");
        }

        return $fileHandle;
    }

    /**
     * Write request data to file
     *
     * @param \HTTP_Request2 $request
     * @param resource $fileHandle
     * @return bool
     */
    protected function debugRequest($request, $fileHandle)
    {
        $this->addSeparatorToFile($fileHandle, 'Request', false);

        if ($url = $request->getUrl()) {
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
     * @param \HTTP_Request2_Response $response
     * @param resource $fileHandle
     * @return bool
     */
    protected function debugResponse($response, $fileHandle)
    {
        $this->addSeparatorToFile($fileHandle, 'Response');

        $this->writeToFile($fileHandle, 'Response Status', $response->getStatus());
        $this->writeToFile($fileHandle, 'Response Phrase', $response->getReasonPhrase());
        $this->writeToFile($fileHandle, 'Headers', $response->getHeader());
        $this->writeToFile($fileHandle, 'Body', $response->getBody());

        return true;
    }

    /**
     * Write debug data to file and add some formatting if needed
     *
     * @param $fileHandle
     * @param string $title
     * @param string|int|array $data
     */
    protected function writeToFile($fileHandle, $title, $data, $nestingLevel = 0)
    {
        if (empty($data)) {
            // Insert some predefined value if data for this field is empty
            $data = '--- EMPTY ---';
        }

        // Get initial field nesting level sting
        $nesting = $this->getNestingString($nestingLevel);
        fwrite($fileHandle, print_r($nesting . $title . " : ", TRUE));

        // Get field value nesting level sting
        $nesting = $this->getNestingString(++$nestingLevel);

        if (is_array($data)) {
            // If data is array call the method again for each array field
            fwrite($fileHandle, $nesting . print_r( "\n", TRUE));
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