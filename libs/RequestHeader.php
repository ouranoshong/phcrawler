<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 9/26/16
 * Time: 4:14 PM
 */

namespace PhCrawler;

use PhCrawler\Enums\HttpProtocols;
use PhCrawler\Utils\Encoding;
use PhCrawler\Utils\Utils;


/**
 * Class RequestHeader
 *
 * @package PhCrawler
 */
class RequestHeader
{
    /**
     * @var \PhCrawler\Request
     */
    protected $_Request;

    /**
     *
     */
    const SEPARATOR = "\r\n";

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
     * @var
     */
    public $method;

    /**
     * @var
     */
    public $refering_url;

    /**
     * @var
     */
    public $http_protocol_version;


    /**
     * RequestHeader constructor.
     *
     * @param \PhCrawler\Request $Request
     */
    public function __construct(Request $Request)
    {
        $this->_Request = $Request;

        $this->init($Request);

    }

    /**
     * @param \PhCrawler\Request $Request
     */
    public function init(Request $Request)
    {

        if (count($Request->post_data) > 0) $this->method = RequestHeader::METHOD_POST;
        else $this->method = RequestHeader::METHOD_GET;

        if ($Request->http_protocol_version === HttpProtocols::HTTP_1_1) $this->http_protocol_version = RequestHeader::HTTP_VERSION_1_1;
        else $this->http_protocol_version = RequestHeader::HTTP_VERSION_1_0;

    }

    /**
     * @return array
     */
    public function buildLines()
    {
        // Create header
        $headerLines = [];
        $headerLines[] = $this->buildFirstLine();
        $headerLines[] = $this->buildHostLine();
        $headerLines[] = $this->buildUserAgentLine();
        $headerLines[] = $this->buildAcceptLine();
        $headerLines[] = $this->buildAcceptEncodingLine();
        $headerLines[] = $this->buildReferingUrlLine();
        $headerLines[] = $this->buildCookieLine();
        $headerLines[] = $this->buildAuthenticationLine();
        $headerLines[] = $this->buildProxyAuthorizationLine();
        $headerLines[] = $this->buildConnectionLine();
        $headerLines = array_merge($headerLines, $this->buildPostContentLines());
        return array_values(array_filter($headerLines));
    }

    /**
     * @return string
     */
    protected function buildFirstLine()
    {
        $_Request = $this->_Request;

        if ($_Request->proxy != null) {
            // A Proxy needs the full qualified URL in the GET or POST headerline.
            $line = $this->method . " " . $_Request->UrlDescriptor->url_rebuild . " HTTP/1.0";
        } else {
            $query = $this->buildHttpRequestQuery();
            $line = $this->method . " " . $query . " HTTP/" . $this->http_protocol_version;
        }

        return $line . self::SEPARATOR;
    }


    /**
     * @return string
     */
    protected function buildHostLine()
    {
        return "Host: " . $this->_Request->url_parts["host"] . self::SEPARATOR;;
    }

    /**
     * @return string
     */
    protected function buildUserAgentLine()
    {
        return "User-Agent: " . str_replace("\n", "", $this->_Request->userAgentString) . self::SEPARATOR;
    }

    /**
     * @return string
     */
    protected function buildAcceptLine()
    {
        return "Accept: */*" . self::SEPARATOR;
    }

    /**
     * @return string
     */
    protected function buildAcceptEncodingLine()
    {
        // Request GZIP-content
        if ($this->_Request->request_gzip_content == true) {
            return "Accept-Encoding: gzip, deflate" . self::SEPARATOR;
        }
        return '';
    }

    /**
     * @return string
     */
    protected function buildReferingUrlLine()
    {
        // Referer
        $refering_url = $this->_Request->UrlDescriptor->refering_url;

        if ($refering_url != null) {
            return "Referer: " . $refering_url . self::SEPARATOR;
        }
        return '';
    }

    /**
     * @return string
     */
    protected function buildCookieLine()
    {
        $cookie_header = $this->buildCookieHeader();
        if ($cookie_header != null)
            return $cookie_header;
        return '';
    }

