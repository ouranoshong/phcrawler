<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 9/28/16
 * Time: 11:29 AM
 */

namespace PhCrawler\Http;


use PhCrawler\Benchmark;
use PhCrawler\Descriptors\ProxyDescriptor;
use PhCrawler\Descriptors\UrlDescriptor;
use PhCrawler\Descriptors\UrlPartsDescriptor;
use PhCrawler\Http\Enums\Protocols;
use PhCrawler\Http\Enums\Timer;
use PhCrawler\Http\Response\DocumentInfo;
use PhCrawler\Http\Response\ResponseHeader;

/**
 * Class Request
 *
 * @package PhCrawler\Http
 */
class HttpClient
{
    use handleDocumentInfo,
        handleRequestHeader,
        handleResponseHeader,
        handleResponseBody;

    /**
     *
     */
    const METHOD_GET = 'GET';
    /**
     *
     */
    const METHOD_POST = 'POST';

    /**
     *
     */
    const HTTP_VERSION_1_0 = '1.0';
    /**
     *
     */
    const HTTP_VERSION_1_1 = '1.1';


    /**
     * @var string
     */
    public $userAgent = 'PhCrawler';

    /**
     * @var bool
     */
    public $request_gzip_content = true;
    /**
     * @var string
     */
    protected $method;
    /**
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $http_protocol_version;

    /**
     * @var UrlDescriptor
     */
    public $UrlDescriptor;

    /**
     * @var UrlPartsDescriptor
     */
    public $UrlPartsDescriptor;

    /**
     * @var ProxyDescriptor
     */
    public $ProxyDescriptor;

    /**
     * @var array
     */
    protected $cookie_data = [];

    /**
     * @var array
     */
    protected $post_data = [];

    /**
     * @var int
     */
    protected $header_bytes_received = 0;

    /**
     * @var null
     */
    protected $server_response_time = null;

    /**
     * @var
     */
    protected $error_code;

    /**
     * @var
     */
    protected $error_message;


    /**
     * @var ResponseHeader
     */
    public $ResponseHeader;

    /**
     * @var DocumentInfo
     */
    public $DocumentInfo;

    /**
     * @var Socket
     */
    protected $Socket;

    /**
     * @var
     */
    protected $document_completed;

    /**
     * @var
     */
    protected $document_received_completely;

    /**
     * @var null | \Closure
     */
    public $response_header_check_callback_function = null;

    /**
     * @var int
     */
    protected $content_buffer_size = 200000;
    /**
     * @var int
     */
    protected $chunk_buffer_size = 20240;
    /**
     * @var int
     */
    protected $socket_read_buffer_size = 1024;
    /**
     * @var int
     */
    protected $source_overlap_size = 1500;

    /**
     * @var int
     */
    protected $content_size_limit = 0;

    /**
     * @var float | null
     */
    protected $content_bytes_received = null;

    /**
     * @var int
     */
    protected $global_traffic_count = 0;

    /**
     * @var null
     */
    protected $server_connect_time = null;

    /**
     * @var null
     */
    protected $socket_pre_fill_size = null;

    /**
     * @var null
     */
    protected $receive_content_types = null;


    /**
     * Sets the URL for the request.
     *
     * @param URLDescriptor $UrlDescriptor An URLDescriptor-object containing the URL to request
     */
    public function setUrl(URLDescriptor $UrlDescriptor)
    {
        $this->UrlDescriptor = $UrlDescriptor;

        if (!$this->UrlPartsDescriptor) {

            $this->UrlPartsDescriptor = new UrlPartsDescriptor();

        }

        $this->UrlPartsDescriptor->init($this->UrlDescriptor->url_rebuild);
    }

    /**
     * Adds a cookie to send with the request.
     *
     * @param string $name Cookie-name
     * @param string $value Cookie-value
     */
    public function addCookie($name, $value)
    {
        $this->cookie_data[$name] = $value;
    }

    /**
     * Adds a cookie to send with the request.
     *
     * @param CookieDescriptor $Cookie
     */
    public function addCookieDescriptor(CookieDescriptor $Cookie)
    {
        $this->addCookie($Cookie->name, $Cookie->value);
    }

    /**
     * Adds a bunch of cookies to send with the request
     *
     * @param array $cookies Numeric array containins cookies as CookieDescriptor-objects
     */
    public function addCookieDescriptors($cookies)
    {
        $cnt = count($cookies);
        for ($x=0; $x<$cnt; $x++)
        {
            $this->addCookieDescriptor($cookies[$x]);
        }
    }

    /**
     * Removes all cookies to send with the request.
     */
    public function clearCookies()
    {
        $this->cookie_data = array();
    }


    /**
     * @param $key
     * @param $value
     */
    public function addPostData($key, $value)
    {
        $this->post_data[$key] = $value;
    }

    /**
     * Removes all post-data to send with the request.
     */
    public function clearPostData()
    {
        $this->post_data = array();
    }


    public function setProxy(ProxyDescriptor $Proxy)
    {
        $this->ProxyDescriptor = $Proxy;
    }


