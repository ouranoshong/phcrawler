<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-10-1
 * Time: 下午4:07
 */

namespace PhCrawler\Http\Request;


use PhCrawler\Http\Enums\RequestFieldEnum;

class Request
{
    /**
     * @var  RequestHeaderCookies
     */
    protected $cookies;

    /**
     * @var  RequestHeaderEntities
     */
    protected $entities;

    /**
     * @var
     */
    protected $headers;

    public $firstLine;


    public function addFirstLine($firstLine) {
        $this->firstLine = $firstLine;
    }

    public function addHeader($name, $value) {
        $this->headers[] = new RequestHeader($name, $value);
        return $this;
    }

    public function addHeaderAuthProxy($username, $password) {
        $this->headers[] = new RequestHeaderAuthProxy($username, $password);
    }

    public function addHeaderAuth($username, $password) {
        $this->headers[] = new RequestHeaderAuth($username, $password);
    }

    public function getHeaders() {
        return $this->headers;
    }

    public function addCookie($name, $value) {
        $this->cookies[] = new RequestCookie($name, $value);
        return $this;
    }

    public function addCookies($cookie) {
        foreach((array)$cookie as $name=>$value) {
            $this->addCookie($name, $value);
        }

        return $this;
    }

    public function getHeaderCookies() {
        return $this->cookies;
    }

    public function addEntity($name, $value) {
        $this->entities[] = new RequestEntity($name, $value);
        return $this;
    }

    public function AddEntities($entities) {
        foreach((array)$entities as $name=>$value) {
            $this->addEntity($name, $value);
        }

        return $this;
    }

    public function getHeaderEntities() {
        return $this->entities;
    }

    public function __toString()
    {
        return $this->buildHeader().RequestFieldEnum::SEPARATOR.$this->buildEntityBody();
    }

    public function buildHeader() {
        $sp = RequestFieldEnum::SEPARATOR;

        $headerLines = [$this->firstLine];

        foreach((array)$this->headers as $Header) {
            $headerLines[] = (string)$Header;
        }

        $cookie = count($this->cookies) > 0 ? (string)(new RequestHeaderCookies($this->cookies)) : '';

        return join(
            $sp,
            array_filter(
                array_merge($headerLines, [$cookie])
            )
        ).$sp;
    }

    public function buildEntityBody() {
        return (string)(new RequestHeaderEntities($this->entities)).RequestFieldEnum::SEPARATOR;
    }

}
