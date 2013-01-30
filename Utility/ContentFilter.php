<?php

class Utility_ContentFilter
{
    public static function filterHtmlTags($content, $flag = false)
    {
        $regExpr = '/<[^>]*>/';
        $filteredContent = preg_replace($regExpr, '', $content);
        if ($flag) {
                $filteredContent = Utility_ContentFilter::filterSpaces($filteredContent);
        }
        return $filteredContent;
    }

    public static function filterSpaces($content)
    {
        return trim(preg_replace('/[ \s]+/', ' ', $content));
    }
}
