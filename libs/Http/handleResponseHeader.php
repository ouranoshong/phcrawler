<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 9/29/16
 * Time: 9:53 AM
 */

namespace PhCrawler\Http;


use PhCrawler\Benchmark;
use PhCrawler\Http\Enums\RequestErrors;
use PhCrawler\Http\Enums\Timer;
use PhCrawler\Http\Response\ResponseHeader;
use PhCrawler\Utils\UrlUtil;

trait handleResponseHeader
{

    protected function readResponseHeader() {
        /**@var \PhCrawler\Http\Socket $Socket */
        $Socket = $this->Socket;

        Benchmark::reset(Timer::SERVER_RESPONSE);
        Benchmark::start(Timer::SERVER_RESPONSE);

        $source_read = '';
        $header = '';
        $server_response = false;

        while( !$Socket->isEOF() ) {

            $Socket->setTimeOut();
            $line_read = $Socket->gets();

            if ($server_response == false) {

                $this->setServerResponseTime(Benchmark::stop(Timer::SERVER_RESPONSE));
                $this->socket_pre_fill_size = $Socket->getUnreadBytes();
                $server_response = true;

                Benchmark::reset(Timer::DATA_TRANSFER);
                Benchmark::start(Timer::DATA_TRANSFER);

            }

            $source_read .= $line_read;
            $this->global_traffic_count += strlen($line_read);

            if ($Socket->checkTimeoutStatus()) {
                $this->setErrorCode($Socket->error_code);
                $this->setErrorMessage($Socket->error_message);
                return $header;
            }

            if (!$this->isHttpResponse($source_read))
            {
                $this->setErrorCode(RequestErrors::ERROR_NO_HTTP_HEADER);
                $this->setErrorMessage("HTTP-protocol error.");
                return $header;
            }

            // Header found and read (2 newlines) -> stop
            if ($this->isFoundResponseHeader($source_read))
            {
                $header = $this->generateRealHeader($source_read);
                break;
            }
        }

        Benchmark::stop(Timer::DATA_TRANSFER);

        // Header was found
        if ($header != "")
        {
            $this->header_bytes_received = strlen($header);
            return $header;
        }

        // No header found
        if ($header == "")
        {
            $this->setServerResponseTime();
            $this->setErrorCode(RequestErrors::ERROR_NO_HTTP_HEADER);
            $this->setErrorMessage("Host doesn't respond with a HTTP-header.");
            return null;
        }

    }

    protected function isHttpResponse($source) {
        return strtolower(substr($source, 0, 4)) == "http";
    }

    protected function isFoundResponseHeader($source) {
        return substr($source, -4, 4) == "\r\n\r\n" || substr($source, -2, 2) == "\n\n";
    }

    protected function generateRealHeader($source) {
        return substr($source, 0, strlen($source)-2);
    }

    protected function decideReceiveContent(ResponseHeader $responseHeader)
    {
        // Get Content-Type from header
        $content_type = $responseHeader->content_type;

        // Call user header-check-callback-method
        if ($this->response_header_check_callback_function != null)
        {
            $ret = call_user_func($this->response_header_check_callback_function, $responseHeader);
            if ($ret < 0) return false;
        }

        // No Content-Type given
        if ($content_type == null)
            return false;

        // Status-code not 2xx
        if ($responseHeader->http_status_code == null || $responseHeader->http_status_code > 299 || $responseHeader->http_status_code < 200)
            return false;

        // Check against the given content-type-rules
        $receive = UrlUtil::checkStringAgainstRegexArray($content_type, $this->receive_content_types);

        return $receive;
    }

}
