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
    use handleRequestHeader,
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
     * @var Socket
     */
    public $Socket;

    protected $document_completed;

    protected $document_received_completely;

    /**
     *
     */
    public function fetch() {

        $this->Socket = $Socket = new Socket();

        $Socket->UrlParsDescriptor = $this->UrlPartsDescriptor;

        $Socket->open();

        $requestHeaderRaw = $this->buildRequestHeaderRaw();

        var_dump($requestHeaderRaw);

        $Socket->send($requestHeaderRaw);

        $responseHeaderRaw = $this->readResponseHeader();

        $this->ResponseHeader = new ResponseHeader($requestHeaderRaw, $this->UrlDescriptor->url_rebuild);

        $responseBodyRaw = $this->readResponseBody();


        var_dump(
            $responseHeaderRaw
        );

        var_dump($responseBodyRaw);

        var_dump($Socket);

        $Socket->close();

        var_dump($Socket);
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
