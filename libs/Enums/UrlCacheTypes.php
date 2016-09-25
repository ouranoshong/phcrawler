<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-9-24
 * Time: 下午11:08
 */

namespace PhCrawler\Enums;


interface UrlCacheTypes
{
    /**
     * URLs get cached in local RAM. Best performance.
     */
    const MEMORY = 1;

    /**
     * URLs get cached in a SQLite-database-file. Recommended for spidering huge websites.
     */
    const SQLITE = 2;
}
