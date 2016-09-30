<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-9-28
 * Time: 下午7:45
 */


require 'vendor/autoload.php';

use PhCrawler\Http\Request;

$Request = new Request();
// https://googleads.g.doubleclick.net/pagead/html/r20160922/r20160727/zrt_lookup.html
//$Request->UrlDescriptor = new \PhCrawler\Http\Descriptors\UrlDescriptor(
//    'https://googleads.g.doubleclick.net/pagead/html/r20160922/r20160727/zrt_lookup.html'
//);

$Request->UrlDescriptor = new \PhCrawler\Http\Descriptors\UrlDescriptor(
    'http://www.baidu.com'
);

$Request->UrlPartsDescriptor = new \PhCrawler\Http\Descriptors\UrlPartsDescriptor($Request->UrlDescriptor->url_rebuild);

$Request->http_protocol_version =  \PhCrawler\Http\Enums\Protocols::HTTP_1_1;
$Request->fetch();


var_dump($Request);

//var_dump($Request);
