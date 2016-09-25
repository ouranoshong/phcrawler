<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-9-24
 * Time: 下午11:07
 */

namespace PhCrawler\Enums;


interface LinkSearchDocumentSections
{
    /**
     * Script-parts of html-documents (<script>...</script>)
     */
    const SCRIPT_SECTIONS = 1;

    /**
     * HTML-comments of html-documents (<!-->...<-->)
     */
    const HTML_COMMENT_SECTIONS = 2;

    /**
     * Javascript-triggering attributes like onClick, onMouseOver etc.
     */
    const JS_TRIGGERING_SECTIONS = 4;

    /**
     * All of the listed sections
     */
    const ALL_SPECIAL_SECTIONS = 7;
}
