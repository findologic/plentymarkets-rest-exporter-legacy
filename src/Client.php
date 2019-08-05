<?php

namespace Findologic\Plentymarkets;

use Exception;
use Findologic\Plentymarkets\Exception\CriticalException;
use Findologic\Plentymarkets\Exception\CustomerException;
use Findologic\Plentymarkets\Exception\ThrottlingException;
use Findologic\Plentymarkets\Exception\AuthorizationException;
use \HTTP_Request2_Response;
use \HTTP_Request2;
use Log4Php\Logger;

class Client
{
    const RETRY_COUNT = 5;
    const METHOD_CALLS_LEFT_COUNT = 'x-plenty-route-calls-left';
    const METHOD_CALLS_WAIT_TIME = 'x-plenty-route-decay';
    const GLOBAL_SHORT_CALLS_LEFT_COUNT = 'x-plenty-global-short-period-calls-left';
    const GLOBAL_SHORT_CALLS_WAIT_TIME = 'x-plenty-global-short-period-decay';
    const GLOBAL_LONG_CALLS_LEFT_COUNT = 'x-plenty-global-long-period-calls-left';
    const GLOBAL_LONG_CALLS_WAIT_TIME = 'x-plenty-global-long-period-decay';

    /**
     * REST API URL
     *
     * @var string
     */
    protected $url;

    /**
     * REST login token
     *
     * @var string
     */
    protected $accessToken;

    /**
     * REST login refresh token
     *
     * @var string
     */
    protected $refreshToken;

    /**
     * @var Logger
     */
    protected $log;

    /**
     * @var Logger
     */
    protected $customerLog;

    /**
     * Flag for login call to API to avoid setting the headers for this call
     *
     * @var bool
     */
    protected $loginFlag = false;

    /**
     * @var bool|\Findologic\Plentymarkets\Debugger
     */
    protected $debug = false;

    /**
     * Connection to API protocol (some websites could use http other https)
     *
     * @var string
     */
    protected $protocol = 'https://';

    /**
     * Variable to allow setting items per page on request without adding this as an property
     * to every class method responsible for calling the API
     *
     * @var bool|int
     */
    protected $itemsPerPage = false;

    /**
     * Variable to allow setting page number without adding this as an property
     * to every class method responsible for calling the API
     *
     * @var bool|int
     */
    protected $page = false;

    /**
     * @var \PlentyConfig
     */
    protected $config;

    /**
     * Time to wait before another request
     *
     * @var bool|int
     */
    protected $throttlingTimeout = false;

    /**
     * Timestamp of last time when the throttling limit was reached
     *
     * @var bool|int
     */
    protected $lastTimeout = false;

    /**
     * @param mixed $config Plentymarkets Config object.
     * @param Logger $log
     * @param Logger $customerLog
     * @param bool $debug
     */
    public function __construct($config, Logger $log, Logger $customerLog, $debug = false)
    {
        $url = rtrim($config->getDomain(), '/') . '/rest/';
        $this->url = $url;
        $this->log = $log;
        $this->customerLog = $customerLog;
        $this->debug = $debug;
        $this->config = $config;
    }

    /**
     * @codeCoverageIgnore
     * @return mixed Plentymarkets config.
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @codeCoverageIgnore
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @codeCoverageIgnore
     * @return string
     */
    public function getLanguageCode()
    {
        return $this->config->getLanguage();
    }

    /**
     * Get the token for API call authorization
     *
     * @return null|string
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * @return null|string
     */
    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    /**
     * @return bool
     */
    public function getLoginFlag()
    {
        return $this->loginFlag;
    }

    /**
     * @codeCoverageIgnore
     * @return string
     */
    public function getProtocol()
    {
        return $this->protocol;
    }

    /**
     * @codeCoverageIgnore
     * @return Logger
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * @codeCoverageIgnore
     * @return Logger
     */
    public function getCustomerLog()
    {
        return $this->customerLog;
    }

    /**
     * Set request items per page count
     *
     * @param int $itemsPerPage
     * @return $this
     */
    public function setItemsPerPage($itemsPerPage)
    {
        $this->itemsPerPage = $itemsPerPage;

        return $this;
    }

    /**
     * Set request page
     *
     * @param int $page
     * @return $this
     */
    public function setPage($page)
    {
        $this->page = $page;

        return $this;
    }

