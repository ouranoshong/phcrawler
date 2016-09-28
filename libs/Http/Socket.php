<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 9/28/16
 * Time: 12:51 PM
 */

namespace PhCrawler\Http;


use PhCrawler\Http\Descriptors\ProxyDescriptor;
use PhCrawler\Http\Descriptors\UrlPartsDescriptor;
use PhCrawler\Http\Enums\RequestErrors;
use PhCrawler\Http\Utils\DNSUtil;

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

    public $timeout = 60;
    public $error_code;
    public $error_message;
    public $flag = STREAM_CLIENT_CONNECT;

    protected $protocol_prefix = '';

    /**
     * @var ProxyDescriptor
     */
    public $ProxyDescriptor;
    /**
     * @var UrlPartsDescriptor
     */
    public $UrlParsDescriptor;


    const SOCKET_PROTOCOL_PREFIX_SSL = 'ssl://';

    public $header_response;
    public $response_body_complety;

    protected function isSSLConnection() {
        return $this->UrlParsDescriptor instanceof UrlPartsDescriptor && $this->UrlParsDescriptor->isSSL();
    }

    protected function isProxyConnection() {
        return $this->ProxyDescriptor instanceof ProxyDescriptor && $this->ProxyDescriptor->host !== null;
    }

    protected function canOpen() {

        if (!($this->UrlParsDescriptor instanceof UrlPartsDescriptor)) {

            $this->error_code = RequestErrors::ERROR_HOST_UNREACHABLE;
            $this->error_message = "Require connection information!";
            return false;

        }

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

        if ($context = $this->getClientContext()) {
            $this->_socket = stream_socket_client(
                $this->getClientRemoteURI(),
                $this->error_code,
                $this->error_message,
                $this->timeout,
                $this->flag,
                $context
            );
        }  else {
            $this->_socket = @stream_socket_client(
                $this->getClientRemoteURI(),
                $this->error_code,
                $this->error_message,
                $this->timeout,
                $this->flag
            );

        }

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

            $host = DNSUtil::getIpByHostName($this->UrlParsDescriptor->host);
            $port = $this->UrlParsDescriptor->port;

            if ($this->isSSLConnection()) {
                $host = $this->UrlParsDescriptor->host;
                $protocol_prefix = self::SOCKET_PROTOCOL_PREFIX_SSL;
            }

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
        return fwrite($this->_socket, $message, strlen($message));
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

    public function getStatus()
    {
        return socket_get_status($this->_socket);
    }

}
