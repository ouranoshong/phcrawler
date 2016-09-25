<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-9-24
 * Time: 下午11:08
 */

namespace PhCrawler\Enums;


class UrlCacheTypes
{
    /**
     * URLs get cached in local RAM. Best performance.
     */
    const URLCACHE_MEMORY = 1;

    /**
     * URLs get cached in a SQLite-database-file. Recommended for spidering huge websites.
     */
    const URLCACHE_SQLITE = 2;
}
