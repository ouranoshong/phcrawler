<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 9/28/16
 * Time: 11:29 AM
 */

namespace PhCrawler\Http;


use PhCrawler\Http\Descriptors\ProxyDescriptor;
use PhCrawler\Http\Descriptors\UrlDescriptor;
use PhCrawler\Http\Descriptors\UrlPartsDescriptor;

/**
 * Class Request
 *
 * @package PhCrawler\Http
 */
class Request
{
    use handleDocumentInfo,
        handleRequestHeader,
        handleResponseHeader,
        handleResponseBody;

    /**
     *
     */
    const HEADER_SEPARATOR = "\r\n";

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
    public $method;
    /**
     * @var string
     */
    public $url;

    /**
     * @var string
     */
    public $http_protocol_version;

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
    public $cookie_data = [];

    /**
     * @var array
     */
    public $post_data = [];

    /**
     * @var int
     */
    public $header_bytes_received = 0;

    /**
     * @var null
     */
    public $server_response_time = null;

    /**
     * @var
     */
    public $error_code;
    /**
     * @var
     */
    public $error_message;

    /**
     * @var ResponseHeader
     */
    public $ResponseHeader;

    /**
     * @var
     */
    public $DocumentInfo;

    /**
     * @var Socket
     */
    public $Socket;

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
    public $header_check_callback_function = null;

    /**
     * @var null
     */
    public $data_transfer_time = null;

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
     *
     */
    public function fetch() {

            if (!$this->openSocket()) return false;

            $this->sendRequestHeader();

            $responseHeaderRaw = $this->readResponseHeader();

            $this->ResponseHeader = new ResponseHeader($responseHeaderRaw, $this->UrlDescriptor->url_rebuild);

            $responseBodyRaw = $this->readResponseBody();

            $this->Socket->close();
    }


    /**
     * @return bool
     */
    protected function openSocket() {
        $this->Socket = $Socket = new Socket();
        $Socket->UrlParsDescriptor = $this->UrlPartsDescriptor;
        return $Socket->open();
    }

    /**
     *
     */
    protected function sendRequestHeader() {
        $requestHeaderRaw = $this->buildRequestHeaderRaw();
        $this->Socket->send($requestHeaderRaw);
    }

    /**
     *
     */
    public function get() {

    }

    /**
     *
     */
    public function post() {

    }




}
