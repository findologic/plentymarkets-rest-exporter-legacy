<?php

namespace Findologic\Plentymarkets;

use Findologic\Plentymarkets\Exception\CriticalException;
use Findologic\Plentymarkets\Exception\CustomerException;
use \HTTP_Request2;
use \Logger;

class Client
{
    const RETRY_COUNT = 5;

    /**
     * Rest api url
     *
     * @var string
     */
    protected $url;

    /**
     * Rest login token
     *
     * @var
     */
    protected $token;

    /**
     * Rest login refresh token
     *
     * @var
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
     * Flag fol login call to api to avoid setting the headers for this call
     *
     * @var bool
     */
    protected $loginFlag = false;

    /**
     * @var bool|\Findologic\Plentymarkets\Debugger
     */
    protected $debug = false;

    /**
     * Connection to api protocol (some websites could use http other https)
     *
     * @var string
     */
    protected $protocol = 'https://';

    /**
     * Variable to allow setting items per page on request without adding this as an property
     * to every class method responsible for calling the api
     *
     * @var bool|int
     */
    protected $itemsPerPage = false;

    /**
     * Variable to allow setting page number without adding this as an property
     * to every class method responsible for calling the api
     *
     * @var bool|int
     */
    protected $page = false;

    /**
     * @var \PlentyConfig
     */
    protected $config;

