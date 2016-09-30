<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-9-28
 * Time: 下午8:45
 */

namespace PhCrawler\Http;


use PhCrawler\Http\Descriptors\ProxyDescriptor;
use PhCrawler\Http\Utils\EncodingUtil;
use PhCrawler\Http\Utils\UrlUtil;

/**
 * Class RequestHeader
 *
 * @package PhCrawler\Http
 */
trait handleRequestHeader
{

    protected function initRequestHeader() {
        if (count($this->post_data) > 0) $this->method = Request::METHOD_POST;
        else $this->method = Request::METHOD_GET;
    }

    /**
     * @return array
     */
    public function buildRequestHeaderLines()
    {
        $this->initRequestHeader();

        // Create header
        $headerLines = [];
        $headerLines[] = $this->buildRequestFirstLine();
        $headerLines[] = $this->buildRequestHostLine();
        $headerLines[] = $this->buildRequestUserAgentLine();
        $headerLines[] = $this->buildRequestAcceptLine();
        $headerLines[] = $this->buildRequestAcceptEncodingLine();
        $headerLines[] = $this->buildRequestReferingUrlLine();
        $headerLines[] = $this->buildRequestCookieLine();
        $headerLines[] = $this->buildRequestAuthenticationLine();
        $headerLines[] = $this->buildRequestProxyAuthorizationLine();
        $headerLines[] = $this->buildRequestConnectionLine();
        $headerLines = array_merge($headerLines, $this->buildRequestPostContentLines());
        return array_values(array_filter($headerLines));
    }

    /**
     * @return string
     */
    protected function buildRequestFirstLine()
    {
        $_Proxy = $this->ProxyDescriptor;

        if ($_Proxy instanceof Proxy && $_Proxy->host != null) {
            // A Proxy needs the full qualified URL in the GET or POST headerline.
            $line = $this->method . " " . $this->UrlDescriptor->url_rebuild . " HTTP/1.0";
        } else {
            $query = $this->buildRequestQuery();
            $line = $this->method . " " . $query . " HTTP/" . $this->http_protocol_version;
        }

        return $line . self::HEADER_SEPARATOR;
    }

    /**
     * @return string
     */
    protected function buildRequestHostLine()
    {
        return "Host: " . $this->UrlPartsDescriptor->host . self::HEADER_SEPARATOR;;
    }

    /**
     * @return string
     */
    protected function buildRequestUserAgentLine()
    {
        return "User-Agent: " . str_replace("\n", "", $this->userAgent) . self::HEADER_SEPARATOR;
    }

    /**
     * @return string
     */
    protected function buildRequestAcceptLine()
    {
        return "Accept: */*" . self::HEADER_SEPARATOR;
    }

    /**
     * @return string
     */
    protected function buildRequestAcceptEncodingLine()
    {
        // Request GZIP-content
        if ($this->request_gzip_content == true) {
            return "Accept-Encoding: gzip, deflate" . self::HEADER_SEPARATOR;
        }
        return '';
    }

    /**
     * @return string
     */
    protected function buildRequestReferingUrlLine()
    {
        // Referer
        $refering_url = $this->UrlDescriptor->refering_url;

        if ($refering_url != null) {
            return "Referer: " . $refering_url . self::HEADER_SEPARATOR;
        }
        return '';
    }


    /**
     * @return string
     */
    protected function buildRequestAuthenticationLine()
    {

        $UrlParts = $this->UrlPartsDescriptor;

        if ($UrlParts->auth_username != "" && $UrlParts->auth_password != "") {
            $auth_string = base64_encode($UrlParts->auth_username . ":" . $UrlParts->auth_password);
            return "Authorization: Basic " . $auth_string . self::HEADER_SEPARATOR;
        }
        return '';
    }

    /**
     * @return string
     */
    protected function buildRequestProxyAuthorizationLine()
    {
        // Proxy authentication
        $Proxy = $this->ProxyDescriptor;

        if ($Proxy instanceof ProxyDescriptor &&
            $Proxy->username != null) {

            $auth_string = base64_encode($Proxy->username . ":" . $Proxy->password);
            return "Proxy-Authorization: Basic " . $auth_string . self::HEADER_SEPARATOR;
        }
        return '';
    }

    /**
     * @return string
     */
    protected function buildRequestConnectionLine()
    {
        return "Connection: close" . self::HEADER_SEPARATOR;
    }

    /**
     * @return array
     */
    protected function buildRequestPostContentLines()
    {
        // Wenn POST-Request
        $headerLines = [];

        if ($this->method == self::METHOD_POST) {
            // Post-Content bauen
            $post_content = $this->buildRequestPostContent();

            $headerLines[] = "Content-Type: multipart/form-data; boundary=---------------------------10786153015124".self::HEADER_SEPARATOR;
            $headerLines[] = "Content-Length: " . strlen($post_content) . (self::HEADER_SEPARATOR.self::HEADER_SEPARATOR);
            $headerLines[] = $post_content;
        } else {
            $headerLines[] = self::HEADER_SEPARATOR;
        }

        return $headerLines;
    }

    /**
     *
     * @return mixed|string
     */
    protected function buildRequestQuery()
    {
        $UrlParts = $this->UrlPartsDescriptor;

        $query = $UrlParts->path . $UrlParts->file . $UrlParts->query;
        // If string already is a valid URL -> do nothing
        if (UrlUtil::isValidUrlString($query)) {
            return $query;
        }

        // Decode query-string (for URLs that are partly urlencoded and partly not)
        $query = rawurldecode($query);

        // if query is already utf-8 encoded -> simply urlencode it,
        // otherwise encode it to utf8 first.
        if (EncodingUtil::isUTF8String($query) == true) {
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
    protected function buildRequestCookieLine()
    {
        $cookie_string = "";

        @reset($this->cookie_data);
        while (list($key, $value) = @each($this->cookie_data)) {
            $cookie_string .= "; " . $key . "=" . $value . "";
        }

        if ($cookie_string != "") {
            return "Cookie: " . substr($cookie_string, 2) . self::HEADER_SEPARATOR;
        } else {
            return null;
        }
    }

    /**
     * Builds the post-content from the postdata-array for the header to send with the request (MIME-style)
     *
     * @return array  Numeric array containing the lines of the POST-part for the header
     */
    protected function buildRequestPostContent()
    {

        $post_content = "";

        // Post-Data
        @reset($this->post_data);
        while (list($key, $value) = @each($this->post_data)) {
            $post_content .= "-----------------------------10786153015124";
            $post_content .= self::HEADER_SEPARATOR;
            $post_content .= "Content-Disposition: form-data; name=\"" . $key . "\"";
            $post_content .= self::HEADER_SEPARATOR . self::HEADER_SEPARATOR;
            $post_content .= $value;
            $post_content .= self::HEADER_SEPARATOR;
        }

        $post_content .= "-----------------------------10786153015124";
        $post_content .= self::HEADER_SEPARATOR;

        return $post_content;
    }

    public function buildRequestHeaderRaw() {
       return join('', $this->buildRequestHeaderLines());
    }
}
