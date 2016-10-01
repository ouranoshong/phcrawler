<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-10-1
 * Time: 下午4:32
 */

namespace PhCrawler\Http\Request;


use PhCrawler\Http\Contracts\handleRequestField;
use PhCrawler\Http\Contracts\RequestField;

class RequestHeaderCookies extends RequestHeader
{
    public $name = 'Cookie';

    public $cookies = [];

    public function __construct($cookies = [])
    {
        $this->cookies = $cookies;

        $this->setCookiesValue($cookies);
    }

    public function setCookiesValue($cookies) {
        /**@var $cookie \PhCrawler\Http\Request\RequestCookie */
        foreach((array)$cookies as $cookie) {
            $this->value .= "; ".$cookie;
        }

        if ($this->value) $this->value = substr($this->value, 2);
    }

}
