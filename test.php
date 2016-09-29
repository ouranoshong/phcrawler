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

$Request->UrlDescriptor = new \PhCrawler\Http\Descriptors\UrlDescriptor(
    'https://www.sov5.com/search?q=PHP+Deprecated%3A++SNI_server_name+is+deprecated+in+favor+of+peer_name&re=1'
);
$Request->UrlPartsDescriptor = new \PhCrawler\Http\Descriptors\UrlPartsDescriptor($Request->UrlDescriptor->url_rebuild);

$Request->http_protocol_version =  Request::HTTP_VERSION_1_1;
$Request->fetch();
