<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 9/29/16
 * Time: 2:31 PM
 */

namespace PhCrawler\Http;


use PhCrawler\Http\Enums\Protocols;
use PhCrawler\Http\Utils\EncodingUtil;

trait handleResponseBody
{
    public function readResponseBody() {

        $Socket = $this->Socket;

        // Init
        $source_portion = "";
        $source_complete = "";
        $this->document_received_completely = true;
        $this->document_completed = false;
        $gzip_encoded_content = null;


        while($this->document_completed == false) {
            $content_chunk = $this->readResponseBodyChunk();

            $source_portion .= $content_chunk;

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
                $source_complete = $source_portion = EncodingUtil::decodeGZipContent($source_complete);


        }
             var_dump($source_complete);
        return $source_complete;
    }

    public function readResponseBodyChunk() {
        /**@var $Socket Socket*/
        $Socket = $this->Socket;

        $source_chunk = "";
        $stop_receiving = false;
        $bytes_received = 0;
        $this->document_completed = false;

        // If chunked encoding and protocol to use is HTTP 1.1
        if ($this->http_protocol_version == Protocols::HTTP_1_1 && $this->ResponseHeader->isTransferEncodingChunked())
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
            $status = $Socket->getStatus();
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
            $line_read = $Socket->read($read_byte_buffer);

            $source_chunk .= $line_read;
            $line_length = strlen($line_read);
            $this->content_bytes_received += $line_length;
            $this->global_traffic_count += $line_length;
            $bytes_received += $line_length;

            // Check socket-status
            $status = $Socket->getStatus();

            // Check for EOF
            if ($status["unread_bytes"] == 0 && $Socket->isEOF())
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
            if ($this->ResponseHeader->content_length == $this->content_bytes_received)
            {
                $stop_receiving = true;
                $this->document_completed = true;
            }

            // Check if contentsize-limit is reached
            if ($this->content_size_limit > 0 && $this->content_size_limit <= $this->content_bytes_received)
            {
                $this->document_received_completely = false;
                $stop_receiving = true;
                $this->document_completed = true;
            }

        }
        return $source_chunk;
    }

}