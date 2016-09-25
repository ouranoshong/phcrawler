<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-9-24
 * Time: 下午10:19
 */

namespace PhCrawler\CookieCache;

use PhCrawler\CookieDescriptor;

abstract class Base
{
    /**
     * Adds a cookie to the cookie-cache.
     *
     * @param CookieDescriptor $Cookie The cookie to add.
     *
     * @return
     */
    abstract public function addCookie(CookieDescriptor $Cookie);

    /**
     * Adds a bunch of cookies to the cookie-cache.
     *
     * @param array $cookies  Numeric array conatinin the cookies to add as PHPCrawlerCookieDescriptor-objects
     */
    abstract public function addCookies($cookies);

    /**
     * Returns all cookies from the cache that are adressed to the given URL
     *
     * @param string $target_url The target-URL
     * @return array  Numeric array conatining all matching cookies as PHPCrawlerCookieDescriptor-objects
     */
    abstract public function getCookiesForUrl($target_url);

    /**
     * Do cleanups after the cache is not needed anymore
     */
    abstract public function cleanup();
}
