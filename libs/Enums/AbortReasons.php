<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-9-24
 * Time: 下午11:04
 */

namespace PhCrawler\Enums;


class AbortReasons
{
    /**
     * Crawling-process aborted because everything is done/passedthrough.
     *
     * @var int
     */
    const ABORTREASON_PASSEDTHROUGH = 1;

    /**
     * Crawling-process aborted because the traffic-limit set by user was reached.
     *
     * @var int
     */
    const ABORTREASON_TRAFFICLIMIT_REACHED = 2;

    /**
     * Crawling-process aborted because the filelimit set by user was reached.
     *
     * @var int
     */
    const ABORTREASON_FILELIMIT_REACHED = 3;

    /**
     * Crawling-process aborted because the handleDocumentInfo-method returned a negative value
     *
     * @var int
     */
    const ABORTREASON_USERABORT = 4;
}
