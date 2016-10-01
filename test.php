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

$Request->setUrl(new \PhCrawler\Http\Descriptors\UrlDescriptor(
    'http://www.baidu.com'
));

$DocumentInfo = $Request->fetch();

var_dump($DocumentInfo);

//var_dump($Request);
