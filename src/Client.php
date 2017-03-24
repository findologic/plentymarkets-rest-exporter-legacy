<?php

namespace Findologic\Plentymarkets;

use HTTP_Request2;
use Logger;
use Findologic\Plentymarkets\Exception\CriticalException;

class Client
{
    const RETRY_COUNT = 5;

    protected $username;
    protected $password;
    protected $endpoint;
    protected $token;
    protected $refreshToken;
    /**
     * @var Logger
     */
    protected $logger;
    protected $loginFlag = false;

    /**
     * @param string $username
     * @param string $password
     * @param string $endpoint
     * @param HTTP_Request2 $client
     */
    public function __construct($username, $password, $endpoint, Logger $logger)
    {
        $this->username = $username;
        $this->password = $password;
        $this->endpoint = $endpoint;
        $this->logger = $logger;
    }

    /**
     * @return null|string
     */
    public function getToken()
    {
        return $this->token;
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
        $this->loginFlag = true;

        $response = $this->call(
            'POST',
            $this->getEndpoint('login'),
            array(
                'username' => $this->username,
                'password' => $this->password
            )
        );

        if (!$response || $response->getStatus() != 200) {
            throw new Exception\CriticalException('Could not connect to api!');
        }

        $data = json_decode($response->getBody());

        if (!property_exists($data, 'accessToken')) {
            throw new Exception\CriticalException("Incorrect login to api, response do not have access token!");
        }

        $this->token = $data->accessToken;
        //TODO: check refresh token functionality and how it should be reused
        $this->refreshToken = $data->refreshToken;
        $this->loginFlag = false;

        return $this;
    }

    public function getCategories()
    {
        $params = array('type' => 'item', 'with' => 'details');
        $response = $this->call('GET', $this->getEndpoint('categories/', $params));

        return $this->returnResult($response);
    }

    public function getVat()
    {
        $response = $this->call('GET', $this->getEndpoint('vat/'));

        return $this->returnResult($response);
    }

    public function getSalesPrices()
    {
        $response = $this->call('GET', $this->getEndpoint('items/sales_prices/'));

        return $this->returnResult($response);
    }

    public function getAttributes()
    {
        $response = $this->call('GET', $this->getEndpoint('items/attributes'));

        return $this->returnResult($response);
    }

    public function getAttributeName($attributeId)
    {
        $response = $this->call('GET', $this->getEndpoint('items/attributes/' . $attributeId . '/names/'));

        return $this->returnResult($response);
    }

    public function getAttributeValues($attributeId)
    {
        $response = $this->call('GET', $this->getEndpoint('items/attributes/' . $attributeId . '/values/'));

        return $this->returnResult($response);
    }

    public function getAttributeValueName($valueId)
    {
        $response = $this->call('GET', $this->getEndpoint('items/attribute_values/' . $valueId . '/names/'));

        return $this->returnResult($response);
    }

    /**
     * @param string $id
     * @return array
     */
    public function getProduct($id)
    {
        $response = $this->call('GET', $this->getEndpoint('items/' . $id));

        return $this->returnResult($response);
    }


    /**
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function getProducts($offset = null, $limit = null)
    {
        //TODO: offset and limit implementation
        $params = array('with' => 'itemProperties');
        $response = $this->call('GET', $this->getEndpoint('items/', $params));

        return $this->returnResult($response);
    }

    /**
     * @param string $productId
     * @return array
     */
    public function getProductVariations($productId)
    {
        $params = array(
            'with' => array('variationSalesPrices', 'variationBarcodes', 'variationCategories', 'variationAttributeValues')
        );

        $response = $this->call('GET', $this->getEndpoint('items/' . $productId . '/variations', $params));

        return $this->returnResult($response);
    }

    public function getVariationProperties($itemId, $variationId)
    {
        $response = $this->call(
            'GET',
            $this->getEndpoint('items/' . $itemId . '/variations/' . $variationId . '/variation_properties')
        );

        return $this->returnResult($response);
    }

    /**
     * @param string $productId
     * @return array
     */
    public function getProductImages($productId)
    {
        $response = $this->call('GET', $this->getEndpoint('items/' . $productId . '/images'));

        return $this->returnResult($response);
    }

    /* End of api calls */

    /**
     * Parse the result from api
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

        if ($params) {
            $query = '?';
            $count = 0;
            foreach ($params as $key => $value) {
                $count++;
                if (is_array($value)) {
                    //if value is array it should be separated by commas in this api
                    $query .= $key . '=' . implode(",", $value);
                } else {
                    $query .= $key . '=' . $value;
                }

                if ($count < count($params)) {
                    $query .= '&';
                }
            }
        }

        return $this->endpoint . $method . $query;
    }

    /**
     * Call the rest client to get response
     *
     * @param $method
     * @param $uri
     * @param $params
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

        while ($continue) {
            try {
                $count++;

                $response = $request->send();

                if ($response->getStatus() != 200) {
                    throw new Exception\CustomerException('Could not call api method for ' . $uri);
                }

                $continue = false;
            } catch (\Exception $e) {
                // If call to api was not successful check if retry limit was reached to stop retry cicle
                if ($count >= self::RETRY_COUNT) {
                    $continue = false;
                    $this->handleException($e);
                }
                //TODO: maybe log unsuccessful calls even before they reach the retry limit ?
            }
        }

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