    /**
     * @return bool|int
     */
    public function getLastTimeout()
    {
        return $this->lastTimeout;
    }

    /**
     * @param bool|int $timeout
     * @return $this
     */
    public function setLastTimeout($timeout)
    {
        $this->lastTimeout = $timeout;

        return $this;
    }

    /**
     * @return bool|int
     */
    public function getThrottlingTimeout()
    {
        return $this->throttlingTimeout;
    }

    /**
     * @param bool|int $timeout
     * @return $this
     */
    public function setThrottlingTimeout($timeout)
    {
        $this->throttlingTimeout = $timeout;

        return $this;
    }

    /* API calls */

    /**
     * Call login method and save API token for further calls
     *
     * @return $this
     * @throws Exception\CriticalException
     */
    public function login()
    {
        // Set the login flag so the setDefaultParams() would not be called as this method tries to call login() method
        // if token is empty
        $this->loginFlag = true;

        try {
            $response = $this->call('POST', $this->getEndpoint('login'), array(
                    'username' => $this->config->getUsername(),
                    'password' => $this->config->getPassword()
                )
            );
        } catch (Exception $e) {
            $response = false;
        }

        // If using incorrect protocol the API returns status between 301-404  so it could be used to check if correct
        // protocol is used and make appropriate changes
        if (!$response || ($response && $response->getStatus() >= 301 && $response->getStatus() <= 404)) {
            $this->protocol = 'http://';
            $this->getLog()->info('API client request protocol changed to HTTP');
            $response = $this->call('POST', $this->getEndpoint('login'), array(
                    'username' => $this->config->getUsername(),
                    'password' => $this->config->getPassword()
                )
            );
        }

        if (!$response || $response->getStatus() != 200) {
            throw new CriticalException('Could not connect to API!');
        }

        $data = json_decode($response->getBody());

        if (!property_exists($data, 'accessToken')) {
            throw new CriticalException('Incorrect login to API, response does not have an access token!');
        }

        $this->accessToken = $data->accessToken;
        $this->refreshToken = $data->refreshToken;
        $this->loginFlag = false;

        return $this;
    }