    /**
     * @param \PlentyConfig $config
     * @param \Logger $log
     * @param \Logger $customerLog
     * @param bool $debug
     */
    public function __construct(\PlentyConfig $config, Logger $log, Logger $customerLog, $debug = false)
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
     * @return \PlentyConfig
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
     * Get the token for api call authorization
     *
     * @return null|string
     */
    public function getToken()
    {
        return $this->token;
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
     * @return \Logger
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * @codeCoverageIgnore
     * @return \Logger
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


    /* Api calls */

    /**
     * Call login method and save api token for further calls
     *
     * @return $this
     * @throws Exception\CriticalException
     */
    public function login()
    {
        // Set the login flag so the setDefaultParams() would not be called as this method tries to call login() method
        // if token is empty
        $this->loginFlag = true;

        $response = $this->call('POST', $this->getEndpoint('login'), array(
                'username' => $this->config->getUsername(),
                'password' => $this->config->getPassword()
            )
        );

        // If using incorrect protocol the api returns status between 301-404  so it could be used to check if correct
        // protocol is used and make appropriate changes
        if ($response && ($response->getStatus() >= 301 && $response->getStatus() <= 404)) {
            $this->protocol = 'http://';
            $this->getLog()->info('Api client requests protocol changed to http://)');
            $response = $this->call('POST', $this->getEndpoint('login'), array(
                    'username' => $this->config->getUsername(),
                    'password' => $this->config->getPassword()
                )
            );
        }

        if (!$response || $response->getStatus() != 200) {
            throw new CriticalException('Could not connect to api!');
        }

        $data = json_decode($response->getBody());

        if (!property_exists($data, 'accessToken')) {
            throw new CriticalException("Incorrect login to api, response do not have access token!");
        }

        $this->token = $data->accessToken;
        $this->loginFlag = false;

        return $this;
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to api is not tested
     * @return array
     */
    public function getStandardVat($shopId)
    {
        $params = array('plentyId' => $shopId);

        $response = $this->call('GET', $this->getEndpoint('vat/standard', $params));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to api is not tested
     * @return array
     */
    public function getWebstores()
    {
        $response = $this->call('GET', $this->getEndpoint('webstores'));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to api is not tested
     * @return array
     */
    public function getCategories($storeId = null)
    {
        $params = array('type' => 'item', 'with' => 'details');

        if ($storeId) {
            $params['plentyId'] = $storeId;
        }

        $response = $this->call('GET', $this->getEndpoint('categories/', $params));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to api is not tested
     * @return array
     */
    public function getCategoriesBranches()
    {
        $response = $this->call('GET', $this->getEndpoint('category_branches/'));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to api is not tested
     * @return array
     */
    public function getVat()
    {
        $response = $this->call('GET', $this->getEndpoint('vat/'));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to api is not tested
     * @return array
     */
    public function getSalesPrices()
    {
        $response = $this->call('GET', $this->getEndpoint('items/sales_prices/'));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to api is not tested
     * @return array
     */
    public function getManufacturers()
    {
        $response = $this->call('GET', $this->getEndpoint('items/manufacturers/'));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to api is not tested
     * @return array
     */
    public function getAttributes()
    {
        $params = array('with' => 'names');
        $response = $this->call('GET', $this->getEndpoint('items/attributes', $params));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to api is not tested
     * @return array
     */
    public function getPropertyGroups()
    {
        $response = $this->call('GET', $this->getEndpoint('items/property_groups'));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to api is not tested
     * @return array
     */
    public function getStores()
    {
        $response = $this->call('GET', $this->getEndpoint('webstores'));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to api is not tested
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
     * @codeCoverageIgnore - Ignore this method as actual call to api is not tested
     * @return array
     */
    public function getUnits()
    {
        $response = $this->call('GET', $this->getEndpoint('items/units'));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to api is not tested
     * @param int $itemId
     * @param int $variationId
     * @return array
     */
    public function getVariationProperties($itemId, $variationId)
    {
        $response = $this->call(
            'GET',
            $this->getEndpoint('items/' . $itemId . '/variations/' . $variationId . '/variation_properties')
        );

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to api is not tested
     * @param int $productId
     * @return array
     */
    public function getProductImages($productId)
    {
        $response = $this->call('GET', $this->getEndpoint('items/' . $productId . '/images'));

        return $this->returnResult($response);
    }

    /**
     * @codeCoverageIgnore - Ignore this method as actual call to api is not tested
     * @param int $id
     * @return array
     */
    public function getProduct($id)
    {
        $response = $this->call('GET', $this->getEndpoint('items/' . $id));

        return $this->returnResult($response);
    }

    /**
     * @return array
     */
    public function getProducts($language = null)
    {
        $params = array('with' => 'itemProperties');

        if ($language) {
            $params['lang'] = $language;
        }

        $response = $this->call('GET', $this->getEndpoint('items/', $params));

        return $this->returnResult($response);
    }

    /**
     * @param int $productId
     * @return array
     */
    public function getProductVariations($productId)
    {
        $params = array(
            'with' =>
                array(
                    'variationSalesPrices',
                    'variationBarcodes',
                    'variationCategories',
                    'variationAttributeValues',
                    'variationClients',
                    'unit',
                    'stock'
                ),
            'isActive' => true
        );

        $response = $this->call('GET', $this->getEndpoint('items/' . $productId . '/variations', $params));

        return $this->returnResult($response);
    }

    /* End of api calls */

    /**
     * Parse the results from api
     *
     * @param $response \HTTP_Request2_Response
     * @return array
     */
    protected function returnResult($response)
    {
        return json_decode($response->getBody(), true);
    }

    /**
     * Format method call with endpoint url and given params
     *
     * @param string $method
     * @return string
     */
    protected function getEndpoint($method, $params = null)
    {
        $query = '';

        //Set page and itemsPerPage params if they are provided by setters
        if ($this->page) {
            $params['page'] = $this->page;
        }

        if ($this->itemsPerPage) {
            $params['itemsPerPage'] = $this->itemsPerPage;
        }

        //Process params to url
        if ($params) {
            $query = '?';
            $count = 0;
            $totalParams = count($params);
            foreach ($params as $key => $value) {
                $count++;
                if (is_array($value)) {
                    //if value is array it should be separated by commas in this api
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
     * Call the rest client to get response
     *
     * @param string $method
     * @param string $uri
     * @param array $params
     * @return bool|mixed
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

                $response = $request->send();

                if ($this->debug) {
                    $this->debug->debugCall($request, $response);
                }

                $this->isResponseValid($response);

                $continue = false;
            } catch (\Exception $e) {
                // If call to api was not successful check if retry limit was reached to stop retry cycle
                if ($count >= self::RETRY_COUNT) {
                    throw $e;
                } else {
                    usleep(100000);
                }
            }
        }

        // The itemPerPage and page properties should be reset after every call as the caller methods should
        // take the actions for setting them again
        $this->itemsPerPage = false;
        $this->page = false;

        $end = microtime(true);

        if ($this->debug) {
            $this->debug->logCallTiming($uri, $begin, $end);
        }

        return $response;
    }

    /**
     * Check response for appropriate statuses to validate if it was successful
     *
     * @param \HTTP_Request2_Response $response
     * @return bool
     * @throws CriticalException
     * @throws CustomerException
     */
    protected function isResponseValid($response)
    {
        // Method is not reachable because provided api user do not have appropriate access rights
        if ($response->getStatus() == 401 && $response->getReasonPhrase() == 'Unauthorized') {
            throw new CriticalException('Provided rest client do not have access rights for method with url: ' . $response->getEffectiveUrl());
        }

        // Method is not reachable, maybe server is down
        if ($response->getStatus() != 200) {
            throw new CustomerException('Could not reach api method for ' . $response->getEffectiveUrl());
        }

        return true;
    }

    /**
     * Create request and set default parameters
     *
     * @param string $method - 'GET', 'POST' , etc.
     * @param string $uri - full endpoint path with query (GET) parameters
     * @param array $params - POST parameters
     * @return HTTP_Request2
     */
    protected function createRequest($method, $uri, $params = null)
    {
        $request = new HTTP_Request2($uri, $method);
        $request->setAdapter('curl');

        // Ignore setting default params for login method as it not required
        if (!$this->loginFlag) {
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
     * @param \HTTP_Request2 $request
     * @return $this
     */
    protected function setDefaultParams($request)
    {
        if (!$this->getToken()) {
            $this->login();
        }

        $request->setHeader('Authorization', 'Bearer ' . $this->getToken());

        return $this;
    }
}