    /**
     * @param $username
     * @param $password
     */
    public function setBasicAuthentication($username, $password)
    {
        if (!($this->UrlPartsDescriptor instanceof UrlPartsDescriptor)) {
            $this->UrlPartsDescriptor = new UrlPartsDescriptor();
        }

        $this->UrlPartsDescriptor->auth_username = $username;
        $this->UrlPartsDescriptor->auth_password = $password;
    }


    /**
     *
     */
    public function fetch() {

            $this->init();

            if (!$this->openSocket()) return $this->DocumentInfo;

            $this->sendRequestContent();

            return $this->readResponseContent();
    }

    /**
     * @throws \Exception
     */
    protected function init() {
        if (!$this->UrlDescriptor) {
            throw new \Exception('Require connection information!');
        }

        if (!$this->UrlPartsDescriptor) {
            $this->UrlPartsDescriptor = new UrlPartsDescriptor(
                $this->UrlDescriptor->url_rebuild
            );
        } else if (!$this->UrlPartsDescriptor->host) {
            $this->UrlPartsDescriptor->init($this->UrlDescriptor->url_rebuild);
        }

        if (!$this->http_protocol_version) {
            $this->http_protocol_version = Protocols::HTTP_1_1;
        }

        $this->initDocumentInfo();
    }

    /**
     * @return bool
     */
    protected function openSocket() {

        $this->Socket = $Socket = new Socket();
        $Socket->UrlParsDescriptor = $this->UrlPartsDescriptor;

        Benchmark::reset(Timer::SERVER_CONNECT);
        Benchmark::start(Timer::SERVER_CONNECT);

        if (!$Socket->open()) {

            $this->setServerConnectTime();
            $this->SetErrorMessage($Socket->error_message);
            $this->SetErrorCode($Socket->error_code);

            return false;
        }


        $this->setServerConnectTime(Benchmark::stop(Timer::SERVER_CONNECT));

        return true;
    }

    /**
     *
     */
    protected function sendRequestContent() {

        $requestHeaderRaw = $this->buildRequestHeaderRaw();

        $this->setDocumentHeaderSend($requestHeaderRaw);

        $this->Socket->send($requestHeaderRaw);

    }

    /**
     * @return \PhCrawler\Http\DocumentInfo
     */
    protected function readResponseContent() {

        $responseHeaderRaw = $this->readResponseHeader();

        $this->setDocumentHeaderReceived($responseHeaderRaw);

        $this->ResponseHeader = new ResponseHeader($responseHeaderRaw, $this->UrlDescriptor->url_rebuild);

        $this->setDocumentResponseHeader($this->ResponseHeader);

        $receive = $this->decideReceiveContent($this->ResponseHeader);

        if ($receive == false)
        {
            $this->Socket->close();
            $this->DocumentInfo->received = false;

            return $this->DocumentInfo;
        }
        else
        {
            $this->DocumentInfo->received = true;
        }

        $this->setDocumentContent($this->readResponseBody());

        $this->setDocumentStatistics();

        return $this->DocumentInfo;
    }

    /**
     * @return array|null
     */
    protected function calculateDataTransferRateValues()
    {

        $dataValues = array();

        $data_transfer_time = $this->getDataTransferTime();
        // Works like this:
        // After the server resonded, the socket-buffer is already filled with bytes,
        // that means they were received within the server-response-time.

        // To calulate the real data transfer rate, these bytes have to be substractred from the received
        // bytes beofre calulating the rate.

        if ($data_transfer_time > 0 && $this->content_bytes_received > 4 * $this->socket_pre_fill_size)
        {
            $dataValues["unbuffered_bytes_read"] = $this->content_bytes_received + $this->header_bytes_received - $this->socket_pre_fill_size;
            $dataValues["data_transfer_rate"] = $dataValues["unbuffered_bytes_read"] / $data_transfer_time;
            $dataValues["data_transfer_time"] = $data_transfer_time;
        }
        else
        {
            $dataValues = null;
        }

        return $dataValues;
    }


    /**
     * @param null $time
     */
    protected function setServerConnectTime($time = null) {
        $this->DocumentInfo->server_connect_time = $time;
    }

    /**
     * @param null $time
     */
    protected function setServerResponseTime($time = null) {
        $this->DocumentInfo->server_response_time = $time;
    }

    /**
     * @param null $time
     */
    protected function setDataTransferTime($time = null) {
        $this->DocumentInfo->data_transfer_time = $time;
    }

    /**
     * @return float
     */
    public function getDataTransferTime() {
        return $this->DocumentInfo->data_transfer_time;
    }

    /**
     * @param $message
     */
    protected function setErrorMessage($message) {
        $this->DocumentInfo->error_occured = true;
        $this->DocumentInfo->error_message = $message;
    }

    /**
     * @param $code
     */
    protected function setErrorCode($code) {
        $this->DocumentInfo->error_occured = true;
        $this->DocumentInfo->error_code = $code;
    }

}
