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
    use handleRequestHeader;

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


    public $userAgent = 'PhCrawler';

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
     *
     */
    public function fetch() {
        $Socket = new Socket();
        $Socket->UrlParsDescriptor = $this->UrlPartsDescriptor;
        $Socket->open();
        $Socket->send($this->buildRequestHeaderRaw());

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
