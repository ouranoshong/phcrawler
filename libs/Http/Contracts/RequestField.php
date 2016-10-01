<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-10-1
 * Time: 下午5:14
 */

namespace PhCrawler\Http\Contracts;


interface RequestField
{

    /**
     * @name convert Field object into a header string
     * @return string
     */
    public function __toString();
}
