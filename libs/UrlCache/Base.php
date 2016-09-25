<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-9-24
 * Time: 下午10:32
 */

namespace PhCrawler\UrlCache;


use PhCrawler\URLDescriptor;
use PhCrawler\Utils\Utils;

abstract class Base
{
    protected $url_priorities = array();

    /**
     * Defines which property of an URL is used to ensure that each URL is only cached once.
     *
     * @var int One of the URLHASH_.. constants
     */
    public $url_distinct_property = self::URLHASH_URL;

    const URLHASH_URL = 1;
    const URLHASH_RAWLINK= 2;
    const URLHASH_NONE = 3;

    /**
     * Returns the next URL from the cache that should be crawled.
     *
     * @return URLDescriptor
     */
    abstract public function getNextUrl();

    /**
     * Returns all URLs currently cached in the URL-cache.
     *
     * @return array Numeric array containing all URLs as URLDescriptor-objects
     */
    abstract public function getAllURLs();

    /**
     * Removes all URLs and all priority-rules from the URL-cache.
     */
    abstract public function clear();

    /**
     * Adds an URL to the url-cache
     *
     * @param URLDescriptor $UrlDescriptor
     */
    abstract public function addURL(URLDescriptor $UrlDescriptor);

    /**
     * Adds an bunch of URLs to the url-cache
     *
     * @param array $urls  A numeric array containing the URLs as URLDescriptor-objects
     */
    abstract public function addURLs($urls);

    /**
     * Checks whether there are URLs left in the cache or not.
     *
     * @return bool
     */
    abstract public function containsURLs();

    /**
     * Marks the given URL in the cache as "followed"
     *
     * @param URLDescriptor $UrlDescriptor
     */
    abstract public function markUrlAsFollowed(URLDescriptor $UrlDescriptor);

    /**
     * Do cleanups after the cache is not needed anymore
     */
    abstract public function cleanup();

    /**
     * Cleans/purges the URL-cache from inconsistent entries.
     */
    abstract public function purgeCache();


    protected function getDistinctURLHash(URLDescriptor $UrlDescriptor)
    {
        if ($this->url_distinct_property == self::URLHASH_URL)
            return md5($UrlDescriptor->url_rebuild);
        elseif ($this->url_distinct_property == self::URLHASH_RAWLINK)
            return md5($UrlDescriptor->link_raw);
        else
            return null;
    }


    protected function getUrlPriority($url)
    {
        $cnt = count($this->url_priorities);
        for ($x=0; $x<$cnt; $x++)
        {
            if (preg_match($this->url_priorities[$x]["match"], $url))
            {
                return $this->url_priorities[$x]["level"];
            }
        }

        return 0;
    }

    /**
     * Adds a Link-Priority-Level
     *
     * @param string $regex
     * @param int    $level
     */
    public function addLinkPriority($regex, $level)
    {
        $c = count($this->url_priorities);
        $this->url_priorities[$c]["match"] = trim($regex);
        $this->url_priorities[$c]["level"] = trim($level);

        // Sort url-priortie-array so that high priority-levels come firts.
        Utils::sort2dArray($this->url_priorities, "level", SORT_DESC);
    }

    /**
     * Adds a bunch of link-priorities
     *
     * @param array $priority_array Numeric array containing the subkeys "match" and "level"
     */
    public function addLinkPriorities($priority_array)
    {
        for ($x=0; $x<count($priority_array); $x++)
        {
            $this->addLinkPriority($priority_array[$x]["match"], $priority_array[$x]["level"]);
        }
    }
}
