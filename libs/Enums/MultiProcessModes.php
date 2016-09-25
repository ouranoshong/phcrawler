<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-9-24
 * Time: 下午11:08
 */

namespace PhCrawler\Enums;


interface MultiProcessModes
{
    /**
     * Crawler runs in a single process
     *
     * @var int
     */
    const NONE = 0;

    /**
     * Crawler runs in multiprocess-mode, usercode is executed by parent-process only.
     *
     * @var int
     */
    const PARENT_EXECUTES_USER_CODE = 1;

    /**
     * Crawler runs in multiprocess-mode, usercode is executed by child-processes directly.
     *
     * @var int
     */
    const CHILDS_EXECUTES_USER_CODE = 2;
}
