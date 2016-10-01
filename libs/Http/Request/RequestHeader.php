<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-10-1
 * Time: 下午4:00
 */

namespace PhCrawler\Http\Request;


use PhCrawler\Http\Contracts\handleRequestField;
use PhCrawler\Http\Contracts\RequestField;

class RequestHeader implements RequestField
{
    use handleRequestField;

}
