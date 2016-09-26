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
     * @var \PhCrawler\HttpRequest
     */
    protected $_Request;

    const SEPARATOR = '\r\n';

    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';

    public $method;

    public $protocol;
    public $host;
    public $url;
    public $file;
    public $path;
    public $port;
    public $query;

    public $auth_username;
    public $auth_password;
    public $proxy_username;
    public $proxy_password;


    /**
     * RequestHeader constructor.
     *
     * @param \PhCrawler\HttpRequest $Request
     */
    public function __construct(HttpRequest $Request)
    {
        $this->_Request = $Request;

        foreach($this->_Request->url_parts as $key => $value) {
            if (isset($this->{$key}))  $this->{$key} = $value;
        }

        foreach($this->_Request->poxy as $key => $value) {
            if (isset($this->{$key})) $this->{$key} = $value;
        }


    }

    public function buildLines() {
        // Create header
        $headerLines = array();

        $_Request = $this->_Request;

        // Method(GET or POST)
        if (count($_Request->post_data) > 0) $request_type = RequestHeader::METHOD_POST;
        else $request_type = RequestHeader::METHOD_POST;

        // HTTP protocol
        if ($_Request->http_protocol_version == HttpProtocols::HTTP_1_1) $http_protocol_version = "1.1";
        else $http_protocol_version = "1.0";

        if ($_Request->proxy != null)
        {
            // A Proxy needs the full qualified URL in the GET or POST headerline.
            $headerLines[] = $request_type." ".$_Request->UrlDescriptor->url_rebuild ." HTTP/1.0\r\n";
        }
        else
        {
            $query = $this->prepareHTTPRequestQuery();
            $headerLines[] = $request_type." ".$query." HTTP/".$http_protocol_version."\r\n";
        }

        $headerLines[] = "Host: ".$_Request->url_parts["host"]."\r\n";

        $headerLines[] = "User-Agent: ".str_replace("\n", "", $_Request->userAgentString)."\r\n";
        $headerLines[] = "Accept: */*\r\n";

        // Request GZIP-content
        if ($_Request->request_gzip_content == true)
        {
            $headerLines[] = "Accept-Encoding: gzip, deflate\r\n";
        }

        // Referer
        if ($_Request->UrlDescriptor->refering_url != null)
        {
            $headerLines[] = "Referer: ".$_Request->UrlDescriptor->refering_url."\r\n";
        }

        // Cookies
        $cookie_header = $this->buildCookieHeader();
        if ($cookie_header != null)
            $headerLines[] = $this->buildCookieHeader();

        // Authentication
        if ($_Request->url_parts["auth_username"] != "" && $_Request->url_parts["auth_password"] != "")
        {
            $auth_string = base64_encode($_Request->url_parts["auth_username"].":".$_Request->url_parts["auth_password"]);
            $headerLines[] = "Authorization: Basic ".$auth_string."\r\n";
        }

        // Proxy authentication
        if ($_Request->proxy != null && $_Request->proxy["proxy_username"] != null)
        {
            $auth_string = base64_encode($_Request->proxy["proxy_username"].":".$_Request->proxy["proxy_password"]);
            $headerLines[] = "Proxy-Authorization: Basic ".$auth_string."\r\n";
        }

        $headerLines[] = "Connection: close\r\n";

        // Wenn POST-Request
        if ($request_type == RequestHeader::METHOD_POST)
        {
            // Post-Content bauen
            $post_content = $this->buildPostContent();

            $headerLines[] = "Content-Type: multipart/form-data; boundary=---------------------------10786153015124\r\n";
            $headerLines[] = "Content-Length: ".strlen($post_content)."\r\n\r\n";
            $headerLines[] = $post_content;
        }
        else
        {
            $headerLines[] = "\r\n";
        }

        return $headerLines;
    }


    /**
     *
     * @return mixed|string
     */
    public function prepareHTTPRequestQuery()
    {
        $_Request = $this->_Request;
        $query = $_Request->url_parts["path"].$_Request->url_parts["file"].$_Request->url_parts["query"];
        // If string already is a valid URL -> do nothing
        if (Utils::isValidUrlString($query))
        {
            return $query;
        }

        // Decode query-string (for URLs that are partly urlencoded and partly not)
        $query = rawurldecode($query);

        // if query is already utf-8 encoded -> simply urlencode it,
        // otherwise encode it to utf8 first.
        if (Encoding::isUTF8String($query) == true)
        {
            $query = rawurlencode($query);
        }
        else
        {
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
    public function buildCookieHeader()
    {
        $cookie_string = "";

        @reset($this->_Request->cookie_array);
        while(list($key, $value) = @each($this->_Request->cookie_array))
        {
            $cookie_string .= "; ".$key."=".$value."";
        }

        if ($cookie_string != "")
        {
            return "Cookie: ".substr($cookie_string, 2)."\r\n";
        }
        else
        {
            return null;
        }
    }

    /**
     * Builds the post-content from the postdata-array for the header to send with the request (MIME-style)
     *
     * @return array  Numeric array containing the lines of the POST-part for the header
     */
    public function buildPostContent()
    {
        $_Request = $this->_Request;

        $post_content = "";

        // Post-Data
        @reset($_Request->post_data);
        while (list($key, $value) = @each($_Request->post_data))
        {
            $post_content .= "-----------------------------10786153015124\r\n";
            $post_content .= "Content-Disposition: form-data; name=\"".$key."\"\r\n\r\n";
            $post_content .= $value."\r\n";
        }

        $post_content .= "-----------------------------10786153015124\r\n";

        return $post_content;
    }

}
