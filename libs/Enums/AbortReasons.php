<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-9-24
 * Time: 下午11:04
 */

namespace PhCrawler\Enums;


interface AbortReasons
{
    /**
     * Crawling-process aborted because everything is done/passedthrough.
     *
     * @var int
     */
    const PASSED_THROUGH = 1;

    /**
     * Crawling-process aborted because the traffic-limit set by user was reached.
     *
     * @var int
     */
    const TRAFFIC_LIMIT_REACHED = 2;

    /**
     * Crawling-process aborted because the filelimit set by user was reached.
     *
     * @var int
     */
    const FILE_LIMIT_REACHED = 3;

    /**
     * Crawling-process aborted because the handleDocumentInfo-method returned a negative value
     *
     * @var int
     */
    const USER_ABORT = 4;
}
