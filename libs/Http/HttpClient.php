<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 9/28/16
 * Time: 11:29 AM
 */

namespace PhCrawler\Http;


use PhCrawler\Benchmark;
use PhCrawler\Http\Descriptors\ProxyDescriptor;
use PhCrawler\Http\Descriptors\UrlDescriptor;
use PhCrawler\Http\Descriptors\UrlPartsDescriptor;
use PhCrawler\Http\Enums\Timer;

/**
 * Class Request
 *
 * @package PhCrawler\Http
 */
class HttpClient
{
    use handleDocumentInfo,
        handleRequestHeader,
        handleResponseHeader,
        handleResponseBody;

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
     * @var string
     */
    public $userAgent = 'PhCrawler';

    /**
     * @var bool
     */
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
     * @var int
     */
    public $header_bytes_received = 0;

    /**
     * @var null
     */
    public $server_response_time = null;

    /**
     * @var
     */
    public $error_code;
    /**
     * @var
     */
    public $error_message;

    /**
     * @var ResponseHeader
     */
    public $ResponseHeader;

    /**
     * @var DocumentInfo
     */
    public $DocumentInfo;

    /**
     * @var Socket
     */
    public $Socket;

    /**
     * @var
     */
    protected $document_completed;

    /**
     * @var
     */
    protected $document_received_completely;

    /**
     * @var null | \Closure
     */
    public $response_header_check_callback_function = null;

    /**
     * @var null
     */
    public $data_transfer_time = null;

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
     * @var int
     */
    protected $content_size_limit = 0;

    /**
     * @var float | null
     */
    protected $content_bytes_received = null;

    /**
     * @var int
     */
    protected $global_traffic_count = 0;

    protected $server_connect_time = null;

    protected $socket_pre_fill_size = null;

    protected $receive_content_types = null;

    /**
     *
     */
    public function fetch() {

            $this->initDocumentInfo();

            if (!$this->openSocket()) return $this->DocumentInfo;

            $this->sendRequestContent();

            return $this->readResponseContent();
    }


    /**
     * @return bool
     */
    protected function openSocket() {

        $this->Socket = $Socket = new Socket();
        $Socket->UrlParsDescriptor = $this->UrlPartsDescriptor;

        Benchmark::reset(Timer::SERVER_CONNECT);
        Benchmark::start(Timer::SERVER_CONNECT);

        if (!$Socket->open()) {

            $this->setServerConnectTime();
            $this->SetErrorMessage($Socket->error_message);
            $this->SetErrorCode($Socket->error_code);

            return false;
        }


        $this->setServerConnectTime(Benchmark::stop(Timer::SERVER_CONNECT));

        return true;
    }

    /**
     *
     */
    protected function sendRequestContent() {

        $requestHeaderRaw = $this->buildRequestHeaderRaw();

        $this->setDocumentHeaderSend($requestHeaderRaw);

        $this->Socket->send($requestHeaderRaw);

    }

    protected function readResponseContent() {
        $responseHeaderRaw = $this->readResponseHeader();

        $this->setDocumentHeaderReceived($responseHeaderRaw);

        $this->ResponseHeader = new ResponseHeader($responseHeaderRaw, $this->UrlDescriptor->url_rebuild);

        $this->setDocumentResponseHeader($this->ResponseHeader);

        $receive = $this->decideReceiveContent($this->ResponseHeader);

        if ($receive == false)
        {
            $this->Socket->close();
            $this->DocumentInfo->received = false;

            return $this->DocumentInfo;
        }
        else
        {
            $this->DocumentInfo->received = true;
        }

        $this->setDocumentContent($this->readResponseBody());

        $this->DocumentInfo->received_completely = $this->document_received_completely;
        $this->document_completed = $this->document_completed;
        $this->DocumentInfo->bytes_received = $this->content_bytes_received;
        $this->DocumentInfo->header_bytes_received = $this->header_bytes_received;

        $dtr_values = $this->calculateDataTransferRateValues();
        if ($dtr_values != null)
        {
            $this->setDocumentDTR($dtr_values);
        }

        return $this->DocumentInfo;
    }

    /**
     * @return array|null
     */
    protected function calculateDataTransferRateValues()
    {

        $dataValues = array();

        $data_transfer_time = $this->getDataTransferTime();
        // Works like this:
        // After the server resonded, the socket-buffer is already filled with bytes,
        // that means they were received within the server-response-time.

        // To calulate the real data transfer rate, these bytes have to be substractred from the received
        // bytes beofre calulating the rate.

        if ($data_transfer_time > 0 && $this->content_bytes_received > 4 * $this->socket_pre_fill_size)
        {
            $dataValues["unbuffered_bytes_read"] = $this->content_bytes_received + $this->header_bytes_received - $this->socket_pre_fill_size;
            $dataValues["data_transfer_rate"] = $dataValues["unbuffered_bytes_read"] / $data_transfer_time;
            $dataValues["data_transfer_time"] = $data_transfer_time;
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


    protected function setServerConnectTime($time = null) {
        $this->DocumentInfo->server_connect_time = $time;
    }

    protected function setServerResponseTime($time = null) {
        $this->DocumentInfo->server_response_time = $time;
    }

    protected function setDataTransferTime($time = null) {
        $this->DocumentInfo->data_transfer_time = $time;
    }

    public function getDataTransferTime() {
        return $this->DocumentInfo->data_transfer_time;
    }

    protected function setErrorMessage($message) {
        $this->DocumentInfo->error_occured = true;
        $this->DocumentInfo->error_message = $message;
    }

    protected function setErrorCode($code) {
        $this->DocumentInfo->error_occured = true;
        $this->DocumentInfo->error_code = $code;
    }

}
