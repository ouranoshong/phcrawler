<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 9/28/16
 * Time: 2:22 PM
 */

namespace PhCrawler\Http\Descriptors;

use PhCrawler\Http\Utils\Url as UtilsUrl;

class UrlParts
{

    const PROTOCOL_PREFIX_HTTP = 'http://';

    const PROTOCOL_PREFIX_HTTPS = 'https://';

    /**
     * @var
     */
    public $protocol;

    /**
     * @var
     */
    public $host;

    /**
     * @var
     */
    public $path;

    /**
     * @var
     */
    public $file;

    /**
     * @var
     */
    public $domain;

    /**
     * @var
     */
    public $port;

    /**
     * @var
     */
    public $auth_username;

    /**
     * @var
     */
    public $auth_password;

    public function __construct($url = '')
    {
        if ($url) {
            $this->init($url);
        }
    }

    /**
     * @param string $url
     *
     * @return $this
     */
    public function init($url = '') {
        $parts = UtilsUrl::parse($url);
        $this->protocol = $parts["protocol"];
        $this->host = $parts["host"];
        $this->path = $parts["path"];
        $this->file = $parts["file"];
        $this->domain = $parts["domain"];
        $this->port = $parts["port"];
        $this->auth_username = $parts["auth_username"];
        $this->auth_password = $parts["auth_password"];
        return $this;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return get_object_vars($this);
    }

    public function isSSL() {
        return $this->protocol == self::PROTOCOL_PREFIX_HTTPS;
    }

}
