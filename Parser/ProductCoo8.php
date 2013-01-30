<?php

if (isset($argv[2]) && $argv[1] == 'test') {
	require_once  '../Config/Config.php';
	require_once  APP_HOME . 'Utility/Http.php';
}

require_once APP_HOME . 'Parser/ProductAbstract.php';
require_once APP_HOME . 'Utility/ContentFilter.php';
require_once APP_HOME . 'Crawler/Http.php';

class Parser_ProductCoo8 extends Parser_ProductAbstract {
	private $_shopName = '库巴';
	private $_shopId = 9;
	private $_categoryTree;

	public function parseSummary($document, $productUrl) {
		if (!preg_match('#^http://www\.coo8\.com/product/\d+\.html$#i', $productUrl)) {
			return FALSE;
		}
		$itemList = array();
		if (strpos($document, '<title>出错啦</title>') !== false) {
			return false;
		}
		
		if (preg_match('|product_name:"([^"]+)"|Us', $document, $paragraph)) {
			$itemList['Title'] = $paragraph[1];
		} else if (preg_match('|<h1>(.+)</h1>|Us', $document, $paragraph)) {
			$itemList['Title'] = $paragraph[1];
		} else {
			return false;
		}
		
		if (preg_match('#<div class="crumb" [^>]*>.*</div>#Us', $document, $v)) {
			if (preg_match_all('|<a [^>]*>(.*)</a>|Us', $v[0], $nav)) {
				if ($nav[1][0] == '首页') {
					if (isset($nav[1][3])) {
						$this->_categoryTree[0] = $nav[1][1];
						$this->_categoryTree[1] = $nav[1][2];
						$this->_categoryTree[2] = $nav[1][3];
					} else {
						$this->_categoryTree[0] = '';
						$this->_categoryTree[1] = $nav[1][1];
						$this->_categoryTree[2] = $nav[1][2];
					}
				} else {
					$this->_categoryTree[0] = $nav[1][0];
					$this->_categoryTree[1] = $nav[1][1];
					$this->_categoryTree[2] = $nav[1][2];
				}
			}
		}
		
		if (preg_match('|product_img3:"([^"]+)"|Us', $document, $paragraph)) {
			$itemList['IMAGE_SRC'] = $paragraph[1];
		} else if (preg_match("|showBig\('(http://[^']+)'|Us", $document, $paragraph)) {
			$itemList['IMAGE_SRC'] = $paragraph[1];
		}

		if (preg_match('|product_price:"([^"]+)"|Us', $document, $paragraph)) {
			$itemList['Price'] = $paragraph[1];
		} else if (preg_match('|product_price="([^"]+)"|Us', $document, $paragraph)) {
			$itemList['Price'] = $paragraph[1];
		} else if (preg_match('|库巴价：\s*([\d\.]+)|Us', $document, $paragraph)) {
			$itemList['Price'] = $paragraph[1];
		}

		if (preg_match('|product_brandname:"([^"]+)"|Us', $document, $paragraph)) {
			$itemList['品牌'] = $paragraph[1];
		}

		foreach($itemList as $key => $value) {
			$itemList[$key] = Utility_ContentFilter::filterHtmlTags($value, true);
		}
		return $itemList;
	}
	
	public function parseDetails($document, $productUrl) {
		if (preg_match('|<table cellspacing="0" id="Table">(.+)</table>|Us', $document, $paragraph)) {
			$r = explode("<td colspan='2' class='head'>", $paragraph[1]);
			$itemList = array();
			foreach($r as $item) {
				if (strlen($item) < 1) { continue; }
				$p = strpos($item, "</td>");
				if ($p === false) { continue; }
				$currentCategory = Utility_ContentFilter::filterSpaces(substr($item, 0, $p));
				$det = preg_match_all("|<td class='left'>(.+)</td><td class='right'>(.+)</td>|Usi", $item, $detailMatch);
				for ($i=0; $i<$det; $i++) {
					$detailMatch[1][$i] = Utility_ContentFilter::filterSpaces($detailMatch[1][$i]);
					$itemList[$currentCategory][$detailMatch[1][$i]] = Utility_ContentFilter::filterHtmlTags($detailMatch[2][$i], true);
				}
			}
			return $itemList;
		}
		return array();
	}

	public function parseComments($document, $productUrl) {
		return false;
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
		$xmlData = '<?xml version="1.0" encoding="GB2312"?>';
		$xmlData .= '<Product>';
		$xmlData .= '<' . $this->_categoryTree[0]
				 . '><' . $this->_categoryTree[1]
				 . '><' . $this->_categoryTree[2] . '>';

		$xmlData .= '<来源信息>';
		foreach ($productInfo['fromInfo'] as $key => $value) {
			$line = '<' . $key . '>' . $value . '</' . $key . '>';
			$xmlData .= $line;
		}
		$xmlData .= '</来源信息>';
		
		$xmlData .= '<商品介绍>';
		foreach ($productInfo['summary'] as $key => $value) {
			$line = '<' . $key . '>' . $value . '</' . $key . '>';
			$xmlData .= $line;
		}
		$xmlData .= '</商品介绍>';

		$xmlData .= '<规格参数>';
		foreach ($productInfo['details'] as $key => $value) {
			$section = '<' . $key . '>';
			foreach ($value as $subKey => $subValue) {
				$line = '<' . $subKey . '>' . $subValue . '</' . $subKey . '>';
				$section .= $line;
			}
			$section.= '</' . $key . '>';
			$xmlData .= $section;
		}
		$xmlData .= '</规格参数>';
		if ($comments) {
			$xmlData .= $this->toCommentXml($comments);
		}

		$xmlData .= '</' . $this->_categoryTree[2]
				 . '></' . $this->_categoryTree[1]
				 . '></' . $this->_categoryTree[0] . '>';
		$xmlData .= '</Product>';

		return $xmlData;
	}
	
	public function toCommentXml($comments) {
		return false;
	}
}

$pCoo8 = new Parser_ProductCoo8;
$Parser['#^http://www\.coo8\.com/product/\d+\.html$#i'] = $pCoo8;

if (isset($argv[2]) && $argv[1] == 'test') {
	$pCoo8->test($argv[2]);
}
