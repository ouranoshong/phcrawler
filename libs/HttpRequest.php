<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-9-24
 * Time: 下午11:14
 */

namespace PhCrawler;

use Exception;
use PhCrawler\Enums\HttpProtocols;
use PhCrawler\Enums\RequestErrors;
use PhCrawler\Utils\Encoding;
use PhCrawler\Utils\Utils;

/**
 * Class HttpRequest
 *
 * @package PhCrawler
 */
class HttpRequest
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
    protected $content_size_limit = 0;

    /**
     * Global counter for traffic this instance of the HTTPRequest-class caused.
     *
     * @var int Traffic in bytes
     */
    protected $global_traffic_count = 0;

    /**
     * Numer of bytes received from the header
     *
     * @var float Number of bytes
     */
    protected $header_bytes_received = null;

    /**
     * Number of bytes received from the content
     *
     * @var float Number of bytes
     */
    protected $content_bytes_received = null;

    /**
     * The time it took to tranfer the data of this document
     *
     * @var float Time in seconds and milliseconds
     */
    protected $data_transfer_time = null;

    /**
     * The time it took to connect to the server
     *
     * @var float Time in seconds and milliseconds or NULL if connection could not be established
     */
    protected $server_connect_time = null;

    /**
     * The server resonse time
     *
     * @var float time in seconds and milliseconds or NULL if the server didn't respond
     */
    protected $server_response_time = null;

    /**
     * Contains all rules defining the content-types that should be received
     *
     * @var array Numeric array conatining the regex-rules
     */
    protected $receive_content_types = array();

    /**
     * Contains all rules defining the content-types of pages/files that should be streamed directly to
     * a temporary file (instead of to memory)
     *
     * @var array Numeric array conatining the regex-rules
     */
    protected $receive_to_file_content_types = array();

    /**
     * Contains all rules defining the content-types defining which documents shoud get checked for links.
     *
     * @var array Numeric array conatining the regex-rules
     */
    protected $link_search_content_types = array("#text/html# i");

    /**
     * The TMP-File to use when a page/file should be streamed to file.
     *
     * @var string
     */
    protected $tmpFile = "phCrawler.tmp";

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
    protected $LinkFinder;

    /**
     * The last response-header this request-instance received.
     */
    protected $lastResponseHeader;

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
     * @var array Array containing the keys "proxy_host", "proxy_port", "proxy_username", "proxy_password".
     */
    public $proxy;

    /**
     * The socket used for HTTP-requests
     */
    protected $socket;

    /**
     * The bytes contained in the socket-buffer directly after the server responded
     */
    protected $socket_pre_fill_size;

    /**
     * Enalbe/disable request for gzip encoded content.
     */
    public $request_gzip_content = false;

    /**
     * @var null | \Closure
     */
    protected $header_check_callback_function = null;

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
     * HttpRequest constructor.
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
    public function sendRequest()
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
        $request_header_lines = $this->buildRequestHeader();
        $header_string = trim(implode("", $request_header_lines));
        $DocInfo->header_send = $header_string;

        // Open socket
        $this->openSocket($DocInfo->error_code, $DocInfo->error_string);
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

        // Send request
        $this->sendRequestHeader($request_header_lines);

        // Read response-header
        $response_header = $this->readResponseHeader($DocInfo->error_code, $DocInfo->error_string);
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
            @fclose($this->socket);
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
        $response_content = $this->readResponseContent($stream_to_file, $DocInfo->error_code, $DocInfo->error_string, $DocInfo->received_completely);

        // If error occured
        if ($DocInfo->error_code != null)
        {
            $DocInfo->error_occured = true;
        }

        @fclose($this->socket);

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
     * Opens the socket to the host.
     *
     * @param  int    &$error_code          Error-code by referenct if an error occured.
     * @param  string &$error_string        Error-string by reference
     *
     * @return bool   TRUE if socket could be opened, otherwise FALSE.
     */
    protected function openSocket(&$error_code, &$error_string)
    {
        Benchmark::reset("connecting_server");
        Benchmark::start("connecting_server");

        // SSL or not?
        if ($this->url_parts["protocol"] == "https://") $protocol_prefix = "ssl://";
        else $protocol_prefix = "";

        // If SSL-request, but openssl is not installed
        if ($protocol_prefix == "ssl://" && !extension_loaded("openssl"))
        {
            $error_code = RequestErrors::ERROR_SSL_NOT_SUPPORTED;
            $error_string = "Error connecting to ".$this->url_parts["protocol"].$this->url_parts["host"].": SSL/HTTPS-requests not supported, extension openssl not installed.";
        }

        // Get IP for hostname
        $ip_address = $this->DNSCache->getIP($this->url_parts["host"]);

        // Open socket
        if ($this->proxy != null)
        {
            $this->socket = @stream_socket_client($this->proxy["proxy_host"].":".$this->proxy["proxy_port"], $error_code, $error_str,
                $this->socketConnectTimeout, STREAM_CLIENT_CONNECT);
        }
        else
        {
            // If ssl -> perform Server name indication
            if ($this->url_parts["protocol"] == "https://")
            {
                $context = stream_context_create(array('ssl' => array('SNI_server_name' => $this->url_parts["host"])));
                $this->socket = @stream_socket_client($protocol_prefix.$ip_address.":".$this->url_parts["port"], $error_code, $error_str,
                    $this->socketConnectTimeout, STREAM_CLIENT_CONNECT, $context);
            }
            else
            {
                $this->socket = @stream_socket_client($protocol_prefix.$ip_address.":".$this->url_parts["port"], $error_code, $error_str,
                    $this->socketConnectTimeout, STREAM_CLIENT_CONNECT); // NO $context here, memory-leak-bug in php v. 5.3.x!!
            }
        }

        $this->server_connect_time = Benchmark::stop("connecting_server");

        // If socket not opened -> throw error
        if ($this->socket == false)
        {
            $this->server_connect_time = null;

            // If proxy not reachable
            if ($this->proxy != null)
            {
                $error_code = RequestErrors::ERROR_PROXY_UNREACHABLE;
                $error_string = "Error connecting to proxy ".$this->proxy["proxy_host"].": Host unreachable (".$error_str.").";
                return false;
            }
            else
            {
                $error_code = RequestErrors::ERROR_HOST_UNREACHABLE;
                $error_string = "Error connecting to ".$this->url_parts["protocol"].$this->url_parts["host"].": Host unreachable (".$error_str.").";
                return false;
            }
        }
        else
        {
            return true;
        }
    }


    /**
     * @param $request_header_lines
     */
    protected function sendRequestHeader($request_header_lines)
    {
        // Header senden
        $cnt = count($request_header_lines);
        for ($x=0; $x<$cnt; $x++)
        {
            fputs($this->socket, $request_header_lines[$x]);
        }
    }

    /**
     * Reads the response-header.
     *
     * @param  int    &$error_code           Error-code by reference if an error occured.
     * @param  string &$error_string         Error-string by reference
     *
     * @return string The response-header or NULL if an error occured
     */
    protected function readResponseHeader(&$error_code, &$error_string)
    {
        Benchmark::reset("server_response_time");
        Benchmark::start("server_response_time");

        $status = socket_get_status($this->socket);
        $source_read = "";
        $header = "";
        $server_responded = false;

        while ($status["eof"] == false)
        {
            socket_set_timeout($this->socket, $this->socketReadTimeout);

            // Read line from socket
            $line_read = fgets($this->socket, 1024);

            // Server responded
            if ($server_responded == false)
            {
                $server_responded = true;
                $this->server_response_time = Benchmark::stop("server_response_time");

                // Determinate socket prefill size
                $status = socket_get_status($this->socket);
                $this->socket_pre_fill_size = $status["unread_bytes"];

                // Start data-transfer-time bechmark
                Benchmark::reset("data_transfer_time");
                Benchmark::start("data_transfer_time");
            }

            $source_read .= $line_read;

            $this->global_traffic_count += strlen($line_read);

            $status = socket_get_status($this->socket);

            // Socket timed out
            if ($status["timed_out"] == true)
            {
                $error_code = RequestErrors::ERROR_SOCKET_TIMEOUT;
                $error_string = "Socket-stream timed out (timeout set to ".$this->socketReadTimeout." sec).";
                return $header;
            }

            // No "HTTP" at beginnig of response
            if (strtolower(substr($source_read, 0, 4)) != "http")
            {
                $error_code = RequestErrors::ERROR_NO_HTTP_HEADER;
                $error_string = "HTTP-protocol error.";
                return $header;
            }

            // Header found and read (2 newlines) -> stop
            if (substr($source_read, -4, 4) == "\r\n\r\n" || substr($source_read, -2, 2) == "\n\n")
            {
                $header = substr($source_read, 0, strlen($source_read)-2);
                break;
            }
        }

        // Stop data-transfer-time bechmark
        Benchmark::stop("data_transfer_time");

        // Header was found
        if ($header != "")
        {
            // Search for links (redirects) in the header
            $this->LinkFinder->processHTTPHeader($header);
            $this->header_bytes_received = strlen($header);
            return $header;
        }

        // No header found
        if ($header == "")
        {
            $this->server_response_time = null;
            $error_code = RequestErrors::ERROR_NO_HTTP_HEADER;
            $error_string = "Host doesn't respond with a HTTP-header.";
            return null;
        }
        
        return null;
    }

    /**
     * Reads the response-content.
     *
     * @param bool    $stream_to_file If TRUE, the content will be streamed diretly to the temporary file and
     *                                this method will not return the content as a string.
     * @param int     &$error_code    Error-code by reference if an error occured.
     * @param &string &$error_string  Error-string by reference
     * @param &string &$document_received_completely Flag indicatign whether the content was received completely passed by reference
     *
     * @return string  The response-content/source. May be emtpy if an error ocdured or data was streamed to the tmp-file.
     */
    protected function readResponseContent($stream_to_file = false, &$error_code, &$error_string, &$document_received_completely)
    {
        $fp = null;
        
        $this->content_bytes_received = 0;

        // If content should be streamed to file
        if ($stream_to_file == true)
        {
            $fp = @fopen($this->tmpFile, "w");

            if ($fp == false)
            {
                $error_code = RequestErrors::ERROR_TMP_FILE_NOT_WRITEABLE;
                $error_string = "Couldn't open the temporary file ".$this->tmpFile." for writing.";
                return "";
            }
        }

        // Init
        $source_portion = "";
        $source_complete = "";
        $document_received_completely = true;
        $document_completed = false;
        $gzip_encoded_content = null;

        // Resume data-transfer-time benchmark
        Benchmark::start("data_transfer_time");

        while ($document_completed == false)
        {
            // Get chunk from content
            $content_chunk = $this->readResponseContentChunk($document_completed, $error_code, $error_string, $document_received_completely);
            $source_portion .= $content_chunk;

            // Check if content is gzip-encoded (check only first chunk)
            if ($gzip_encoded_content === null)
            {
                if (Encoding::isGzipEncoded($content_chunk))
                    $gzip_encoded_content = true;
                else
                    $gzip_encoded_content = false;
            }

            // Stream to file or store source in memory
            if ($stream_to_file == true)
            {
                @fwrite($fp, $content_chunk);
            }
            else
            {
                $source_complete .= $content_chunk;
            }

            // Decode gzip-encoded content when done with document
            if ($document_completed == true && $gzip_encoded_content == true)
                $source_complete = $source_portion = Encoding::decodeGZipContent($source_complete);

            // Find links in portion of the source
            if (($gzip_encoded_content == false && $stream_to_file == false && strlen($source_portion) >= $this->content_buffer_size) || $document_completed == true)
            {
                if (Utils::checkStringAgainstRegexArray($this->lastResponseHeader->content_type, $this->link_search_content_types))
                {
                    Benchmark::stop("data_transfer_time");
                    $this->LinkFinder->findLinksInHTMLChunk($source_portion);

                    if ($this->source_overlap_size > 0)
                        $source_portion = substr($source_portion, -$this->source_overlap_size);
                    else
                        $source_portion = "";

                    Benchmark::start("data_transfer_time");
                }
            }
        }

        if ($stream_to_file == true) @fclose($fp);

        // Stop data-transfer-time benchmark
        Benchmark::stop("data_transfer_time");
        $this->data_transfer_time = Benchmark::getElapsedTime("data_transfer_time");

        return $source_complete;
    }


    /**
     * @param $document_completed
     * @param $error_code
     * @param $error_string
     * @param $document_received_completely
     *
     * @return string
     */
    protected function readResponseContentChunk(&$document_completed, &$error_code, &$error_string, &$document_received_completely)
    {
        $source_chunk = "";
        $stop_receiving = false;
        $bytes_received = 0;
        $document_completed = false;

        // If chunked encoding and protocol to use is HTTP 1.1
        if ($this->http_protocol_version == HttpProtocols::HTTP_1_1 && $this->lastResponseHeader->transfer_encoding == "chunked")
        {
            // Read size of next chunk
            $chunk_line = @fgets($this->socket, 128);
            if (trim($chunk_line) == "") $chunk_line = @fgets($this->socket, 128);
            $current_chunk_size = hexdec(trim($chunk_line));
        }
        else
        {
            $current_chunk_size = $this->chunk_buffer_size;
        }

        if ($current_chunk_size === 0)
        {
            $stop_receiving = true;
            $document_completed = true;
        }

        while ($stop_receiving == false)
        {
            socket_set_timeout($this->socket, $this->socketReadTimeout);

            // Set byte-buffer to bytes in socket-buffer (Fix for SSL-hang-bug #56, thanks to MadEgg!)
            $status = socket_get_status($this->socket);
            if ($status["unread_bytes"] > 0)
                $read_byte_buffer = $status["unread_bytes"];
            else
                $read_byte_buffer = $this->socket_read_buffer_size;

            // If chunk will be complete next read -> resize read-buffer to size of remaining chunk
            if ($bytes_received + $read_byte_buffer >= $current_chunk_size && $current_chunk_size > 0)
            {
                $read_byte_buffer = $current_chunk_size - $bytes_received;
                $stop_receiving = true;
            }

            // Read line from socket
            $line_read = @fread($this->socket, $read_byte_buffer);

            $source_chunk .= $line_read;
            $line_length = strlen($line_read);
            $this->content_bytes_received += $line_length;
            $this->global_traffic_count += $line_length;
            $bytes_received += $line_length;

            // Check socket-status
            $status = socket_get_status($this->socket);

            // Check for EOF
            if ($status["unread_bytes"] == 0 && ($status["eof"] == true || feof($this->socket) == true))
            {
                $stop_receiving = true;
                $document_completed = true;
            }

            // Socket timed out
            if ($status["timed_out"] == true)
            {
                $stop_receiving = true;
                $document_completed = true;
                $error_code = RequestErrors::ERROR_SOCKET_TIMEOUT;
                $error_string = "Socket-stream timed out (timeout set to ".$this->socketReadTimeout." sec).";
                $document_received_completely = false;
                return $source_chunk;
            }

            // Check if content-length stated in the header is reached
            if ($this->lastResponseHeader->content_length == $this->content_bytes_received)
            {
                $stop_receiving = true;
                $document_completed = true;
            }

            // Check if contentsize-limit is reached
            if ($this->content_size_limit > 0 && $this->content_size_limit <= $this->content_bytes_received)
            {
                $document_received_completely = false;
                $stop_receiving = true;
                $document_completed = true;
            }

        }

        return $source_chunk;
    }


    /**
     * @return array
     */
    protected function buildRequestHeader()
    {
        return (new RequestHeader($this))->buildLines();
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
     * Returns the global traffic this instance of the HTTPRequest-class caused so far.
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

