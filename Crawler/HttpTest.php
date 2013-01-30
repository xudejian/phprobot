<?php
include_once "Http.php";

function parse_sitemap($url) {
	$content = Crawler_Http::getHttpFileContent($url);
	if ($content === FALSE) {
		return;
	}
	$c = preg_match_all('#<url>(.+)</url>#iUms', $content, $url);
	for($i=0; $i<$c; ++$i) {
		if (preg_match('#<loc>(http[^<]+)</loc>#iUms', $url[1][$i], $loc)) {
			echo $loc[1], chr(10);
		}
	}
	unset($url);
	$c = preg_match_all('#<sitemap>(.+)</sitemap>#iUms', $content, $sitemap);
	for($i=0; $i<$c; ++$i) {
		if (preg_match('#<loc>(http[^<]+)</loc>#iUms', $sitemap[1][$i], $loc)) {
			parse_sitemap($loc[1]);
			break;
		}
	}
	unset($sitemap);
}

parse_sitemap('http://www.amazon.cn/sitemaps.CN_detail_page_sitemap_desktop_index.xml.gz');
