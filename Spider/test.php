<?php
require_once "PhpRobot.php";

class TestPhpRobot {
	
	public static function DEBUG_check_url($expect, $url, $base) {
		$u = PhpRobot::parse_url($url, $base);
		$to = PhpRobot::toUrl($u);
		if (strcmp($expect, $to)) {
			echo "FAIL expect:", $expect, " != ", $to, chr(10);
		} else {
			echo "OK ", $expect, chr(10);
		}
	}

	public static function TEST_check_url() {
		self::DEBUG_check_url("http://www.google.cn/", "../../", "http://www.google.cn");
		self::DEBUG_check_url("http://www.google.cn/test", "test", "http://www.google.cn/");
		self::DEBUG_check_url("http://www.google.cn/test", "../test", "http://www.google.cn/");
		self::DEBUG_check_url("http://www.google.cn/test", "./test", "http://www.google.cn");
		self::DEBUG_check_url("http://www.google.cn/test/test2", "../test/test2", "http://www.google.cn");
		self::DEBUG_check_url("http://www.google.cn/test/test2/test3/", "test/test2/test3////", "http://www.google.cn");
		self::DEBUG_check_url("http://www.google.cn/test/", "test/test2/test3/../../", "http://www.google.cn");
		self::DEBUG_check_url("http://www.google.cn/test/test2", "/test/test2", "http://www.google.cn/test2/");
		self::DEBUG_check_url("http://www.google.cn/test/test2", "/test////test2", "http://www.google.cn///test2/");
		self::DEBUG_check_url("http://www.google.cn/test/test2", "/test////test2", "http://www.google.cn////test2/");
		self::DEBUG_check_url("http://www.google.cn/test2", "/test/../../../test2", "http://www.google.cn//..//test2/");
		self::DEBUG_check_url("http://www.google.cn/test2", "/test/../../../test2", "http://www.google.cn/../../../test2/");
		self::DEBUG_check_url("http://www.google.cn/test2", "/test/../../../test2", "http://www.google.cn/test2/../");
		self::DEBUG_check_url("http://www.google.cn/test2", "..//test/.././../test2", "http://www.google.cn/test2/../");
		self::DEBUG_check_url("http://www.google.cn/test2", "..//test/.././../test2", "http://www.google.cn/..");
		self::DEBUG_check_url("http://www.google.cn/test/test2", "/test/test2", "http://www.google.cn/test2////");
		self::DEBUG_check_url("http://www.g.cn/test2/www.baidu.com", "www.baidu.com", "http://www.g.cn/test2////");
		self::DEBUG_check_url("http://www.g.cn/test2/?www.baidu.com", "?www.baidu.com", "http://www.g.cn/test2////");
		self::DEBUG_check_url("http://www.g.cn/test/?www.baidu.com", "/test/?www.baidu.com", "http://www.g.cn/test2////");
		self::DEBUG_check_url("http://www.baidu.com/", "http://www.baidu.com", "http://www.google.cn/test2////");
		self::DEBUG_check_url("ftp://www.baidu.com/", "ftp://www.baidu.com", "http://www.google.cn/test2////");
		self::DEBUG_check_url('http://www.360buy.com/products/737-794-798-0-0-0-0-0-0-0-1-1-3.html', '737-794-798-0-0-0-0-0-0-0-1-1-3.html', 'http://www.360buy.com/products/737-794-798.html');
	}

	public static function DEBUG_url_match($line, $expect) {
		if (preg_match('#^http\://www\.360buy\.com/product/\d+\.html$#i', $line)) {
			if (!$expect) {
				echo 'FAIL 1 expect false:', $line, chr(10);
			} else {
				echo 'OK 1 ', $line, chr(10);
			}
		} else if (preg_match('#^http\://www\.360buy\.com/products/\d+-\d+-\d+-0-0-0-0-0-0-0-1-1-\d+\.html$#i', $line)) {
			if (!$expect) {
				echo 'FAIL 2 expect false:', $line, chr(10);
			} else {
				echo 'OK 2 ', $line, chr(10);
			}
		} else if (preg_match('#^http://(book|mvd)\.360buy\.com/\d+\.html$#i', $line)) {
			if (!$expect) {
				echo 'FAIL 2 expect false:', $line, chr(10);
			} else {
				echo 'OK 2 ', $line, chr(10);
			}
		} else {
			if ($expect) {
				echo 'FAIL, expect true, ', $line, chr(10);
			} else {
				echo 'OK FALSE ', $line, chr(10);
			}
		}
	}

	public static function TEST_url_match() {
		self::DEBUG_url_match('http://www.360buy.com/products/670-729-2603-0-0-47906-0-0-0-0-1-1-1.html', false);
		self::DEBUG_url_match('http://www.360buy.com/products/670-729-2603-10-0-47906-0-0-0-0-1-1-1.html', false);
		self::DEBUG_url_match('http://www.360buy.com/products/670-729-2603-0-0-47906-0-0-0-0-1-1-1.html', false);
		self::DEBUG_url_match('http://www.360buy.com/products/670-729-2603-0-0-0-0-0-0-0-1-1-1.html', true);
		self::DEBUG_url_match('http://www.360buy.com/product/670.html', true);
		self::DEBUG_url_match('http://www.360buy.com/products/670.html', false);
		self::DEBUG_url_match('http://book.360buy.com/19008608.html', true);
		self::DEBUG_url_match('http://mvd.360buy.com/20046857.html', true);
	}
	
	public static function TEST_parse_web() {
		$web = file_get_contents('test.html');
		$urls = PhpRobot::parse_web('http://category.dangdang.com/all/?category_path=01.00.00.00.00.00', $web);
		foreach($urls as $url=>$v) {
			echo $url,chr(10);
		}
	}
	
	public static function TEST_levenshtein() {
		$web = file_get_contents('test.html');
		$urls = array_keys(PhpRobot::parse_web('http://www.360buy.com/products/737-794-798.html', $web));
		$c = count($urls);
		$infos = array();
		for ($i=0; $i<$c; ++$i) {
			$infos[$i] = PhpRobot::urlDiffCount('http://www.360buy.com/products/737-794-798-0-0-0-0-0-0-0-1-1-1.html', $urls[$i]);
		}
		asort($infos, SORT_NUMERIC);
		foreach ($infos as $i=>$v) {
			echo $i, '=>', $urls[$i], ' :', $v, chr(10);
		}
	}

	public static function TEST() {
		self::TEST_check_url();
		self::TEST_url_match();
		self::TEST_parse_web();
		#self::TEST_levenshtein();
	}

}

TestPhpRobot::TEST();

$v = file_put_contents('data/test/test.html', 'hello');
var_dump($v);
