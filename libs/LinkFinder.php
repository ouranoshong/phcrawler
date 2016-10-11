<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 10/11/16
 * Time: 12:16 PM
 */

namespace PhCrawler;


use PhCrawler\Descriptors\LinkDescriptor;
use PhCrawler\Descriptors\LinkPartsDescriptor;
use PhCrawler\Http\Response\DocumentInfo;
use PhCrawler\Utils\LinkUtil;

class LinkFinder
{
    /**
     * Numeric array containing all tags to extract links from
     *
     * @var array
     */
    public $extract_tags = array("href", "src", "url", "location", "codebase", "background", "data", "profile", "action", "open");

    public $sourceLink;

    public $baseLinkParts;

    public $top_lines_processed = false;

    public $meta_attributes = [];

    public $aggressive_search = true;


    public function setSourceLink(LinkDescriptor $LinkDescriptor) {
        $this->sourceLink = $LinkDescriptor;
        $this->baseLinkParts = new LinkPartsDescriptor($LinkDescriptor->url_rebuild);
    }

    public function getRedirectLinkInHeader($http_header) {
        // Get redirect-link from header
        preg_match("/((?i)location:|content-location:)(.{0,})[\n]/", $http_header, $match);

        if (isset($match[2]))
        {
            $redirect_link = trim($match[2]);
            return $this->buildLinkDescriptor($redirect_link, "", "", true);
        }
        else return null;
    }

    public function getLinksInContent($html_source) {

        $Links = [];

        // Check for meta-base-URL and meta-tags in top of HTML-source
        if ($this->top_lines_processed == false)
        {
            $meta_base_url = LinkUtil::getBaseUrlFromMetaTag($html_source);

            if ($meta_base_url != null)
            {
                $base_url = LinkUtil::buildURLFromLink($meta_base_url, $this->baseLinkParts);
                $this->baseLinkParts = new LinkPartsDescriptor($base_url);
            }

            // Get all meta-tags
            $this->meta_attributes = LinkUtil::getMetaTagAttributes($html_source);

            // Set flag that top-lines of source were processed
            $this->top_lines_processed = true;
        }


        // Build the RegEx-part for html-tags to search links in
        $tag_regex_part = "";
        $cnt = count($this->extract_tags);
        for ($x=0; $x<$cnt; $x++)
        {
            $tag_regex_part .= "|".$this->extract_tags[$x];
        }
        $tag_regex_part = substr($tag_regex_part, 1);

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

            if (!empty($link_raw)) $Links[] = $this->buildLinkDescriptor($link_raw, $linkcode, $linktext);
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

            if (!empty($link_raw)) $Links[] = $this->buildLinkDescriptor($link_raw, $linkcode, $linktext);
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

                    $Links[] = $this->buildLinkDescriptor($link_raw, $linkcode, $linktext);
                }
            }
        }

        return $Links;
    }

    protected function buildLinkDescriptor($link_raw, $link_code, $link_text = "", $is_redirect_url = false) {

        // Rebuild URL from link
        $url_rebuild = LinkUtil::buildURLFromLink($link_raw, $this->baseLinkParts);

        // If link coulnd't be rebuild
        if ($url_rebuild == null) return;

        // Create an URLDescriptor-object with URL-data
        $url_link_depth = $this->sourceLink->url_link_depth + 1;
        $LinkDescriptor = new LinkDescriptor($url_rebuild, $link_raw, $link_code, $link_text, $this->sourceLink->url_rebuild, $url_link_depth);

        // Is redirect-URL?
        if ($is_redirect_url == true)
            $LinkDescriptor->is_redirect_url = true;

        return $LinkDescriptor;
    }

    public function findInDocumentInfo(DocumentInfo $documentInfo) {

    }

}
