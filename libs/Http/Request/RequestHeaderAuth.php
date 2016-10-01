<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 16-10-1
 * Time: 下午4:21
 */

namespace PhCrawler\Http\Request;


class RequestHeaderAuth extends RequestHeader
{
    public $name = 'Authorization';
    protected $username;
    protected $password;

    public function __construct($username, $password)
    {
        $this->username = $username;
        $this->password = $password;

        $this->setAuthValue();
    }

    protected function setAuthValue() {
        $this->value = 'Basic '. base64_encode($this->username . ":" . $this->password);
    }

}
