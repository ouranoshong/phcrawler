<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-10-1
 * Time: 下午6:09
 */

namespace PhCrawler\Http\Request;

use PhCrawler\Http\Enums\RequestFieldEnum;

class RequestHeaderEntities extends RequestHeader implements RequestFieldEnum
{
    public $name = '';

    public $entities = [];

    public function __construct($entities = [])
    {
        $this->entities = $entities;
        $this->setEntityValue();
    }

    public function setEntityValue() {
        $this->value = '';
        /**@var $entity \PhCrawler\Http\Request\RequestEntity */
        foreach((array)$this->entities as $entity) {
            $this->value .= "-----------------------------10786153015124";
            $this->value .= self::SEPARATOR;
            $this->value .= (string)$entity;
        }

        if ($this->value) {
            $this->value .= "-----------------------------10786153015124";
            $this->value .= self::SEPARATOR;
        }
    }
}
