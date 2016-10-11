<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-9-28
 * Time: 下午7:45
 */


require 'vendor/autoload.php';

use PhCrawler\Http\HttpClient;



// https://googleads.g.doubleclick.net/pagead/html/r20160922/r20160727/zrt_lookup.html
//$Request->UrlDescriptor = new \PhCrawler\Http\Descriptors\UrlDescriptor(
//    'https://googleads.g.doubleclick.net/pagead/html/r20160922/r20160727/zrt_lookup.html'
//);


$linkCache = new SplQueue();

$linkCache->enqueue(new \PhCrawler\Descriptors\LinkDescriptor(
    'http://www.baidu.com'
));

while($link = $linkCache->dequeue()) {
    /**@var $link \PhCrawler\Descriptors\LinkDescriptor*/

    var_dump($link);
    if ( $link->url_link_depth > 2 ) continue;

    $Request = new HttpClient();
    $Request->setUrl($link);

    $DocumentInfo = $Request->fetch();

    if ($DocumentInfo instanceof \PhCrawler\Http\Response\DocumentInfo) {
        $Finder = new \PhCrawler\LinkFinder();
        $Finder->setSourceLink($Request->LinkDescriptor);
        $linkFound = $Finder->getRedirectLinkInHeader($DocumentInfo->header_received);
        if ($linkFound) {
            $linkCache->enqueue($linkFound);
        }
        foreach((array)$Finder->getLinksInContent($DocumentInfo->content) as $linkFound) {
            if ($linkFound) {
                $linkCache->enqueue($linkFound);
            }
        }
    }
}


//var_dump($Request);
