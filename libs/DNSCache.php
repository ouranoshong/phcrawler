<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-9-24
 * Time: 下午11:12
 */

namespace PhCrawler;


use PhCrawler\Utils\Utils;

class DNSCache
{
    /**
     * Array for caching IPs of the requested hostnames
     *
     * @var array Associative array, keys = hostnames, values = IPs.
     */
    protected $host_ip_array;

    public function __construct()
    {
    }


    public function getIP($hostname)
    {
        // If host already was queried
        if (isset($this->host_ip_array[$hostname]))
        {
            return $this->host_ip_array[$hostname];
        }

        // Else do DNS-query
        else
        {
            $ip = gethostbyname($hostname);
            $this->host_ip_array[$hostname] = $ip;
            return $ip;
        }
    }

    /**
     * Checks whether a hostname is already cached.
     *
     * @param string $hostname The hostname
     * @return bool
     */
    public function hostInCache($hostname)
    {
        if (isset($this->host_ip_array[$hostname])) return true;
        else return false;
    }

    /**
     * Checks whether the hostname of the given URL is already cached
     *
     * @param URLDescriptor $URL The URL
     * @return bool
     */
    public function urlHostInCache(URLDescriptor $URL)
    {
        $url_parts = Utils::splitURL($URL->url_rebuild);
        return $this->hostInCache($url_parts["host"]);
    }
}
