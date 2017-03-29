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
    protected $directory = '/tmp/';

    /**
     * Array for holding specific paths of api call to debug
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
            $this->directory = $directory;
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
        if (!$this->debugPath($request->getUrl()->getPath())) {
            return false;
        }

        $this->debugRequest($request);
        $this->debugResponse($response);
    }

    /**
     * Check if this api path should be debugged
     *
     * @param string $path
     * @return bool
     */
    protected function debugPath($path)
    {
        $path = str_replace('/rest/', '', $path);
        if (!empty($this->pathsToDebug) && in_array($path, $this->pathsToDebug)) {
            return true;
        }

        return false;
    }

    /**
     * @param \HTTP_Request2 $request
     */
    protected function debugRequest($request)
    {

    }

    /**
     * @param \HTTP_Request2_Response $response
     */
    protected function debugResponse($response)
    {

    }

}