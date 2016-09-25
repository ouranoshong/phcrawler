<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-9-24
 * Time: 下午11:14
 */

namespace PhCrawler;


use PhCrawler\Enums\LinkSearchDocumentSections;
use PhCrawler\UrlCache\Base;
use PhCrawler\UrlCache\Memory;
use PhCrawler\Utils\Utils;

class LinkFinder
{
    /**
     * Numeric array containing all tags to extract links from
     *
     * @var array
     */
    public $extract_tags = array("href", "src", "url", "location", "codebase", "background", "data", "profile", "action", "open");

    /**
     * Specifies whether links will also be searched outside of HTML-tags
     *
     * @var bool
     */
    public $aggressive_search = true;

    /**
     * Specifies whether redirect-links set in http-headers should get found.
     *
     * @var bool
     */
    public $find_redirect_urls = true;

    /**
     * The URL of the html-source to find links in
     *
     * @var URLDescriptor
     */
    protected $SourceUrl;

    /**
     * Cache for storing found links/urls
     *
     * @var Base
     */
    protected $LinkCache;

    /**
     * Flag indicating whether the top lines of the HTML-source were processed.
     */
    protected $top_lines_processed = false;

    /**
     * Parts of the base-url as PHPCrawlerUrlPartsDescriptor-object
     *
     * @var UrlPartsDescriptor
     */
    protected $baseUrlParts;

    protected $found_links_map = array();

    /**
     * Meta-attributes found in the html-source.
     *
     * @var array
     */
    protected $meta_attributes = array();

    /**
     * Sections of HTML-documents ignorded by the linkfinder.
     *
     * @var int Bitwise combination of LinkSearchDocumentSections-constants
     */
    protected $ignore_document_sections = null;

    public function __construct()
    {
        $this->LinkCache = new Memory();
        $this->LinkCache->url_distinct_property = Base::URLHASH_URL;
    }

    /**
     * Sets the source-URL of the document to find links in
     *
     * @param URLDescriptor $SourceUrl
     */
    public function setSourceUrl(URLDescriptor $SourceUrl)
    {
        $this->SourceUrl = $SourceUrl;
        $this->baseUrlParts = UrlPartsDescriptor::fromURL($SourceUrl->url_rebuild);
    }

    /**
     * Processes the response-header of the document.
     *
     * @param &string $header The response-header of the document.
     */
    public function processHTTPHeader(&$header)
    {
        if ($this->find_redirect_urls == true)
        {
            $this->findRedirectLinkInHeader($header);
        }
    }

    /**
     * Resets/clears the internal link-cache.
     */
    public function resetLinkCache()
    {
        $this->LinkCache->clear();
        $this->top_lines_processed = false;
        $this->meta_attributes = array();
    }

    
    protected function findRedirectLinkInHeader(&$http_header)
    {
        Benchmark::start("checking_for_redirect_link");

        // Get redirect-URL or link from header
        $redirect_link = Utils::getRedirectURLFromHeader($http_header);

        // Add redirect-URL to linkcache
        if ($redirect_link != null)
        {
            $this->addLinkToCache($redirect_link, "", "", true);
        }

        Benchmark::stop("checking_for_redirect_link");
    }


    public function findLinksInHTMLChunk(&$html_source)
    {
        Benchmark::start("searching_for_links_in_page");

        // Check for meta-base-URL and meta-tags in top of HTML-source
        if ($this->top_lines_processed == false)
        {
            $meta_base_url = Utils::getBaseUrlFromMetaTag($html_source);
            if ($meta_base_url != null)
            {
                $base_url = Utils::buildURLFromLink($meta_base_url, $this->baseUrlParts);
                $this->baseUrlParts = UrlPartsDescriptor::fromURL($base_url);
            }

            // Get all meta-tags
            $this->meta_attributes = Utils::getMetaTagAttributes($html_source);

            // Set flag that top-lines of source were processed
            $this->top_lines_processed = true;
        }

        // Prepare HTML-chunk
        $this->prepareHTMLChunk($html_source);

        // Build the RegEx-part for html-tags to search links in
        $tag_regex_part = "";
        $cnt = count($this->extract_tags);
        for ($x=0; $x<$cnt; $x++)
        {
            $tag_regex_part .= "|".$this->extract_tags[$x];
        }
        $tag_regex_part = substr($tag_regex_part, 1);

        // 1. <a href="...">LINKTEXT</a> (well formed link with </a> at the end and quotes around the link)
        // Get the link AND the linktext from these tags
        // This has to be done FIRST !!
        preg_match_all("#<\s*a\s[^<>]*(?<=\s)(?:".$tag_regex_part.")\s*=\s*".
            "(?|\"([^\"]+)\"|'([^']+)'|([^\s><'\"]+))[^<>]*>".
            "((?:(?!<\s*\/a\s*>).){0,500})".
            "<\s*\/a\s*># is", $html_source, $matches);

        $cnt = count($matches[0]);
        for ($x=0; $x<$cnt; $x++)
        {
            $link_raw = trim($matches[1][$x]);
            $linktext = $matches[2][$x];
            $linkcode = trim($matches[0][$x]);

            if (!empty($link_raw)) $this->addLinkToCache($link_raw, $linkcode, $linktext);
        }

        // Second regex (everything that could be a link inside of <>-tags)
        preg_match_all("#<[^<>]*\s(?:".$tag_regex_part.")\s*=\s*".
            "(?|\"([^\"]+)\"|'([^']+)'|([^\s><'\"]+))[^<>]*># is", $html_source, $matches);

        $cnt = count($matches[0]);
        for ($x=0; $x<$cnt; $x++)
        {
            $link_raw = trim($matches[1][$x]);
            $linktext = "";
            $linkcode = trim($matches[0][$x]);

            if (!empty($link_raw)) $this->addLinkToCache($link_raw, $linkcode, $linktext);
        }

        // Now, if agressive_mode is set to true, we look for some
        // other things
        $pregs = array();
        if ($this->aggressive_search == true)
        {
            // Links like "...:url("animage.gif")..."
            $pregs[]="/[\s\.:;](?:".$tag_regex_part.")\s*\(\s*([\"|']{0,1})([^\"'\) ]{1,500})['\"\)]/ is";

            // Everything like "...href="bla.html"..." with qoutes
            $pregs[]="/[\s\.:;\"'](?:".$tag_regex_part.")\s*=\s*([\"|'])(.{0,500}?)\\1/ is";

            // Everything like "...href=bla.html..." without qoutes
            $pregs[]="/[\s\.:;](?:".$tag_regex_part.")\s*(=)\s*([^\s\">']{1,500})/ is";

            for ($x=0; $x<count($pregs); $x++)
            {
                unset($matches);
                preg_match_all($pregs[$x], $html_source, $matches);

                $cnt = count($matches[0]);
                for ($y=0; $y<$cnt; $y++)
                {
                    $link_raw = trim($matches[2][$y]);
                    $linkcode = trim($matches[0][$y]);
                    $linktext = "";

                    $this->addLinkToCache($link_raw, $linkcode, $linktext);
                }
            }
        }

        $this->found_links_map = array();

        Benchmark::stop("searching_for_links_in_page");
    }

