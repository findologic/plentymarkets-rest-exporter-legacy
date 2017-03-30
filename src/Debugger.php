<?php

namespace Findologic\Plentymarkets;

use \HTTP_Request2;
use \HTTP_Request2_Response;

class Debugger
{
    /**
     * Directory where call debug information should be save
     *
     * @var string
     */
    protected $directory = 'tmp';

    /**
     * Array for holding specific paths of api calls to debug
     * If empty all calls will be logged
     * Example: if you pass array('items') only /rest/items call will be logged
     *
     *
     * @var array
     */
    protected $pathsToDebug = array();

    public function __construct($directory = false, $pathsToDebug = array())
    {
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

        try {
            $path = $this->getApiCallDirectoryPath($request->getUrl()->getPath());
            $filePrefix = $this->getFilePrefix();
            $this->createDirectory($path);
            $this->debugRequest($request, $path, $filePrefix);
            $this->debugResponse($response, $path, $filePrefix);
        } catch (\Exception $e) {
            //TODO: Logging the errors
        }
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
        return date('H_i_s', time()) . '_';
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
     * Create file handle
     *
     * @param string $directory
     * @param string $file
     * @return bool|resource
     */
    protected function createFile($directory, $file)
    {
        if (($fileHandle = @fopen($directory . $file, 'w+')) === false ) {
            //TODO: should it throw exception if file creation was not successful ???
            return false;
        }

        return $fileHandle;
    }

    /**
     * @param \HTTP_Request2 $request
     * @param string $path
     * @param string $filePrefix
     */
    protected function debugRequest($request, $path, $filePrefix)
    {
        $fileHandle = $this->createFile($path, $filePrefix . 'Request.html');

        if (!$fileHandle) {
            return false;
        }

        //$this->writeHeaders($fileHandle);

        //JSON_PRETTY_PRINT
    }

    /**
     * @param \HTTP_Request2_Response $response
     * @param string $path
     * @param string $filePrefix
     */
    protected function debugResponse($response, $path, $filePrefix)
    {

    }
}