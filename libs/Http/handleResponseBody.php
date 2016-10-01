<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 9/29/16
 * Time: 2:31 PM
 */

namespace PhCrawler\Http;


use PhCrawler\Benchmark;
use PhCrawler\Http\Enums\Protocols;
use PhCrawler\Http\Enums\Timer;
use PhCrawler\Http\Utils\EncodingUtil;

trait handleResponseBody
{
    protected function readResponseBody() {
        //init
        $this->content_bytes_received = 0;
        $source_complete = "";
        $this->document_received_completely = true;
        $this->document_completed = false;
        $gzip_encoded_content = null;

        Benchmark::start(Timer::DATA_TRANSFER);

        while($this->document_completed == false) {
            $content_chunk = $this->readResponseBodyChunk();
            // Check if content is gzip-encoded (check only first chunk)
            if ($gzip_encoded_content === null)
            {
                if (EncodingUtil::isGzipEncoded($content_chunk))
                    $gzip_encoded_content = true;
                else
                    $gzip_encoded_content = false;
            }

            $source_complete .= $content_chunk;

            if ($this->document_completed == true && $gzip_encoded_content == true)
                $source_complete = EncodingUtil::decodeGZipContent($source_complete);

        }

        Benchmark::stop(Timer::DATA_TRANSFER);

        $this->setDataTransferTime(Benchmark::getElapsedTime(Timer::DATA_TRANSFER));

        return $source_complete;
    }

    protected function readResponseBodyChunk() {
        /**@var $Socket Socket*/
        $Socket = $this->Socket;

        $source_chunk = "";
        $stop_receiving = false;
        $bytes_received = 0;
        $this->document_completed = false;

        // If chunked encoding and protocol to use is HTTP 1.1
        if ($this->isResponseBodyChunked())
        {
            // Read size of next chunk
            $chunk_line = $Socket->gets();
            if (trim($chunk_line) == "") $chunk_line = $Socket->gets();
            $current_chunk_size = hexdec(trim($chunk_line));
        }
        else
        {
            $current_chunk_size = $this->chunk_buffer_size;
        }

        if ($current_chunk_size === 0)
        {
            $stop_receiving = true;
            $this->document_completed = true;
        }

        while ($stop_receiving == false)
        {
            $Socket->setTimeOut();

            // Set byte-buffer to bytes in socket-buffer (Fix for SSL-hang-bug #56, thanks to MadEgg!)
            $unread_bytes = $Socket->getUnreadBytes();

            if ($unread_bytes > 0)
                $read_byte_buffer = $unread_bytes;
            else
                $read_byte_buffer = $this->socket_read_buffer_size;

            // If chunk will be complete next read -> resize read-buffer to size of remaining chunk
            if ($bytes_received + $read_byte_buffer >= $current_chunk_size && $current_chunk_size > 0)
            {
                $read_byte_buffer = $current_chunk_size - $bytes_received;
                $stop_receiving = true;
            }

            // Read line from socket
            $line_read = $Socket->read($read_byte_buffer);

            $source_chunk .= $line_read;
            $line_length = strlen($line_read);
            $bytes_received += $line_length;

            $this->content_bytes_received += $line_length;
            $this->global_traffic_count += $line_length;

            // Check for EOF
            if ($Socket->getUnreadBytes() == 0 && $Socket->isEOF())
            {
                $stop_receiving = true;
                $this->document_completed = true;
            }

            // Socket timed out
            if ($Socket->checkTimeoutStatus())
            {
//                $stop_receiving = true;
                $this->document_completed = true;

                $this->document_received_completely = false;
                return $source_chunk;
            }

            // Check if content-length stated in the header is reached
            if ($this->isReachedContentLength())
            {
                $stop_receiving = true;
                $this->document_completed = true;
            }

            // Check if content-size-limit is reached
            if ($this->isReachedContentSizeLimit())
            {
                $this->document_received_completely = false;
                $stop_receiving = true;
                $this->document_completed = true;
            }

        }
        return $source_chunk;
    }

    protected function isResponseBodyChunked() {
        return $this->http_protocol_version == Protocols::HTTP_1_1 && $this->ResponseHeader->isTransferEncodingChunked();
    }

    protected function isReachedContentLength() {
        return $this->ResponseHeader->content_length == $this->content_bytes_received;
    }

    protected function isReachedContentSizeLimit() {
        return $this->content_size_limit > 0 && $this->content_size_limit <= $this->content_bytes_received;
    }

}