    public function refreshLogin()
    {
        try {
            $response = $this->call('POST', $this->getEndpoint('login/refresh'), array(
                    'refresh_token' => $this->refreshToken
                )
            );
        } catch (Exception $e) {
            return;
        }

        $data = json_decode($response->getBody());

        $this->accessToken = $data->accessToken;
        $this->refreshToken = $data->refreshToken;
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to API is not tested
     * @param int $plentyId
     * @return array
     */
    public function getStandardVat($plentyId)
    {
        $params = array('plentyId' => $plentyId);

        $response = $this->call('GET', $this->getEndpoint('vat/standard', $params));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to API is not tested
     * @return array
     */
    public function getWebstores()
    {
        $response = $this->call('GET', $this->getEndpoint('webstores'));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to API is not tested
     * @param int|null $plentyId
     * @return array
     */
    public function getCategories($plentyId = null)
    {
        $params = array('type' => 'item', 'with' => 'details');

        if ($plentyId) {
            $params['plentyId'] = $plentyId;
        }

        $response = $this->call('GET', $this->getEndpoint('categories/', $params));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to API is not tested
     * @return array
     */
    public function getCategoriesBranches()
    {
        $response = $this->call('GET', $this->getEndpoint('category_branches/'));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to API is not tested
     * @return array
     */
    public function getVat()
    {
        $response = $this->call('GET', $this->getEndpoint('vat/'));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to API is not tested
     * @return array
     */
    public function getSalesPrices()
    {
        $response = $this->call('GET', $this->getEndpoint('items/sales_prices/'));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to API is not tested
     * @return array
     */
    public function getManufacturers()
    {
        $response = $this->call('GET', $this->getEndpoint('items/manufacturers/'));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to API is not tested
     * @return array
     */
    public function getAttributes()
    {
        $response = $this->call('GET', $this->getEndpoint('items/attributes', array('with' => 'names')));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to API is not tested
     * @return array
     */
    public function getProperties()
    {
        try {
            $response = $this->call('GET', $this->getEndpoint('properties'));
        } catch (CustomerException $e) {
            $this->log->warning('Permission for properties is not granted.');

            return [];
        }

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to API is not tested
     * @return array
     */
    public function getItemProperties()
    {
        $response = $this->call('GET', $this->getEndpoint('items/properties', array('with' => 'names')));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to API is not tested
     * @return array
     */
    public function getPropertyGroups()
    {
        $response = $this->call('GET', $this->getEndpoint('items/property_groups', array('with' => 'names')));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to API is not tested
     * @return array
     */
    public function getStores()
    {
        $response = $this->call('GET', $this->getEndpoint('webstores'));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to API is not tested
     * @param int $attributeId
     * @return array
     */
    public function getAttributeValues($attributeId)
    {
        $params = array('with' => 'names');
        $response = $this->call('GET', $this->getEndpoint('items/attributes/' . $attributeId . '/values/', $params));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to API is not tested
     * @return array
     */
    public function getUnits()
    {
        $response = $this->call('GET', $this->getEndpoint('items/units'));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to API is not tested
     * @param int $id
     * @return array
     */
    public function getProduct($id)
    {
        $response = $this->call('GET', $this->getEndpoint('items/' . $id));

        return $this->returnResult($response);
    }

    /**
     * @param string|null $language
     * @return array
     */
    public function getProducts($language = null)
    {
        $params = array();

        if ($language) {
            $params['lang'] = $language;
        }

        $response = $this->call('GET', $this->getEndpoint('items/', $params));

        return $this->returnResult($response);
    }

    /**
     * @param array $productIds
     * @param bool|int $storePlentyId
     * @return array
     */
    public function getProductVariations(array $productIds, $storePlentyId = false)
    {
        $params = [
            'with' => [
                'variationSalesPrices',
                'variationBarcodes',
                'variationCategories',
                'variationAttributeValues',
                'variationClients',
                'variationProperties',
                'properties',
                'itemImages',
                'unit',
                'stock',
                'tags'
            ],
            'isActive' => true,
            'itemId' => $productIds
        ];

        if ($storePlentyId) {
            $params['plentyId'] = $storePlentyId;
        }

        $response = $this->call('GET', $this->getEndpoint('items/variations', $params));

        return $this->returnResult($response);
    }

    /* End of API calls */

    /**
     * Parse the results from API
     *
     * @param HTTP_Request2_Response $response
     * @return array
     */
    protected function returnResult(HTTP_Request2_Response $response)
    {
        return json_decode($response->getBody(), true);
    }

    /**
     * Format method call with endpoint URL and given params
     *
     * @param string $method
     * @param array|null $params
     * @return string
     */
    protected function getEndpoint($method, $params = null)
    {
        $query = '';

        // Set page and itemsPerPage params if they are provided by setters
        if ($this->page) {
            $params['page'] = $this->page;
        }

        if ($this->itemsPerPage) {
            $params['itemsPerPage'] = $this->itemsPerPage;
        }

        // The itemPerPage and page properties should be reset after every call as the caller methods should
        // take the actions for setting them again
        $this->itemsPerPage = false;
        $this->page = false;

        // Process params to URL
        if ($params) {
            $query = '?';
            $count = 0;
            $totalParams = count($params);
            foreach ($params as $key => $value) {
                $count++;
                if (is_array($value)) {
                    // If value is array it should be separated by commas in this API
                    $query .= $key . '=' . implode(",", $value);
                } else {
                    $query .= $key . '=' . $value;
                }

                if ($count < $totalParams) {
                    $query .= '&';
                }
            }
        }

        return $this->protocol . $this->getUrl() . $method . $query;
    }

    /**
     * Call the REST client to get a response
     *
     * @param string $method
     * @param string $uri
     * @param array|null $params
     * @return bool|mixed
     * @throws ThrottlingException
     * @throws AuthorizationException
     */
    protected function call($method, $uri, $params = null)
    {
        $begin = microtime(true);

        $response = false;
        $continue = true;
        $count = 0;

        /**
         * @var HTTP_Request2 $request
         */
        $request = $this->createRequest($method, $uri, $params);

        // Use while cycle for retrying the call if previous call failed until limit is reached
        while ($continue) {
            try {
                $count++;

                $this->handleThrottling();
                $response = $request->send();

                if ($this->debug) {
                    $this->debug->debugCall($request, $response);
                }

                $this->isResponseValid($response);
                $this->checkThrottling($response);

                $continue = false;
            } catch (AuthorizationException $e) {
                if ($this->getLoginFlag()) {
                    throw $e;
                }

                $this->refreshLogin();
                $this->setDefaultParams($request);
            } catch (Exception $e) {
                if ($e instanceof ThrottlingException || $count >= self::RETRY_COUNT) {
                    // Throw exception instantly if it is throttling exception
                    // or check if retry limit was reached to stop retry cycle
                    throw $e;
                } else {
                    usleep(100000);
                }
            }
        }

        $end = microtime(true);

        if ($this->debug) {
            $this->debug->logCallTiming($uri, $begin, $end);
        }

        return $response;
    }

    /**
     * Check response for appropriate statuses to validate if it was successful
     *
     * @param HTTP_Request2_Response $response
     * @return bool
     * @throws CustomerException
     * @throws ThrottlingException
     * @throws AuthorizationException
     */
    protected function isResponseValid(HTTP_Request2_Response $response)
    {
        // Method is not reachable because provided API user do not have appropriate access rights
        if ($response->getStatus() == 401 && $response->getReasonPhrase() == 'Unauthorized') {
            throw new AuthorizationException('Provided REST client is not logged in!');
        }

        if ($response->getStatus() == 403) {
            throw new CustomerException('Provided REST client does not have access rights for method with URL: ' . $response->getEffectiveUrl());
        }

        if ($response->getStatus() == 429) {
            throw new ThrottlingException('Throttling limit reached!');
        }

        // Method is not reachable, maybe server is down
        if ($response->getStatus() != 200) {
            throw new CustomerException('Could not reach API method for ' . $response->getEffectiveUrl());
        }

        if ($this->returnResult($response) === null) {
            throw new CustomerException("API responded with " . $response->getStatus() . " but didn't return any data.");
        }

        return true;
    }

    /**
     * Create request and set default parameters
     *
     * @param string $method - 'GET', 'POST' , etc.
     * @param string $uri - full endpoint path with query (GET) parameters
     * @param array|null $params - POST parameters
     * @return HTTP_Request2
     */
    protected function createRequest($method, $uri, $params = null)
    {
        $request = new HTTP_Request2($uri, $method);
        $request->setAdapter('curl');

        // Ignore setting default params for login method as it not required
        if (!$this->getLoginFlag()) {
            $this->setDefaultParams($request);
        }

        if ($method == 'POST' && is_array($params) && !empty($params)) {
            foreach ($params as $parameter => $value) {
                $request->addPostParameter($parameter, $value);
            }
        }

        return $request;
    }

    /**
     * Set default request params for request
     *
     * @param HTTP_Request2 $request
     * @return $this
     */
    protected function setDefaultParams(HTTP_Request2 $request)
    {
        if (!$this->getAccessToken()) {
            $this->login();
        }

        $request->setHeader('Authorization', 'Bearer ' . $this->getAccessToken());

        return $this;
    }

    /**
     * Recalculate time out before next request if throttling limit is reached
     */
    protected function handleThrottling()
    {
        $timeOut = $this->getThrottlingTimeout();

        if ($timeOut) {
            // Reduce timeout between requests by the time spent handling the response data
            if ($this->getLastTimeout()) {
                $timeOut = $timeOut - (time() - $this->getLastTimeout()) + 1;
            }

            $this->log->warning('Throttling limit reached. Will be waiting for ' . $timeOut . ' seconds.');
            usleep($timeOut * 1000000);
        }

        $this->setLastTimeout(false);
        $this->setThrottlingTimeout(false);
    }

    /**
     * Check response headers to know if API throttling limit is reached and handle the situation
     *
     * @param HTTP_Request2_Response $response
     * @throws ThrottlingException
     */
    protected function checkThrottling(HTTP_Request2_Response $response)
    {
        if ($response->getHeader(self::GLOBAL_LONG_CALLS_LEFT_COUNT) == 1) {
            //TODO: maybe check if global time out is not so long and wait instead of stopping execution
            $this->log->alert('Global throttling limit reached.');
            throw new ThrottlingException();
        }

        if ($response->getHeader(self::METHOD_CALLS_LEFT_COUNT) == 1) {
            $this->setLastTimeout(time());
            $this->setThrottlingTimeout($response->getHeader(self::METHOD_CALLS_WAIT_TIME));
            return;
        }

        if ($response->getHeader(self::GLOBAL_SHORT_CALLS_LEFT_COUNT) == 1) {
            $this->setLastTimeout(time());
            $this->setThrottlingTimeout($response->getHeader(self::GLOBAL_SHORT_CALLS_WAIT_TIME));
            return;
        }
    }
}
