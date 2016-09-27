<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-9-24
 * Time: 下午11:14
 */

namespace PhCrawler;

use Exception;
use PhCrawler\Enums\RequestErrors;
use PhCrawler\Utils\Utils;

/**
 * Class Request
 *
 * @package PhCrawler
 */
class Request
{
    /**
     * The user-agent-string
     */
    public $userAgentString = "PhCrawler";

    /**
     * The HTTP protocol version to use.
     */
    public $http_protocol_version = 2;

    /**
     * Timeout-value for socket-connection
     */
    public $socketConnectTimeout = 10;

    /**
     * Socket-read-timeout
     */
    public $socketReadTimeout = 5;

    /**
     * Limit for content-size to receive
     *
     * @var int The kimit n bytes
     */
    public $content_size_limit = 0;

    /**
     * Global counter for traffic this instance of the Request-class caused.
     *
     * @var int Traffic in bytes
     */
    public $global_traffic_count = 0;

    /**
     * Numer of bytes received from the header
     *
     * @var float Number of bytes
     */
    public $header_bytes_received = null;

    /**
     * Number of bytes received from the content
     *
     * @var float Number of bytes
     */
    public $content_bytes_received = null;

    /**
     * The time it took to tranfer the data of this document
     *
     * @var float Time in seconds and milliseconds
     */
    public $data_transfer_time = null;

    /**
     * The time it took to connect to the server
     *
     * @var float Time in seconds and milliseconds or NULL if connection could not be established
     */
    public $server_connect_time = null;

    /**
     * The server resonse time
     *
     * @var float time in seconds and milliseconds or NULL if the server didn't respond
     */
    public $server_response_time = null;

    /**
     * Contains all rules defining the content-types that should be received
     *
     * @var array Numeric array conatining the regex-rules
     */
    public $receive_content_types = array();

    /**
     * Contains all rules defining the content-types of pages/files that should be streamed directly to
     * a temporary file (instead of to memory)
     *
     * @var array Numeric array conatining the regex-rules
     */
    public $receive_to_file_content_types = array();

    /**
     * Contains all rules defining the content-types defining which documents shoud get checked for links.
     *
     * @var array Numeric array conatining the regex-rules
     */
    public $link_search_content_types = array("#text/html# i");

    /**
     * The TMP-File to use when a page/file should be streamed to file.
     *
     * @var string
     */
    public $tmpFile = "phCrawler.tmp";

    /**
     * The URL for the request as URLDescriptor-object
     *
     * @var URLDescriptor
     */
    public $UrlDescriptor;

    /**
     * The parts of the URL for the request as returned by Utils::splitURL()
     *
     * @var array
     */
    public $url_parts = array();

    /**
     * DNS-cache
     *
     * @var DNSCache
     */
    public $DNSCache;

    /**
     * Link-finder object
     *
     * @var LinkFinder
     */
    public $LinkFinder;

    /**
     * The last response-header this request-instance received.
     */
    public $lastResponseHeader;

    /**
     * Array containing cookies to send with the request
     *
     * @array
     */
    public $cookie_data = array();

    /**
     * Array containing POST-data to send with the request
     *
     * @var array
     */
    public $post_data = array();

    /**
     * The proxy to use
     *
     * @var array
     * Array containing the keys "proxy_host", "proxy_port", "proxy_username", "proxy_password".
     */
    public $proxy;

    /**
     * The socket used for HTTP-requests
     */
    protected $socket;

    /**
     * The bytes contained in the socket-buffer directly after the server responded
     */
    public $socket_pre_fill_size;

    /**
     * Enalbe/disable request for gzip encoded content.
     */
    public $request_gzip_content = false;

    /**
     * @var null | \Closure
     */
    public $header_check_callback_function = null;

    /**
     * @var int
     */
    public $content_buffer_size = 200000;
    /**
     * @var int
     */
    public $chunk_buffer_size = 20240;
    /**
     * @var int
     */
    public $socket_read_buffer_size = 1024;
    /**
     * @var int
     */
    public $source_overlap_size = 1500;

    /**
     * Request constructor.
     */
    public function __construct()
    {
       $this->LinkFinder = new LinkFinder();
       $this->DNSCache = new DNSCache();
    }

