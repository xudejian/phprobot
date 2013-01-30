<?php

if (isset($argv[2]) && $argv[1] == 'test') {
	require_once  '../Config/Config.php';
	require_once  APP_HOME . 'Utility/Http.php';
}

require_once APP_HOME . 'Parser/ProductAbstract.php';
require_once APP_HOME . 'Utility/ContentFilter.php';
require_once APP_HOME . 'Crawler/Http.php';

class Parser_ProductRedbaby extends Parser_ProductAbstract
{
	private $_shopName = 'Redbaby';
	private $_shopId = 12;

	private $_categoryTree;

	public function parseSummary($document, $productUrl) {
		if (preg_match('#^http://www\.redbaby\.com\.cn/[^/]+/\d+\.html$#i', $productUrl)) {
		} else {
			return FALSE;
		}
		$itemList = array();
		if (preg_match('#jqimg="([^"]+)"#Us', $document, $paragraph)) {
			$itemList['IMAGE_SRC'] = $paragraph[1];
		}
		if (preg_match('#<h1>(.+)</h1>#Us', $document, $paragraph)) {
			$itemList['Title'] = iconv('UTF-8', 'GB18030//IGNORE', $paragraph[1]);
			$itemList['商品名称'] = $itemList['Title'];
		}
		if (!isset($itemList['Title'])) {
			return false;
		}
		
		#var productId = "1182721"
		if (preg_match('#var productId = "([^"]+)"#Us', $document, $paragraph)) {
			$tmp = 'http://plus.redbaby.com.cn/plus/product/getPriceInfo?pId='. $paragraph[1];
			$priceContent = Crawler_Http::getHttpFileContent($tmp);
			if (preg_match("#id='price'>([^<]+)<#Us", $priceContent, $paragraph)) {
				$itemList['Price'] = $paragraph[1];
			}
		}
		
		if (preg_match('#<div class="globalCurrentCrumb">.*</div>#Us', $document, $v)) {
			$v = iconv('UTF-8', 'GB18030//IGNORE', $v[0]);
			if (preg_match_all('|<a [^>]*>(.*)</a>|Us', $v, $nav)) {
				if ($nav[1][0] == '红孩子') {
					$this->_categoryTree[0] = $nav[1][1];
					$this->_categoryTree[1] = $nav[1][2];
					$this->_categoryTree[2] = $nav[1][3];
					if (isset($nav[1][4])) {
						$itemList['品牌'] = $nav[1][4];
					}
				} else {
					$this->_categoryTree[0] = $nav[1][0];
					$this->_categoryTree[1] = $nav[1][1];
					$this->_categoryTree[2] = $nav[1][2];
				}
			}
		}

		foreach($itemList as $key => $value) {
			$itemList[$key] = Utility_ContentFilter::filterHtmlTags($value, True);
		}
		return $itemList;
	}
	
	public function parseDetails($document, $productUrl) {
		$itemList = array();
		if (preg_match('#id="commonBasicInfo">.*<ul[^>]*>.*</ul>#Us', $document, $v)) {
			$v = iconv('UTF-8', 'GB18030//IGNORE', $v[0]);
			$c = preg_match_all('|<li><span>(.+)：</span>(.+)</li>|Us', $v, $nav);
			for ($i = 0; $i < $c; ++$i) {
				$k = Utility_ContentFilter::filterHtmlTags($nav[1][$i], True);
				$itemList[$k] = Utility_ContentFilter::filterHtmlTags($nav[2][$i], True);
			}
		}
		return $itemList;
	}

	public function parseComments($document, $productUrl) {
		return array();
	}
	
	public function parseFromInfo ($document, $productUrl) {
		$id = basename($productUrl, '.html');
		$fromInfo = array(
			'shopName'  => $this->_shopName,
			'shopId'    => $this->_shopId,
			'fromId'    => $id,
			'fromUrl'   => $productUrl,
		);
		return $fromInfo;
	}

	public function toXml($productInfo, $comments=null) {
		$xmlData[] = '<?xml version="1.0" encoding="GB2312"?>';
		$xmlData[] = '<Product>';
		$xmlData[] = '<' . $this->_categoryTree[0]
				 . '><' . $this->_categoryTree[1]
				 . '><' . $this->_categoryTree[2] . '>';

		$xmlData[] = '<来源信息>';
		foreach ($productInfo['fromInfo'] as $key => $value) {
			$line = '<' . $key . '>' . $value . '</' . $key . '>';
			$xmlData[] = $line;
		}
		$xmlData[] = '</来源信息>';
		
		$xmlData[] = '<商品介绍>';
		foreach ($productInfo['summary'] as $key => $value) {
			$line = '<' . $key . '>' . $value . '</' . $key . '>';
			$xmlData[] = $line;
		}
		$xmlData[] = '</商品介绍>';

		$xmlData[] = '<规格参数>';
		foreach ($productInfo['details'] as $key => $value) {
			$section = '<' . $key . '>';
			if (is_array($value)) {
				foreach ($value as $subKey => $subValue) {
					$line = '<' . $subKey . '>' . $subValue . '</' . $subKey . '>';
					$section .= $line;
				}
			} else {
				$section .= $value;
			}
			$section.= '</' . $key . '>';
			$xmlData[] = $section;
		}
		$xmlData[] = '</规格参数>';
		if ($comments) {
			$xmlData[] = $this->toCommentXml($comments);
		}

		$xmlData[] = '</' . $this->_categoryTree[2]
				 . '></' . $this->_categoryTree[1]
				 . '></' . $this->_categoryTree[0] . '>';
		$xmlData[] = '</Product>';

		return $xmlData;
	}
	
	public function toCommentXml($comments) {
		//$xmlData = '< ? xml version="1.0" encoding="GB2312" ? >';
		$xmlData = "\n<COMMENTS>\n";
		foreach ($comments as $comment) {
			$section = "<COMMENT>\n";
			foreach ($comment as $key => $value) {
				$line = "<" . $key . "><![CDATA[" . $value . "]]></" . $key . ">\n";
				$section .= $line;
			}
			$section.= "</COMMENT>\n";
			$xmlData .= $section;
		}
		$xmlData .= "</COMMENTS>\n";
		return $xmlData;
	}
}

$pRedbaby = new Parser_ProductRedbaby;
$Parser['#^http://www\.redbaby\.com\.cn/[^/]+/\d+\.html$#i'] = $pRedbaby;

if (isset($argv[2]) && $argv[1] == 'test') {
	$pRedbaby->test($argv[2]);
}