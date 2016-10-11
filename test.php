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


$linkCache = new \PhCrawler\LinkCache();

$linkCache->addLink(new \PhCrawler\Descriptors\LinkDescriptor(
    'http://www.baidu.com'
));

while($link = $linkCache->getNextLink()) {
    /**@var $link \PhCrawler\Descriptors\LinkDescriptor*/

    var_dump($link);

    $Request = new HttpClient();
    $Request->setUrl($link);

    $DocumentInfo = $Request->fetch();

    $linksFound = [];

    if ($DocumentInfo instanceof \PhCrawler\Http\Response\DocumentInfo) {
        $Finder = new \PhCrawler\LinkFinder();
        $Filter = new \PhCrawler\LinkFilter();

        $Finder->setSourceLink($Request->LinkDescriptor);
        $Filter->setBaseURL($Request->LinkDescriptor->url_rebuild);

        $Filter->max_crawling_depth = 2;

        $linksFound[] = $Finder->getRedirectLinkInHeader($DocumentInfo->header_received);
        $linksFound = array_merge($linksFound, $Finder->getLinksInContent($DocumentInfo->content));

        $linksFound = $Filter->filterLinks($linksFound);
    }

    if (count($linksFound)) {
        foreach($linksFound as $linkFound) {
            if ($linkFound) {
                $linkCache->addLink($linkFound);
            }
        }
    }

}


//var_dump($Request);
