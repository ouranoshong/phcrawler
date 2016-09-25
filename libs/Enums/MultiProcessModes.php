<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-9-24
 * Time: 下午11:08
 */

namespace PhCrawler\Enums;


class MultiProcessModes
{
    /**
     * Crawler runs in a single process
     *
     * @var int
     */
    const MPMODE_NONE = 0;

    /**
     * Crawler runs in multiprocess-mode, usercode is executed by parent-process only.
     *
     * @var int
     */
    const MPMODE_PARENT_EXECUTES_USERCODE = 1;

    /**
     * Crawler runs in multiprocess-mode, usercode is executed by child-processes directly.
     *
     * @var int
     */
    const MPMODE_CHILDS_EXECUTES_USERCODE = 2;
}
