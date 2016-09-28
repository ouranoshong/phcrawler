<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 9/28/16
 * Time: 1:59 PM
 */

namespace PhCrawler\Http\Utils;


class Url
{
    public function parse($url) {
        // Protokoll der URL hinzuf�gen (da ansonsten parse_url nicht klarkommt)
        if (!preg_match("#^[a-z0-9-]+://# i", $url))
            $url = "http://" . $url;

        $parts = @parse_url($url);

        if (!isset($parts)) return null;

        $protocol = $parts["scheme"]."://";
        $host = (isset($parts["host"]) ? $parts["host"] : "");
        $path = (isset($parts["path"]) ? $parts["path"] : "");
        $query = (isset($parts["query"]) ? "?".$parts["query"] : "");
        $auth_username = (isset($parts["user"]) ? $parts["user"] : "");
        $auth_password = (isset($parts["pass"]) ? $parts["pass"] : "");
        $port = (isset($parts["port"]) ? $parts["port"] : "");

        // Host is case-insensitive
        $host = strtolower($host);

        // File
        preg_match("#^(.*/)([^/]*)$#", $path, $match); // Alles ab dem letzten "/"
        if (isset($match[0]))
        {
            $file = trim($match[2]);
            $path = trim($match[1]);
        }
        else
        {
            $file = "";
        }

        // The domainname from the host
        // Host: www.foo.com -> Domain: foo.com
        $parts = @explode(".", $host);
        if (count($parts) <= 2)
        {
            $domain = $host;
        }
        else if (preg_match("#^[0-9]+$#", str_replace(".", "", $host))) // IP
        {
            $domain = $host;
        }
        else
        {
            $pos = strpos($host, ".");
            $domain = substr($host, $pos+1);
        }

        // DEFAULT VALUES f�r protocol, path, port etc. (wenn noch nicht gesetzt)

        // Wenn Protokoll leer -> Protokoll ist "http://"
        if ($protocol == "") $protocol="http://";

        // Wenn Port leer -> Port setzen auf 80 or 443
        // (abh�ngig vom Protokoll)
        if ($port == "")
        {
            if (strtolower($protocol) == "http://") $port=80;
            if (strtolower($protocol) == "https://") $port=443;
        }

        // Wenn Pfad leet -> Pfad ist "/"
        if ($path=="") $path = "/";

        // R�ckgabe-Array
        $url_parts["protocol"] = $protocol;
        $url_parts["host"] = $host;
        $url_parts["path"] = $path;
        $url_parts["file"] = $file;
        $url_parts["query"] = $query;
        $url_parts["domain"] = $domain;
        $url_parts["port"] = $port;

        $url_parts["auth_username"] = $auth_username;
        $url_parts["auth_password"] = $auth_password;

        return $url_parts;
    }
}
