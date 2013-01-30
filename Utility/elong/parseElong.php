<?php
require_once  '../../Config/Config.php';
require_once APP_HOME . 'Crawler/Http.php';

$continent_urls = array(
	"1" => "http://trip.elong.com/guide/asia.html",
	"2" => "http://trip.elong.com/guide/europe.html",
	"3" => "http://trip.elong.com/guide/africa.html",
	"4" => "http://trip.elong.com/guide/namerica.html",
	"5" => "http://trip.elong.com/guide/samerica.html",
	"6" => "http://trip.elong.com/guide/oceania.html"
);

$country_id = 8;
$insert_sql = 'insert into xiu_city(id,name,en_name,type,parent_id,continent_id) values';
echo $insert_sql,'(7,"中国","China",2,1,1);', chr(10);
foreach($continent_urls as $continent => $continent_url) {
	$content = Crawler_Http::getHttpFileContent($continent_url);
	$pstart = strpos($content, '<map name="Map" id="Map">');
	$content = substr($content, $pstart);
	$pstart = strpos($content, '</map>');
	$content = substr($content, 0, $pstart);
	$c = preg_match_all('#<!--([^-]+)--><area shape="rect" coords=".*" href="(http://trip.elong.com/([^/]+)/)"#', $content, $matches);
	for ($i=0; $i<$c; $i++) {
		echo $insert_sql, '(',
			$country_id,',',
			'"',addslashes($matches[1][$i]),'",',
			'"',ucwords($matches[3][$i]),'",',
			'2,',$continent,',',
			$continent,
			');',
			chr(10);
		$country_id++;
	}
}
