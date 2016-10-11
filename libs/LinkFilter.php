<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 10/11/16
 * Time: 4:34 PM
 */

namespace PhCrawler;


use PhCrawler\Descriptors\LinkDescriptor;
use PhCrawler\Descriptors\LinkPartsDescriptor;
use PhCrawler\Utils\LinkUtil;

class LinkFilter
{
    /**
     * The full qualified and normalized URL the crawling-prpocess was started with.
     *
     * @var string
     */
    protected $starting_link = "";

    /**
     * The URL-parts of the starting-url.
     *
     * @var LinkPartsDescriptor
     *
     */
    protected $starting_link_parts = null;

    /**
     * Array containing regex-rules for URLs that should be followed.
     *
     * @var array
     */
    protected $url_follow_rules = array();

    /**
     * Array containing regex-rules for URLs that should NOT be followed.
     *
     * @var array
     */
    protected $url_filter_rules = array();

    /**
     * Defines whether nofollow-tags should get obeyed.
     *
     * @var bool
     */
    public $obey_nofollow_tags = false;

    /**
     * The general follow-mode of the crawler
     *
     * @var int The follow-mode
     *
     *          0 -> follow every links
     *          1 -> stay in domain
     *          2 -> stay in host
     *          3 -> stay in path
     */
    public $general_follow_mode = 2;

    /**
     * The maximum crawling-depth
     *
     * @var int
     */
    public $max_crawling_depth = null;

    /**
     * Sets the base-URL of the crawling process some rules relate to
     *
     * @param string $starting_url The URL the crawling-process was started with.
     */
    public function setBaseURL($starting_url)
    {
        $this->starting_link = $starting_url;

        // Parts of the starting-URL
        $this->starting_link_parts = new LinkPartsDescriptor($starting_url);
    }


    public function filterLinks($links_found_url_descriptors)
    {
        $cnt = count($links_found_url_descriptors);
        for ($x=0; $x<$cnt; $x++)
        {
            if ($links_found_url_descriptors[$x] && !$this->linkMatchesRules($links_found_url_descriptors[$x]))
            {
                $links_found_url_descriptors[$x] = null;
            }
        }

        return array_filter((array)$links_found_url_descriptors);

    }


    protected function linkMatchesRules(LinkDescriptor $url)
    {
        // URL-parts of the URL to check against the filter-rules
        $LinkParts = new LinkPartsDescriptor($url->url_rebuild);

        // Kick out all links that are NOT of protocol "http" or "https"
        if ($LinkParts->protocol != "http://" && $LinkParts->protocol != "https://")
        {
            return false;
        }

        // Kick out URLs exceeding the maximum crawling-depth
        if ($this->max_crawling_depth !== null && $url->url_link_depth > $this->max_crawling_depth)
        {
            return false;
        }

        // If meta-tag "robots"->"nofollow" is present and obey_nofollow_tags is TRUE -> always kick out URL
//        if ($this->obey_nofollow_tags == true &&
//            isset($this->CurrentDocumentInfo->meta_attributes["robots"]) &&
//            preg_match("#nofollow# i", $this->CurrentDocumentInfo->meta_attributes["robots"]))
//        {
//            return false;
//        }

        // If linkcode contains "rel='nofollow'" and obey_nofollow_tags is TRUE -> always kick out URL
//        if ($this->obey_nofollow_tags == true)
//        {
//            if (preg_match("#^<[^>]*rel\s*=\s*(?|\"\s*nofollow\s*\"|'\s*nofollow\s*'|\s*nofollow\s*)[^>]*>#", $url->linkcode))
//            {
//                return false;
//            }
//        }

        // Filter URLs to other domains if wanted
        if ($this->general_follow_mode >= 1)
        {
            if ($LinkParts->domain != $this->starting_link_parts->domain) return false;
        }

        // Filter URLs to other hosts if wanted
        if ($this->general_follow_mode >= 2)
        {
            // Ignore "www." at the beginning of the host, because "www.foo.com" is the same host as "foo.com"
            if (preg_replace("#^www\.#", "", $LinkParts->host) != preg_replace("#^www\.#", "", $this->starting_link_parts->host))
                return false;
        }

        // Filter URLs leading path-up if wanted
        if ($this->general_follow_mode == 3)
        {
            if ($LinkParts->protocol != $this->starting_link_parts->protocol ||
                preg_replace("#^www\.#", "", $LinkParts->host) != preg_replace("#^www\.#", "", $this->starting_link_parts->host) ||
                substr($LinkParts->path, 0, strlen($this->starting_link_parts->path)) != $this->starting_link_parts->path)
            {
                return false;
            }
        }

        // Filter URLs by url_filter_rules
        for ($x=0; $x<count($this->url_filter_rules); $x++)
        {
            if (preg_match($this->url_filter_rules[$x], $url->url_rebuild)) return false;
        }

        // Filter URLs by url_follow_rules
        if (count($this->url_follow_rules) > 0)
        {
            $match_found = false;
            for ($x=0; $x<count($this->url_follow_rules); $x++)
            {
                if (preg_match($this->url_follow_rules[$x], $url->url_rebuild))
                {
                    $match_found = true;
                    break;
                }
            }

            if ($match_found == false) return false;
        }

        return true;
    }

    public function addURLFollowRule($regex)
    {
        $check = LinkUtil::checkRegexPattern($regex); // Check pattern

        if ($check == true)
        {
            $this->url_follow_rules[] = trim($regex);
        }
        return $check;
    }


    public function addURLFilterRule($regex)
    {
        $check = LinkUtil::checkRegexPattern($regex); // Check pattern

        if ($check == true)
        {
            $this->url_filter_rules[] = trim($regex);
        }
        return $check;
    }


    public function addURLFilterRules($regex_array)
    {
        $cnt = count($regex_array);
        for ($x=0; $x<$cnt; $x++)
        {
            $this->addURLFilterRule($regex_array[$x]);
        }
    }
}
