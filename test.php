<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-9-28
 * Time: 下午7:45
 */


require 'vendor/autoload.php';

use PhCrawler\Http\HttpClient;

$Request = new HttpClient();
// https://googleads.g.doubleclick.net/pagead/html/r20160922/r20160727/zrt_lookup.html
//$Request->UrlDescriptor = new \PhCrawler\Http\Descriptors\UrlDescriptor(
//    'https://googleads.g.doubleclick.net/pagead/html/r20160922/r20160727/zrt_lookup.html'
//);

$Request->UrlDescriptor = new \PhCrawler\Http\Descriptors\UrlDescriptor(
    'localhost:8080/server.php?query=GET'
);

$Request->cookie_data = ['coookie'=> 'cookie'];

$Request->post_data = ['q'=>'hello'];

$Request->UrlPartsDescriptor = new \PhCrawler\Http\Descriptors\UrlPartsDescriptor($Request->UrlDescriptor->url_rebuild);

$Request->http_protocol_version =  \PhCrawler\Http\Enums\Protocols::HTTP_1_1;
$DocumentInfo = $Request->fetch();


var_dump($DocumentInfo);

//var_dump($Request);
