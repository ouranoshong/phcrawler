<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 9/29/16
 * Time: 4:10 PM
 */

namespace PhCrawler\Http;


use PhCrawler\Http\Descriptors\CookieDescriptor;
use PhCrawler\Http\Utils\UrlUtil;

class ResponseHeader
{

    public function __construct($header_string, $source_url)
    {
        $this->header_raw = $header_string;
        $this->source_url = $source_url;

        $this->http_status_code = self::parseHttpStatusCode($header_string);
        $this->content_type = strtolower(UrlUtil::getHeaderValue($header_string, "content-type"));
        $this->content_length = strtolower(UrlUtil::getHeaderValue($header_string, "content-length"));
        $this->cookies = self::getCookies($header_string, $source_url);
        $this->transfer_encoding = strtolower(UrlUtil::getHeaderValue($header_string, "transfer-encoding"));
        $this->content_encoding = strtolower(UrlUtil::getHeaderValue($header_string, "content-encoding"));
    }

    /**
     * Gets the HTTP-statuscode from a given response-header.
     *
     * @param string $header  The response-header
     * @return int            The status-code or NULL if no status-code was found.
     */
    public static function parseHttpStatusCode($header)
    {
        $first_line = strtok($header, "\n");

        preg_match("# [0-9]{3}#", $first_line, $match);

        if (isset($match[0]))
            return (int)trim($match[0]);
        else
            return null;
    }

    /**
     * Returns all cookies from the give response-header.
     *
     * @param string $header      The response-header
     * @param string $source_url  URL the cookie was send from.
     * @return array Numeric array containing all cookies as CookieDescriptor-objects.
     */
    public static function getCookies($header, $source_url)
    {
        $cookies = array();

        $hits = preg_match_all("#[\r\n]set-cookie:(.*)[\r\n]# Ui", $header, $matches);

        if ($hits && $hits != 0)
        {
            for ($x=0; $x<count($matches[1]); $x++)
            {
                $cookies[] = CookieDescriptor::getFromHeaderLine($matches[1][$x], $source_url);
            }
        }

        return $cookies;
    }

    /**
     * The raw HTTP-header as it was send by the server
     *
     * @var string
     */
    public $header_raw;

    /**
     * The HTTP-statuscode
     *
     * @var int
     */
    public $http_status_code;

    /**
     * The content-type
     *
     * @var string
     */
    public $content_type;

    /**
     * The content-length as stated in the header.
     *
     * @var int
     */
    public $content_length;

    /**
     * The content-encoding as stated in the header.
     *
     * @var string
     */
    public $content_encoding;

    /**
     * The transfer-encoding as stated in the header.
     *
     * @var string
     */
    public $transfer_encoding;

    /**
     * All cookies found in the header
     *
     * @var array Numeric array containing all cookies as {@link PHPCrawlerCookieDescriptor}-objects
     */
    public $cookies = array();

    /**
     * The URL of the website the header was recevied from.
     *
     * @var string
     */
    public $source_url;


    public function isTransferEncodingChunked() {
        return $this->transfer_encoding == 'chunked';
    }
}
