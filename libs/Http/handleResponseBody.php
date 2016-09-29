<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 9/29/16
 * Time: 2:31 PM
 */

namespace PhCrawler\Http;


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
            $content_chunk = $this->readResponseContentChunk();
            $source_portion .= $content_chunk;

            // Check if content is gzip-encoded (check only first chunk)
            if ($gzip_encoded_content === null)
            {
                if (EncodingUtil::isGzipEncoded($content_chunk))
                    $gzip_encoded_content = true;
                else
                    $gzip_encoded_content = false;
            }

            if ($this->document_completed == true && $gzip_encoded_content == true)
                $source_complete = $source_portion = EncodingUtil::decodeGZipContent($source_complete);
        }

        return $source_complete;
    }

    public function readResponseBodyChunk() {
        $Socket = $this->Socket;

        if ($this->http_protocol_version == HttpProtocols::HTTP_1_1 && $this->ResponseHeader->isTransferChunked())
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
    }

}
