<?php

namespace Findologic\Plentymarkets;

use HTTP_Request2;
use Logger;
use Findologic\Plentymarkets\Exception\CriticalException;
use Findologic\Plentymarkets\Exception\CustomerException;

class Client
{
    const RETRY_COUNT = 5;

    protected $username;
    protected $password;
    protected $url;
    protected $token;
    protected $refreshToken;
    /**
     * @var Logger
     */
    protected $logger;

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
     * @param string $username
     * @param string $password
     * @param string $url
     * @param Logger $logger
     * @param bool $debug
     */
    public function __construct($username, $password, $url, Logger $logger, $debug = false)
    {
        $this->username = $username;
        $this->password = $password;
        $url = rtrim($url, '/') . '/rest/';
        $this->url = $url;
        $this->logger = $logger;
        $this->debug = $debug;
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
     * @return string
     */
    public function getProtocol()
    {
        return $this->protocol;
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
                'username' => $this->username,
                'password' => $this->password
            )
        );

        // If using incorrect protocol the api returns 301 so it could be used to check if correct protocal is used
        // and make appropriate changes
        if ($response && $response->getStatus() == 301) {
            $this->protocol = 'http://';
            $response = $this->call('POST', $this->getEndpoint('login'), array(
                    'username' => $this->username,
                    'password' => $this->password
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
    public function getCategories()
    {
        $params = array('type' => 'item', 'with' => 'details');
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
    public function getProducts()
    {
        $params = array('with' => 'itemProperties');
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
            'with' => array('variationSalesPrices', 'variationBarcodes', 'variationCategories', 'variationAttributeValues', 'unit')
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

        return $this->protocol . $this->url . $method . $query;
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

                if ($response->getStatus() != 200) {
                    throw new CustomerException('Could not call api method for ' . $uri);
                }

                $continue = false;
            } catch (\Exception $e) {
                // If call to api was not successful check if retry limit was reached to stop retry cycle
                if ($count >= self::RETRY_COUNT) {
                    $continue = false;
                    $this->handleException($e);
                } else {
                    usleep(250000);
                }
            }
        }

        // The itemPerPage and page properties should be reseted after every call as the caller methods should
        // take the actions for setting them again
        $this->itemsPerPage = false;
        $this->page = false;

        return $response;
    }

    /**
     * Create request and set default parameters
     *
     * @param string $method
     * @param string $uri
     * @param array $params
     * @return HTTP_Request2
     */
    protected function createRequest($method, $uri, $params = null)
    {
        $request = new HTTP_Request2($uri, $method);
        $request->setAdapter('curl');

        // ignore setting default params for login method as it not required
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

    /**
     * @codeCoverageIgnore
     * @param \Exception $e
     */
    protected function handleException($e)
    {
        //TODO: logging implementation
        if ($e instanceof CriticalException) {
            $this->logger->error($e->getMessage());
            die();
        }
    }
}