    /**
     * @return string
     */
    protected function buildAuthenticationLine()
    {
        // Authentication
        $_Request = $this->_Request;

        if ($_Request->url_parts["auth_username"] != "" && $_Request->url_parts["auth_password"] != "") {
            $auth_string = base64_encode($_Request->url_parts["auth_username"] . ":" . $_Request->url_parts["auth_password"]);
            return "Authorization: Basic " . $auth_string . self::SEPARATOR;
        }
        return '';
    }

    /**
     * @return string
     */
    protected function buildProxyAuthorizationLine()
    {
        // Proxy authentication
        $_Request = $this->_Request;
        if ($_Request->proxy != null && $_Request->proxy["proxy_username"] != null) {
            $auth_string = base64_encode($_Request->proxy["proxy_username"] . ":" . $_Request->proxy["proxy_password"]);
            return "Proxy-Authorization: Basic " . $auth_string . self::SEPARATOR;
        }
        return '';
    }

    /**
     * @return string
     */
    protected function buildConnectionLine()
    {
        return "Connection: close" . self::SEPARATOR;
    }

    /**
     * @return array
     */
    protected function buildPostContentLines()
    {
        // Wenn POST-Request
        $headerLines = [];

        if ($this->method == RequestHeader::METHOD_POST) {
            // Post-Content bauen
            $post_content = $this->buildPostContent();

            $headerLines[] = "Content-Type: multipart/form-data; boundary=---------------------------10786153015124".self::SEPARATOR;
            $headerLines[] = "Content-Length: " . strlen($post_content) . (self::SEPARATOR.self::SEPARATOR);
            $headerLines[] = $post_content;
        } else {
            $headerLines[] = self::SEPARATOR;
        }

        return $headerLines;
    }

    /**
     *
     * @return mixed|string
     */
    protected function buildHttpRequestQuery()
    {
        $_Request = $this->_Request;
        $query = $_Request->url_parts["path"] . $_Request->url_parts["file"] . $_Request->url_parts["query"];
        // If string already is a valid URL -> do nothing
        if (Utils::isValidUrlString($query)) {
            return $query;
        }

        // Decode query-string (for URLs that are partly urlencoded and partly not)
        $query = rawurldecode($query);

        // if query is already utf-8 encoded -> simply urlencode it,
        // otherwise encode it to utf8 first.
        if (Encoding::isUTF8String($query) == true) {
            $query = rawurlencode($query);
        } else {
            $query = rawurlencode(utf8_encode($query));
        }

        // Replace url-specific signs back
        $query = str_replace("%2F", "/", $query);
        $query = str_replace("%3F", "?", $query);
        $query = str_replace("%3D", "=", $query);
        $query = str_replace("%26", "&", $query);

        return $query;
    }

    /**
     * Builds the cookie-header-part for the header to send.
     *
     * @return string  The cookie-header-part, i.e. "Cookie: test=bla; palimm=palaber"
     *                 Returns NULL if no cookies should be send with the header.
     */
    protected function buildCookieHeader()
    {
        $cookie_string = "";

        @reset($this->_Request->cookie_array);
        while (list($key, $value) = @each($this->_Request->cookie_array)) {
            $cookie_string .= "; " . $key . "=" . $value . "";
        }

        if ($cookie_string != "") {
            return "Cookie: " . substr($cookie_string, 2) . self::SEPARATOR;
        } else {
            return null;
        }
    }

    /**
     * Builds the post-content from the postdata-array for the header to send with the request (MIME-style)
     *
     * @return array  Numeric array containing the lines of the POST-part for the header
     */
    protected function buildPostContent()
    {
        $_Request = $this->_Request;
        $post_content = "";

        // Post-Data
        @reset($_Request->post_data);
        while (list($key, $value) = @each($_Request->post_data)) {
            $post_content .= "-----------------------------10786153015124";
            $post_content .= self::SEPARATOR;
            $post_content .= "Content-Disposition: form-data; name=\"" . $key . "\"";
            $post_content .= self::SEPARATOR . self::SEPARATOR;
            $post_content .= $value;
            $post_content .= self::SEPARATOR;
        }

        $post_content .= "-----------------------------10786153015124";
        $post_content .= self::SEPARATOR;

        return $post_content;
    }

}
