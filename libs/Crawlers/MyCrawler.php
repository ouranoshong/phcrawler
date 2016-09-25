<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-9-25
 * Time: 下午4:02
 */

namespace PhCrawler\Crawlers;


use PhCrawler\Crawler;
use PhCrawler\DocumentInfo;

class MyCrawler extends Crawler
{
    function handleDocumentInfo(DocumentInfo $DocInfo)
    {
        // Just detect linebreak for output ("\n" in CLI-mode, otherwise "<br>").
        if (PHP_SAPI == "cli") $lb = "\n";
        else $lb = "<br />";

        // Print the URL and the HTTP-status-Code
        echo "Page requested: ".$DocInfo->url." (".$DocInfo->http_status_code.")".$lb;

        // Print the refering URL
        echo "Referer-page: ".$DocInfo->referer_url.$lb;

        echo "Header received: ".$DocInfo->header_bytes_received." bytes".$lb;

        // Print if the content of the document was be recieved or not
        if ($DocInfo->received == true)
            echo "Content received: ".$DocInfo->bytes_received." bytes".$lb;
        else
            echo "Content not received".$lb;

        // Now you should do something with the content of the actual
        // received page or file ($DocInfo->source), we skip it in this example

        echo $lb;

        flush();
    }
}
