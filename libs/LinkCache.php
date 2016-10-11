<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 10/11/16
 * Time: 3:35 PM
 */

namespace PhCrawler;


use PhCrawler\Descriptors\LinkDescriptor;

class LinkCache
{

    public $queue;
    public $met_map;


    public function __construct()
    {
        $this->queue = new \SplQueue();
        $this->met_map = [];

    }

    public function getNextLink() {
        if ($this->queue->isEmpty()) return null;

        return $this->queue->dequeue();
    }

    public function addLink(LinkDescriptor $linkDescriptor) {
        if (!$this->isMetLinkBefore($linkDescriptor->url_rebuild)) {
            $this->queue->enqueue($linkDescriptor);
        }
    }

    public function isMetLinkBefore($link_raw = '') {
        $map_key = md5($link_raw);

        if (isset($this->met_map[$map_key]) && $this->met_map[$map_key]) {
            return true;
        }

        $this->met_map[$map_key] = true;
        return false;
    }


}
