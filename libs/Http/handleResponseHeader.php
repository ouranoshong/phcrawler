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

trait handleResponseHeader
{

    public function readResponseHeader() {
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

                $this->server_response_time = Benchmark::stop(Timer::SERVER_RESPONSE);
                $server_response = true;

                Benchmark::reset(Timer::DATA_TRANSFER);
                Benchmark::start(Timer::DATA_TRANSFER);

            }

            $source_read .= $line_read;
            $this->global_traffic_count += strlen($line_read);

            if ($Socket->checkTimeoutStatus()) {
                $this->error_code = $Socket->error_code;
                $this->error_message = $Socket->error_message;
                return $header;
            }

            if (!$this->isHttpResponse($source_read))
            {
                $this->error_code = RequestErrors::ERROR_NO_HTTP_HEADER;
                $this->error_message = "HTTP-protocol error.";
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
            $this->server_response_time = null;
            $this->error_code = RequestErrors::ERROR_NO_HTTP_HEADER;
            $this->error_message = "Host doesn't respond with a HTTP-header.";
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

}
