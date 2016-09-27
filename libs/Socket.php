<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-9-27
 * Time: 下午7:09
 */

namespace PhCrawler;
use PhCrawler\Enums\HttpProtocols;
use PhCrawler\Enums\RequestErrors;
use PhCrawler\Utils\Encoding;
use PhCrawler\Utils\Utils;


/**
 * Class Socket
 *
 * @package PhCrawler
 */
class Socket
{
    /**
     * @var resource
     */
    protected $_socket;

    /**
     * @var \PhCrawler\Request
     */
    protected $_Request;

    /**
     * Socket constructor.
     *
     * @param \PhCrawler\Request $Request
     */
    public function __construct(Request $Request)
    {
       $this->_Request = $Request; 
    }


    /**
     * @param $error_code
     * @param $error_string
     *
     * @return bool
     */
    public function open(&$error_code, &$error_string)
    {
        Benchmark::reset("connecting_server");
        Benchmark::start("connecting_server");

        $_Request = $this->_Request;
        
        // SSL or not?
        if ($_Request->url_parts["protocol"] == "https://") $protocol_prefix = "ssl://";
        else $protocol_prefix = "";

        // If SSL-request, but openssl is not installed
        if ($protocol_prefix == "ssl://" && !extension_loaded("openssl"))
        {
            $error_code = RequestErrors::ERROR_SSL_NOT_SUPPORTED;
            $error_string = "Error connecting to ".$_Request->url_parts["protocol"].$_Request->url_parts["host"].": SSL/HTTPS-requests not supported, extension openssl not installed.";
        }

        // Get IP for hostname
        $ip_address = $_Request->DNSCache->getIP($_Request->url_parts["host"]);

        // Open socket
        if ($_Request->proxy != null)
        {
            $this->_socket = @stream_socket_client($_Request->proxy["proxy_host"].":".$_Request->proxy["proxy_port"], $error_code, $error_str,
                $_Request->socketConnectTimeout, STREAM_CLIENT_CONNECT);
        }
        else
        {
            // If ssl -> perform Server name indication
            if ($_Request->url_parts["protocol"] == "https://")
            {
                $context = stream_context_create(array('ssl' => array('SNI_server_name' => $_Request->url_parts["host"])));
                $this->_socket = @stream_socket_client($protocol_prefix.$ip_address.":".$_Request->url_parts["port"], $error_code, $error_str,
                    $_Request->socketConnectTimeout, STREAM_CLIENT_CONNECT, $context);
            }
            else
            {
                $this->_socket = @stream_socket_client($protocol_prefix.$ip_address.":".$_Request->url_parts["port"], $error_code, $error_str,
                    $_Request->socketConnectTimeout, STREAM_CLIENT_CONNECT); // NO $context here, memory-leak-bug in php v. 5.3.x!!
            }
        }

        $_Request->server_connect_time = Benchmark::stop("connecting_server");

        // If socket not opened -> throw error
        if ($this->_socket == false)
        {
            $_Request->server_connect_time = null;

            // If proxy not reachable
            if ($_Request->proxy != null)
            {
                $error_code = RequestErrors::ERROR_PROXY_UNREACHABLE;
                $error_string = "Error connecting to proxy ".$_Request->proxy["proxy_host"].": Host unreachable (".$error_str.").";
                return false;
            }
            else
            {
                $error_code = RequestErrors::ERROR_HOST_UNREACHABLE;
                $error_string = "Error connecting to ".$_Request->url_parts["protocol"].$_Request->url_parts["host"].": Host unreachable (".$error_str.").";
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
    public function sendRequestHeader($request_header_lines) {
        
        // Header senden
        $cnt = count($request_header_lines);
        for ($x=0; $x<$cnt; $x++)
        {
            fputs($this->_socket, $request_header_lines[$x]);
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
    public function readResponseHeader(&$error_code, &$error_string)
    {
        $_Request = $this->_Request;
        
        Benchmark::reset("server_response_time");
        Benchmark::start("server_response_time");

        $status = socket_get_status($this->_socket);
        $source_read = "";
        $header = "";
        $server_responded = false;

        while ($status["eof"] == false)
        {
            socket_set_timeout($this->_socket, $_Request->socketReadTimeout);

            // Read line from socket
            $line_read = fgets($this->_socket, 1024);

            // Server responded
            if ($server_responded == false)
            {
                $server_responded = true;
                $_Request->server_response_time = Benchmark::stop("server_response_time");

                // Determinate socket prefill size
                $status = socket_get_status($this->_socket);
                $_Request->socket_pre_fill_size = $status["unread_bytes"];

                // Start data-transfer-time bechmark
                Benchmark::reset("data_transfer_time");
                Benchmark::start("data_transfer_time");
            }

            $source_read .= $line_read;

            $_Request->global_traffic_count += strlen($line_read);

            $status = socket_get_status($this->_socket);

            // Socket timed out
            if ($status["timed_out"] == true)
            {
                $error_code = RequestErrors::ERROR_SOCKET_TIMEOUT;
                $error_string = "Socket-stream timed out (timeout set to ".$_Request->socketReadTimeout." sec).";
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
            $_Request->LinkFinder->processHTTPHeader($header);
            $_Request->header_bytes_received = strlen($header);
            return $header;
        }

        // No header found
        if ($header == "")
        {
            $_Request->server_response_time = null;
            $error_code = RequestErrors::ERROR_NO_HTTP_HEADER;
            $error_string = "Host doesn't respond with a HTTP-header.";
            return null;
        }

        return null;
    }
    
    public function close() {
        return @fclose($this->_socket);
    }

    public function readResponseContent($stream_to_file = false, &$error_code, &$error_string, &$document_received_completely)
    {
        $fp = null;
        $_Request = $this->_Request;

        $_Request->content_bytes_received = 0;

        // If content should be streamed to file
        if ($stream_to_file == true)
        {
            $fp = @fopen($_Request->tmpFile, "w");

            if ($fp == false)
            {
                $error_code = RequestErrors::ERROR_TMP_FILE_NOT_WRITEABLE;
                $error_string = "Couldn't open the temporary file ".$_Request->tmpFile." for writing.";
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
