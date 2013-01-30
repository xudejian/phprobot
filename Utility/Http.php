<?php

class Utility_Http
{
    public static function getFileName($url)
    {
        $components = parse_url($url);
        return $components['path'];
    }
}