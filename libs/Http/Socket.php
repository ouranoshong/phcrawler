<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 9/28/16
 * Time: 12:51 PM
 */

namespace PhCrawler\Http;


use PhCrawler\Http\Descriptors\Proxy;
use PhCrawler\Http\Descriptors\UrlParts;
use PhCrawler\Http\Enums\RequestErrors;
use PhCrawler\Http\Utils\DNS;

/**
 * Class Socket
 *
 * @package PhCrawler\Http
 */
class Socket
{

    const PROTOCOL_PREFIX_SSL = 'ssl://';

    /**
     * @var resource
     */
    protected $_socket;

    public $timeout;
    public $error_code;
    public $error_message;
    public $flag = STREAM_CLIENT_CONNECT;

    protected $protocol_prefix = '';

    /**
     * @var Proxy
     */
    public $ProxyDescriptor;
    /**
     * @var UrlParts
     */
    public $UrlParsDescriptor;



    const SOCKET_PROTOCOL_PREFIX_SSL = 'ssl://';

    public $header_response;
    public $response_body_complety;

    protected function isSSLConnection() {
        return $this->UrlParsDescriptor instanceof UrlParts && $this->UrlParsDescriptor->isSSL();
    }

    protected function isProxyConnection() {
        return $this->ProxyDescriptor instanceof Proxy && $this->ProxyDescriptor->host !== null;
    }

    protected function canOpen() {

        if ($this->isSSLConnection() && !extension_loaded("openssl"))
        {
            $UrlParts = $this->UrlParsDescriptor;
            $this->error_code = RequestErrors::ERROR_SSL_NOT_SUPPORTED;
            $this->error_message = "Error connecting to ".$UrlParts->protocol.$UrlParts->host.": SSL/HTTPS-requests not supported, extension openssl not installed.";
            return false;
        }

        return true;
    }

    public function open()
    {

        if (!$this->canOpen()) return false;

        $this->_socket = @stream_socket_client(
            $this->getClientRemoveURI(),
            $this->error_code,
            $this->error_message,
            $this->timeout,
            $this->flag,
            $this->getClientContext()
        );

        return $this->checkOpened();
    }

    protected function checkOpened() {
        if ($this->_socket == false)
        {
            // If proxy not reachable
            if ($this->isProxyConnection())
            {
                $this->error_code = RequestErrors::ERROR_PROXY_UNREACHABLE;
                $this->error_message = "Error connecting to proxy ".$this->ProxyDescriptor->host.": Host unreachable (".$this->error_message.").";
                return false;
            }
            else
            {
                $UrlParts = $this->UrlParsDescriptor;
                $this->error_code = RequestErrors::ERROR_HOST_UNREACHABLE;
                $this->error_message = "Error connecting to ".$UrlParts->protocol.$UrlParts->host.": Host unreachable (".$this->error_message.").";
                return false;
            }
        }
        return true;
    }

    protected function getClientRemoteURI() {

        $protocol_prefix = '';

        if ($this->isProxyConnection()) {
            $host = $this->ProxyDescriptor->host;
            $port = $this->ProxyDescriptor->port;
        } else {

            if ($this->isSSLConnection()) {
                $protocol_prefix = self::SOCKET_PROTOCOL_PREFIX_SSL;
            }

            $host = DNS::getIpByHostName($this->UrlParsDescriptor->host);
            $port = $this->UrlParsDescriptor->port;

        }

        return $protocol_prefix . $host . ':'.$port;
    }

    protected function getClientContext() {
        if ($this->isSSLConnection()) {
            return stream_context_create(array('ssl' => array('SNI_server_name' => $this->UrlParsDescriptor->host)));
        }
        return null;
    }

    public function close() {
        @fclose($this->_socket);
    }

    public function send($message = '')
    {
        return @fputs($this->_socket, $message);
    }

    public function read($buffer = 128) {
        return @fread($this->_socket, $buffer);
    }

    public function gets($buffer = 1024) {
        return @fgets($this->_socket, $buffer);
    }

    public function setTimeOut($timeout = null) {

        if ($timeout) {
            $this->timeout = $timeout;
        }

        return socket_set_timeout($this->_socket, $this->timeout);
    }

    public function getStatus() {
        return socket_get_status($this->_socket);
    }

    public function readResponseHeader()
    {

        //TODO

        $status = socket_get_status($this->_socket);
        $source_read = "";
        $header = "";
        $server_responded = false;

        while ($status["eof"] == false)
        {
            socket_set_timeout($this->_socket, $this->timeout);

            // Read line from socket
            $line_read = fgets($this->_socket, 1024);

            // Server responded
            if ($server_responded == false)
            {
                $server_responded = true;

                // Determinate socket prefill size
                $status = socket_get_status($this->_socket);
                $status["unread_bytes"];

            }

            $source_read .= $line_read;

            $status = socket_get_status($this->_socket);

            // Socket timed out
            if ($status["timed_out"] == true)
            {
                $this->error_code = RequestErrors::ERROR_SOCKET_TIMEOUT;
                $this->error_message = "Socket-stream timed out (timeout set to ".$this->timeout." sec).";
                return $header;
            }

            // No "HTTP" at beginnig of response
            if (strtolower(substr($source_read, 0, 4)) != "http")
            {
                $this->error_code = RequestErrors::ERROR_NO_HTTP_HEADER;
                $this->error_message = "HTTP-protocol error.";
                return $header;
            }

            // Header found and read (2 newlines) -> stop
            if (substr($source_read, -4, 4) == "\r\n\r\n" || substr($source_read, -2, 2) == "\n\n")
            {
                $header = substr($source_read, 0, strlen($source_read)-2);
                break;
            }
        }

        // Header was found
        if ($header != "")
        {
            return $header;
        }

        // No header found
        if ($header == "")
        {
            $this->error_code = RequestErrors::ERROR_NO_HTTP_HEADER;
            $this->error_message = "Host doesn't respond with a HTTP-header.";
            return null;
        }

        return null;
    }

