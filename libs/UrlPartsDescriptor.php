<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-9-24
 * Time: 下午11:16
 */

namespace PhCrawler;


use PhCrawler\Utils\Utils;

class UrlPartsDescriptor
{
    public $protocol;

    public $host;

    public $path;

    public $file;

    public $domain;

    public $port;

    public $auth_username;

    public $auth_password;

    public static function fromURL($url)
    {
        $parts = Utils::splitURL($url);

        $tmp = new UrlPartsDescriptor();

        $tmp->protocol = $parts["protocol"];
        $tmp->host = $parts["host"];
        $tmp->path = $parts["path"];
        $tmp->file = $parts["file"];
        $tmp->domain = $parts["domain"];
        $tmp->port = $parts["port"];
        $tmp->auth_username = $parts["auth_username"];
        $tmp->auth_password = $parts["auth_password"];

        return $tmp;
    }

    public function toArray()
    {
        return get_object_vars($this);
    }
}
