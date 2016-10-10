<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 9/28/16
 * Time: 2:52 PM
 */

namespace PhCrawler\Utils;


class DNSUtil
{
    public static $HOST_IP_TABLE = [];

    public static function getIpByHostName($name = '') {
        // If host already was queried
        if (isset(self::$HOST_IP_TABLE[$name]))
        {
            return self::$HOST_IP_TABLE[$name];
        } else {
            $ip = gethostbyname($name);
            self::$HOST_IP_TABLE[$name] = $ip;
            return $ip;
        }
    }

}