    /**
     * Sets the URL for the request.
     *
     * @param URLDescriptor $UrlDescriptor An URLDescriptor-object containing the URL to request
     */
    public function setUrl(URLDescriptor $UrlDescriptor)
    {
        $this->UrlDescriptor = $UrlDescriptor;

        // Split the URL into its parts
        $this->url_parts = Utils::splitURL($UrlDescriptor->url_rebuild);
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
     * Sets the html-tags from which to extract/find links from.
     *
     * @param array $tag_array Numeric array containing the tags, i.g. array("href", "src", "url", ...)
     * @return bool
     */
    public function setLinkExtractionTags($tag_array)
    {
        if (!is_array($tag_array)) return false;

        $this->LinkFinder->extract_tags = $tag_array;
        return true;
    }


    /**
     * @param $mode
     *
     * @return bool
     */
    public function setFindRedirectURLs($mode)
    {
        if (!is_bool($mode)) return false;

        $this->LinkFinder->find_redirect_urls = $mode;

        return true;
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

    /**
     * @param      $proxy_host
     * @param      $proxy_port
     * @param null $proxy_username
     * @param null $proxy_password
     */
    public function setProxy($proxy_host, $proxy_port, $proxy_username = null, $proxy_password = null)
    {
        $this->proxy = array();
        $this->proxy["proxy_host"] = $proxy_host;
        $this->proxy["proxy_port"] = $proxy_port;
        $this->proxy["proxy_username"] = $proxy_username;
        $this->proxy["proxy_password"] = $proxy_password;
    }


    /**
     * @param $username
     * @param $password
     */
    public function setBasicAuthentication($username, $password)
    {
        $this->url_parts["auth_username"] = $username;
        $this->url_parts["auth_password"] = $password;
    }

    /**
     * Enables/disables aggresive linksearch
     *
     * @param bool $mode
     * @return bool
     */
    public function enableAggressiveLinkSearch($mode)
    {
        if (!is_bool($mode)) return false;

        $this->LinkFinder->aggressive_search = $mode;
        return true;
    }

    /**
     * @param $obj
     * @param $method_name
     */
    public function setHeaderCheckCallbackFunction(&$obj, $method_name)
    {
        $this->header_check_callback_function = array($obj, $method_name);
    }


    /**
     * @return \PhCrawler\DocumentInfo
     * @throws \Exception
     */
    public function fetch()
    {
        // Prepare LinkFinder
        $this->LinkFinder->resetLinkCache();
        $this->LinkFinder->setSourceUrl($this->UrlDescriptor);

        // Initiate the Response-object and pass base-infos
        $DocInfo = new DocumentInfo();
        $DocInfo->url = $this->UrlDescriptor->url_rebuild;
        $DocInfo->protocol = $this->url_parts["protocol"];
        $DocInfo->host = $this->url_parts["host"];
        $DocInfo->path = $this->url_parts["path"];
        $DocInfo->file = $this->url_parts["file"];
        $DocInfo->query = $this->url_parts["query"];
        $DocInfo->port = $this->url_parts["port"];
        $DocInfo->url_link_depth = $this->UrlDescriptor->url_link_depth;

        // Create header to send
        $request_header_lines = $this->RequestHeader()->buildLines();
        $header_string = trim(implode("", $request_header_lines));
        $DocInfo->header_send = $header_string;

        // Open socket
        $Socket = new Socket($this);
        $Socket->open($DocInfo->error_code, $DocInfo->error_string);

        $DocInfo->server_connect_time = $this->server_connect_time;

        // If error occured
        if ($DocInfo->error_code != null)
        {
            // If proxy-error -> throw exception
            if ($DocInfo->error_code == RequestErrors::ERROR_PROXY_UNREACHABLE)
            {
                throw new Exception("Unable to connect to proxy '".$this->proxy["proxy_host"]."' on port '".$this->proxy["proxy_port"]."'");
            }

            $DocInfo->error_occured = true;
            return $DocInfo;
        }

        $Socket->sendRequestHeader($request_header_lines);

        $response_header = $Socket->readResponseHeader($DocInfo->error_code, $DocInfo->error_string);

        $DocInfo->server_response_time = $this->server_response_time;

        // If error occured
        if ($DocInfo->error_code != null)
        {
            $DocInfo->error_occured = true;
            return $DocInfo;
        }

        // Set header-infos
        $this->lastResponseHeader = new ResponseHeader($response_header, $this->UrlDescriptor->url_rebuild);
        $DocInfo->responseHeader = $this->lastResponseHeader;
        $DocInfo->header = $this->lastResponseHeader->header_raw;
        $DocInfo->http_status_code = $this->lastResponseHeader->http_status_code;
        $DocInfo->content_type = $this->lastResponseHeader->content_type;
        $DocInfo->cookies = $this->lastResponseHeader->cookies;

        // Referer-Infos
        if ($this->UrlDescriptor->refering_url != null)
        {
            $DocInfo->referer_url = $this->UrlDescriptor->refering_url;
            $DocInfo->refering_linkcode = $this->UrlDescriptor->linkcode;
            $DocInfo->refering_link_raw = $this->UrlDescriptor->link_raw;
            $DocInfo->refering_linktext = $this->UrlDescriptor->linktext;
        }

        // Check if content should be received
        $receive = $this->decideReceiveContent($this->lastResponseHeader);

        if ($receive == false)
        {
//            @fclose($this->socket);
            $Socket->close();

            $DocInfo->received = false;
            $DocInfo->links_found_url_descriptors = $this->LinkFinder->getAllURLs(); // Maybe found a link/redirect in the header
            $DocInfo->meta_attributes = $this->LinkFinder->getAllMetaAttributes();
            return $DocInfo;
        }
        else
        {
            $DocInfo->received = true;
        }

        // Check if content should be streamd to file
        $stream_to_file = $this->decideStreamToFile($response_header);

        // Read content
//        $response_content = $this->readResponseContent($stream_to_file, $DocInfo->error_code, $DocInfo->error_string, $DocInfo->received_completely);

        $response_content = $Socket->readResponseContent($stream_to_file, $DocInfo->error_code, $DocInfo->error_string, $DocInfo->received_completely);

        // If error occured
        if ($DocInfo->error_code != null)
        {
            $DocInfo->error_occured = true;
        }

//        @fclose($this->socket);

        $Socket->close();

        // Complete ResponseObject
        $DocInfo->content = $response_content;
        $DocInfo->source = &$DocInfo->content;
        $DocInfo->received_completly = $DocInfo->received_completely;

        if ($stream_to_file == true)
        {
            $DocInfo->received_to_file = true;
            $DocInfo->content_tmp_file = $this->tmpFile;
        }
        else $DocInfo->received_to_memory = true;

        $DocInfo->links_found_url_descriptors = $this->LinkFinder->getAllURLs();
        $DocInfo->meta_attributes = $this->LinkFinder->getAllMetaAttributes();

        // Info about received bytes
        $DocInfo->bytes_received = $this->content_bytes_received;
        $DocInfo->header_bytes_received = $this->header_bytes_received;

        $dtr_values = $this->calculateDataTransferRateValues();
        if ($dtr_values != null)
        {
            $DocInfo->data_transfer_rate = $dtr_values["data_transfer_rate"];
            $DocInfo->unbuffered_bytes_read = $dtr_values["unbuffered_bytes_read"];
            $DocInfo->data_transfer_time = $dtr_values["data_transfer_time"];
        }

        $DocInfo->setLinksFoundArray();

        $this->LinkFinder->resetLinkCache();

        return $DocInfo;
    }


    /**
     * @return array|null
     */
    protected function calculateDataTransferRateValues()
    {
        $dataValues = array();

        // Works like this:
        // After the server resonded, the socket-buffer is already filled with bytes,
        // that means they were received within the server-response-time.

        // To calulate the real data transfer rate, these bytes have to be substractred from the received
        // bytes beofre calulating the rate.
        if ($this->data_transfer_time > 0 && $this->content_bytes_received > 4 * $this->socket_pre_fill_size)
        {
            $dataValues["unbuffered_bytes_read"] = $this->content_bytes_received + $this->header_bytes_received - $this->socket_pre_fill_size;
            $dataValues["data_transfer_rate"] = $dataValues["unbuffered_bytes_read"] / $this->data_transfer_time;
            $dataValues["data_transfer_time"] = $this->data_transfer_time;
        }
        else
        {
            $dataValues = null;
        }

        return $dataValues;
    }

    /**
     * @return RequestHeader
     */
    protected function RequestHeader()
    {
        return (new RequestHeader($this));
    }


    /**
     * Checks whether the content of this page/file should be received (based on the content-type, http-status-code,
     * user-callback and the applied rules)
     *
     * @param ResponseHeader $responseHeader The response-header as an ResponseHeader-object
     * @return bool TRUE if the content should be received
     */
    protected function decideReceiveContent(ResponseHeader $responseHeader)
    {
        // Get Content-Type from header
        $content_type = $responseHeader->content_type;

        // Call user header-check-callback-method
        if ($this->header_check_callback_function != null)
        {
            $ret = call_user_func($this->header_check_callback_function, $responseHeader);
            if ($ret < 0) return false;
        }

        // No Content-Type given
        if ($content_type == null)
            return false;

        // Status-code not 2xx
        if ($responseHeader->http_status_code == null || $responseHeader->http_status_code > 299 || $responseHeader->http_status_code < 200)
            return false;

        // Check against the given content-type-rules
        $receive = Utils::checkStringAgainstRegexArray($content_type, $this->receive_content_types);

        return $receive;
    }

    /**
     * Checks whether the content of this page/file should be streamed directly to file.
     *
     * @param string $response_header The response-header
     * @return bool TRUE if the content should be streamed to TMP-file
     */
    protected function decideStreamToFile($response_header)
    {
        if (count($this->receive_to_file_content_types) == 0) return false;

        // Get Content-Type from header
        $content_type = Utils::getHeaderValue($response_header, "content-type");

        // No Content-Type given
        if ($content_type == null) return false;

        // Check against the given rules
        $receive = Utils::checkStringAgainstRegexArray($content_type, $this->receive_to_file_content_types);

        return $receive;
    }

    /**
     * Adds a rule to the list of rules that decides which pages or files - regarding their content-type - should be received
     *
     * If the content-type of a requested document doesn't match with the given rules, the request will be aborted after the header
     * was received.
     *
     * @param string $regex The rule as a regular-expression
     * @return bool TRUE if the rule was added to the list.
     *              FALSE if the given regex is not valid.
     */
    public function addReceiveContentType($regex)
    {
        $check = Utils::checkRegexPattern($regex); // Check pattern

        if ($check == true)
        {
            $this->receive_content_types[] = trim(strtolower($regex));
        }
        return $check;
    }

    /**
     * Adds a rule to the list of rules that decides what types of content should be streamed diretly to the temporary file.
     *
     * If a content-type of a page or file matches with one of these rules, the content will be streamed directly into the temporary file
     * given in setTmpFile() without claiming local RAM.
     *
     * @param string $regex The rule as a regular-expression
     * @return bool         TRUE if the rule was added to the list and the regex is valid.
     */
    public function addStreamToFileContentType($regex)
    {
        $check = Utils::checkRegexPattern($regex); // Check pattern

        if ($check == true)
        {
            $this->receive_to_file_content_types[] = trim($regex);
        }
        return $check;
    }


    /**
     * @param $tmp_file
     *
     * @return bool
     */
    public function setTmpFile($tmp_file)
    {
        //Check if writable
        $fp = @fopen($tmp_file, "w");

        if (!$fp)
        {
            return false;
        }
        else
        {
            fclose($fp);
            $this->tmpFile = $tmp_file;
            return true;
        }
    }

    /**
     * Sets the size-limit in bytes for content the request should receive.
     *
     * @param int $bytes
     * @return bool
     */
    public function setContentSizeLimit($bytes)
    {
        if (preg_match("#^[0-9]*$#", $bytes))
        {
            $this->content_size_limit = $bytes;
            return true;
        }
        else return false;
    }

    /**
     * Returns the global traffic this instance of the Request-class caused so far.
     *
     * @return int The traffic in bytes.
     */
    public function getGlobalTrafficCount()
    {
        return $this->global_traffic_count;
    }

    /**
     * Adds a rule to the list of rules that decide what kind of documents should get
     * checked for links in (regarding their content-type)
     *
     * @param string $regex Regular-expression defining the rule
     * @return bool         TRUE if the rule was successfully added
     */
    public function addLinkSearchContentType($regex)
    {
        $check = Utils::checkRegexPattern($regex); // Check pattern
        if ($check == true)
        {
            $this->link_search_content_types[] = trim($regex);
        }
        return $check;
    }

    /**
     * @param $http_protocol_version
     *
     * @return bool
     */
    public function setHTTPProtocolVersion($http_protocol_version)
    {
        if (preg_match("#[1-2]#", $http_protocol_version))
        {
            $this->http_protocol_version = $http_protocol_version;
            return true;
        }
        else return false;
    }

    /**
     * @param $mode
     */
    public function requestGzipContent($mode)
    {
        if (is_bool($mode))
        {
            $this->request_gzip_content = $mode;
        }
    }


    /**
     * @param $document_sections
     *
     * @return mixed
     */
    public function excludeLinkSearchDocumentSections($document_sections)
    {
        return $this->LinkFinder->excludeLinkSearchDocumentSections($document_sections);
    }


    /**
     * @param null $content_buffer_size
     * @param null $chunk_buffer_size
     * @param null $socket_read_buffer_size
     * @param null $source_overlap_size
     *
     * @throws \Exception
     */
    public function setBufferSizes($content_buffer_size = null, $chunk_buffer_size = null, $socket_read_buffer_size = null, $source_overlap_size = null)
    {
        if ($content_buffer_size !== null)
            $this->content_buffer_size = $content_buffer_size;

        if ($chunk_buffer_size !== null)
            $this->chunk_buffer_size = $chunk_buffer_size;

        if ($socket_read_buffer_size !== null)
            $this->socket_read_buffer_size = $socket_read_buffer_size;

        if ($source_overlap_size !== null)
            $this->source_overlap_size = $source_overlap_size;

        if ($this->content_buffer_size < $this->chunk_buffer_size || $this->chunk_buffer_size < $this->socket_read_buffer_size)
        {
            throw new Exception("Implausible buffer-size-settings assigned to ".get_class($this).".");
        }
    }
}

