<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 9/30/16
 * Time: 5:17 PM
 *
 *
 *
 */

namespace PhCrawler\Http;


use PhCrawler\Descriptors\LinkDescriptor;
use PhCrawler\Descriptors\LinkPartsDescriptor;
use PhCrawler\Http\Response\DocumentInfo;
use PhCrawler\Http\Response\ResponseHeader;

trait handleDocumentInfo
{
    protected function initDocumentInfo() {

        $this->DocumentInfo = new DocumentInfo();

        $this->setDocumentUrl($this->UrlDescriptor);

        $this->setDocumentUrlParts($this->UrlPartsDescriptor);
    }

    protected function setDocumentResponseHeader(ResponseHeader $responseHeader) {
        $this->DocumentInfo->http_status_code = $responseHeader->http_status_code;
        $this->DocumentInfo->content_type = $responseHeader->content_type;
        $this->DocumentInfo->cookies = $responseHeader->cookies;
        $this->setDocumentHeaderReceived($responseHeader->header_raw);
    }

    protected function setDocumentHeaderSend($raw) {
        $this->DocumentInfo->header_send = $raw;
    }

    protected function setDocumentHeaderReceived($raw) {
        $this->DocumentInfo->header_received = $raw;
    }

    protected function setDocumentContent($raw) {
        $this->DocumentInfo->content = $raw;
    }

    protected function setDocumentUrlParts(LinkPartsDescriptor $UrlParts) {
        $this->DocumentInfo->protocol = $UrlParts->protocol;
        $this->DocumentInfo->host = $UrlParts->host;
        $this->DocumentInfo->path = $UrlParts->path;
        $this->DocumentInfo->port = $UrlParts->port;
        $this->DocumentInfo->file = $UrlParts->file;
        $this->DocumentInfo->query = $UrlParts->query;
    }

    protected function setDocumentUrl(LinkDescriptor $Url) {
        $this->DocumentInfo->url = $Url->url_rebuild;
        $this->DocumentInfo->url_link_depth = $Url->url_link_depth;
        $this->DocumentInfo->referer_url = $Url->refering_url;
        $this->DocumentInfo->refering_link_code = $Url->link_code;
        $this->DocumentInfo->refering_link_raw = $Url->link_raw;
        $this->DocumentInfo->refering_link_text = $Url->link_text;
    }

    protected function setDocumentDTR($dtr_values) {
        $this->DocumentInfo->data_transfer_rate = $dtr_values["data_transfer_rate"];
        $this->DocumentInfo->unbuffered_bytes_read = $dtr_values["unbuffered_bytes_read"];
        $this->DocumentInfo->data_transfer_time = $dtr_values["data_transfer_time"];
    }

    protected function setDocumentStatistics() {
        $this->DocumentInfo->received_completely = $this->document_received_completely;
        $this->DocumentInfo->bytes_received = $this->content_bytes_received;
        $this->DocumentInfo->header_bytes_received = $this->header_bytes_received;

        $dtr_values = $this->calculateDataTransferRateValues();
        if ($dtr_values != null)
        {
            $this->setDocumentDTR($dtr_values);
        }
    }
}