    public function readResponseContent()
    {
        //TODO

        $fp = null;
        $_Request = $this->_Request;

        $_Request->content_bytes_received = 0;


        // Init
        $source_portion = "";
        $source_complete = "";
        $document_received_completely = true;
        $document_completed = false;
        $gzip_encoded_content = null;


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

            $source_complete .= $content_chunk;

            // Decode gzip-encoded content when done with document
            if ($document_completed == true && $gzip_encoded_content == true)
                $source_complete = $source_portion = Encoding::decodeGZipContent($source_complete);

            // Find links in portion of the source
            if (($gzip_encoded_content == false && $stream_to_file == false && strlen($source_portion) >= $_Request->content_buffer_size) || $document_completed == true)
            {
                if (Utils::checkStringAgainstRegexArray($_Request->lastResponseHeader->content_type, $_Request->link_search_content_types))
                {
                    Benchmark::stop("data_transfer_time");
                    $_Request->LinkFinder->findLinksInHTMLChunk($source_portion);

                    if ($_Request->source_overlap_size > 0)
                        $source_portion = substr($source_portion, -$_Request->source_overlap_size);
                    else
                        $source_portion = "";

                    Benchmark::start("data_transfer_time");
                }
            }
        }

        if ($stream_to_file == true) @fclose($fp);

        // Stop data-transfer-time benchmark
        Benchmark::stop("data_transfer_time");
        $_Request->data_transfer_time = Benchmark::getElapsedTime("data_transfer_time");

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

        //TODO

        $_Request = $this->_Request;
        $source_chunk = "";
        $stop_receiving = false;
        $bytes_received = 0;
        $document_completed = false;

        // If chunked encoding and protocol to use is HTTP 1.1
        if ($_Request->http_protocol_version == HttpProtocols::HTTP_1_1 && $_Request->lastResponseHeader->transfer_encoding == "chunked")
        {
            // Read size of next chunk
            $chunk_line = @fgets($this->_socket, 128);
            if (trim($chunk_line) == "") $chunk_line = @fgets($this->_socket, 128);
            $current_chunk_size = hexdec(trim($chunk_line));
        }
        else
        {
            $current_chunk_size = $_Request->chunk_buffer_size;
        }

        if ($current_chunk_size === 0)
        {
            $stop_receiving = true;
            $document_completed = true;
        }

        while ($stop_receiving == false)
        {
            socket_set_timeout($this->_socket, $_Request->socketReadTimeout);

            // Set byte-buffer to bytes in socket-buffer (Fix for SSL-hang-bug #56, thanks to MadEgg!)
            $status = socket_get_status($this->_socket);
            if ($status["unread_bytes"] > 0)
                $read_byte_buffer = $status["unread_bytes"];
            else
                $read_byte_buffer = $_Request->socket_read_buffer_size;

            // If chunk will be complete next read -> resize read-buffer to size of remaining chunk
            if ($bytes_received + $read_byte_buffer >= $current_chunk_size && $current_chunk_size > 0)
            {
                $read_byte_buffer = $current_chunk_size - $bytes_received;
                $stop_receiving = true;
            }

            // Read line from socket
            $line_read = @fread($this->_socket, $read_byte_buffer);

            $source_chunk .= $line_read;
            $line_length = strlen($line_read);
            $_Request->content_bytes_received += $line_length;
            $_Request->global_traffic_count += $line_length;
            $bytes_received += $line_length;

            // Check socket-status
            $status = socket_get_status($this->_socket);

            // Check for EOF
            if ($status["unread_bytes"] == 0 && ($status["eof"] == true || feof($this->_socket) == true))
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
                $error_string = "Socket-stream timed out (timeout set to ".$_Request->socketReadTimeout." sec).";
                $document_received_completely = false;
                return $source_chunk;
            }

            // Check if content-length stated in the header is reached
            if ($_Request->lastResponseHeader->content_length == $_Request->content_bytes_received)
            {
                $stop_receiving = true;
                $document_completed = true;
            }

            // Check if contentsize-limit is reached
            if ($_Request->content_size_limit > 0 && $_Request->content_size_limit <= $_Request->content_bytes_received)
            {
                $document_received_completely = false;
                $stop_receiving = true;
                $document_completed = true;
            }

        }

        return $source_chunk;
    }

}
