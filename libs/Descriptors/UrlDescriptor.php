<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 9/28/16
 * Time: 2:22 PM
 */

namespace PhCrawler\Descriptors;


class UrlDescriptor
{
    /**
     * The complete, full qualified and normalized URL
     *
     * @var string
     */
    public $url_rebuild = null;

    /**
     * The raw link to this URL as it was found in the HTML-source, i.e. "../dunno/index.php"
     */
    public $link_raw = null;

    /**
     * The html-codepart that contained the link to this URL, i.e. "<a href="../foo.html">LINKTEXT</a>"
     */
    public $link_code = null;

    /**
     * The linktext or html-code the link to this URL was layed over.
     */
    public $link_text = null;

    /**
     * The URL of the page that contained the link to the URL described here.
     *
     * @var string
     */
    public $refering_url;

    /**
     * Flag indicating whether this URL was target of an HTTP-redirect.
     *
     * @var string
     */
    public $is_redirect_url = false;

    /**
     * The URL/link-depth of this URL relevant to the entry-URL of the crawling-process
     *
     * @var int
     */
    public $url_link_depth;


    public function __construct($url_rebuild, $link_raw = null, $link_code = null, $link_text = null, $refering_url = null, $url_link_depth = null)
    {
        $this->url_rebuild = $url_rebuild;

        if (!empty($link_raw)) $this->link_raw = $link_raw;
        if (!empty($link_code)) $this->link_code = $link_code;
        if (!empty($link_text)) $this->link_text = $link_text;
        if (!empty($refering_url)) $this->refering_url = $refering_url;
        if ($url_link_depth !== null) $this->url_link_depth = (int)$url_link_depth;
    }

}