    protected function prepareHTMLChunk(&$html_source)
    {
        // WARNING:
        // When modifying, test thhe following regexes on a huge page for preg_replace segfaults.
        // Be sure to set the preg-groups to "non-capture" (?:...)!

        // Replace <script>-sections from source, but only those without src in it.
        if ($this->ignore_document_sections & LinkSearchDocumentSections::SCRIPT_SECTIONS)
        {
            $html_source = preg_replace("#<script(?:(?!src).)*>.*(?:<\/script>|$)# Uis", "", $html_source);
            $html_source = preg_replace("#^(?:(?!<script).)*<\/script># Uis", "", $html_source);
        }

        // Replace HTML-comments from source
        if ($this->ignore_document_sections & LinkSearchDocumentSections::HTML_COMMENT_SECTIONS)
        {
            $html_source = preg_replace("#<\!--.*(?:-->|$)# Uis", "", $html_source);
            $html_source = preg_replace("#^(?:(?!<\!--).)*--># Uis", "", $html_source);
        }

        // Replace javascript-triggering attributes
        if ($this->ignore_document_sections & LinkSearchDocumentSections::JS_TRIGGERING_SECTIONS)
        {
            $html_source = preg_replace("#on[a-z]+\s*=\s*(?|\"([^\"]+)\"|'([^']+)'|([^\s><'\"]+))# Uis", "", $html_source);
        }
    }

    /**
     * Adds a link to the LinkFinder-internal link-cache
     *
     * @param string $link_raw        The link like it was found
     * @param string $link_code       The html-code of the link like it was found (i.e. <a href="the_link.html">Link</a>)
     * @param string $link_text       The linktext like it was found
     * @param bool   $is_redirect_url Flag indicatin whether the found URL is target of an HTTP-redirect
     */
    protected function addLinkToCache($link_raw, $link_code, $link_text = "", $is_redirect_url = false)
    {
        //Benchmark::start("preparing_link_for_cache");

        // If liks already was found and processed -> skip this link
        if (isset($this->found_links_map[$link_raw])) return;

        // Rebuild URL from link
        $url_rebuild = Utils::buildURLFromLink($link_raw, $this->baseUrlParts);

        // If link coulnd't be rebuild
        if ($url_rebuild == null) return;

        // Create an URLDescriptor-object with URL-data
        $url_link_depth = $this->SourceUrl->url_link_depth + 1;
        $UrlDescriptor = new URLDescriptor($url_rebuild, $link_raw, $link_code, $link_text, $this->SourceUrl->url_rebuild, $url_link_depth);

        // Is redirect-URL?
        if ($is_redirect_url == true)
            $UrlDescriptor->is_redirect_url = true;

        // Add the URLDescriptor-object to LinkCache
        $this->LinkCache->addURL($UrlDescriptor);

        // Add the URLDescriptor-object to found-links-array
        $map_key = $link_raw;
        $this->found_links_map[$map_key] = true;

        //Benchmark::stop("preparing_link_for_cache");
    }

    /**
     * Returns all URLs/links found so far in the document.
     *
     * @return array Numeric array containing all URLs as URLDescriptor-objects
     */
    public function getAllURLs()
    {
        return $this->LinkCache->getAllURLs();
    }

    /**
     * Returns all meta-tag attributes found so far in the document.
     *
     * @return array Assoziative array conatining all found meta-attributes.
     *               The keys are the meta-names, the values the content of the attributes.
     *               (like $tags["robots"] = "nofollow")
     *
     */
    public function getAllMetaAttributes()
    {
        return $this->meta_attributes;
    }

    
    public function excludeLinkSearchDocumentSections($document_sections)
    {
        return $this->ignore_document_sections = $document_sections;
    }
}
