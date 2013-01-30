<?php

if (isset($argv[2]) && $argv[1] == 'test') {
	require_once  '../Config/Config.php';
	require_once  APP_HOME . 'Utility/Http.php';
}

require_once APP_HOME . 'Parser/ProductAbstract.php';
require_once APP_HOME . 'Utility/ContentFilter.php';
require_once APP_HOME . 'Crawler/Http.php';

class Parser_ProductNewegg extends Parser_ProductAbstract {
	private $_shopName = '新蛋网';
	private $_shopId = 8;
	private $_categoryTree;

	public function parseSummary($document, $productUrl) {
		if (!preg_match('#^http://www\.newegg\.com\.cn/Product/.+\.htm$#i', $productUrl)) {
			return false;
		}
		
		$itemList = array();
		if (strpos($document, '<title>错误消息:该产品不存在') !== false) {
			return false;
		}
		
		if (preg_match('|<h1[^>]*>(.+)</h1>|', $document, $paragraph)) {
			$itemList['Title'] = $paragraph[1];
		} else {
			return false;
		}
		
		if (preg_match('#<div id="crumb" class="crumb">.*</div>#Us', $document, $v)) {
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
				 
				foreach($this->_categoryTree as $key => $value) {
					$this->_categoryTree[$key] = str_replace('&#12289;','、',Utility_ContentFilter::filterHtmlTags($value, true));
				}
			}
		}
		
		if (preg_match('|src340="([^\"]+)"|', $document, $paragraph)) {
			#$itemList['smallImg'] = $paragraph[1];
			$itemList['IMAGE_SRC'] = $paragraph[1];
		}
		$price = $this->_getProductPrice($document);
		if (intval( $price ) > 0) {
			$itemList['Price'] = $price;
		}

		if (preg_match('|<a title="([^"]+)" href="[^"]+"><img alt="([^"]+)"|', $document, $paragraph)) {
			if ($paragraph[1] == $paragraph[2]) {
				$itemList['品牌'] = $paragraph[1];
			}
		}

		if (preg_match('|<dd>\s*产品型号：\s*<span>(.*)</span>\s*</dd>|Us', $document, $paragraph)) {
			$itemList['产品型号'] = $paragraph[1];
		}

		if (isset($itemList['价格举报'])) {
			unset($itemList['价格举报']);
		}
		if (isset($itemList['纠错信息'])) {
			unset($itemList['纠错信息']);
		}
		foreach($itemList as $key => $value) {
			$itemList[$key] = Utility_ContentFilter::filterHtmlTags($value, true);
		}
		return $itemList;
	}
	
	private function _getProductPrice($document) {
		if (preg_match('|href="(http://www.newegg.com.cn/Product/[^/]+/Advisory.htm)"|Us', $document, $paragraph)) {
			$priceContent = Crawler_Http::getHttpFileContent($paragraph[1]);
			if (preg_match('|<span id="instanPrice">(.+)</span>|Us', $priceContent, $matches)) {
				return $matches[1];
			} else {
				return 0;
			}
		}
		return 0;
	}
	
	public function parseDetails($document, $productUrl) {
		if (preg_match('|<div style="display: none;" class="proDescTab tabSpec" id="tabCot_product_2"><table width="100%" cellspacing="0" cellpadding="0" border="0"><tr>(.+)</table>|Us', $document, $paragraph)) {
			$r = split('<th colspan="2" class="title">', $paragraph[1]);
			$itemList = array();
			foreach($r as $item) {
				if (strlen($item) < 1) { continue; }
				$p = strpos($item, "</th>");
				if ($p === false) { continue; }
				$currentCategory = Utility_ContentFilter::filterSpaces(substr($item, 0, $p));
				$det = preg_match_all('|<th>(.+)</th><td>(.+)</td>|Us', $item, $detailMatch);
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
		$id = basename($productUrl, '.htm');
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

$pNewegg = new Parser_ProductNewegg;
$Parser['#^http://www\.newegg\.com\.cn/Product/.+\.htm$#i'] = $pNewegg;

if (isset($argv[2]) && $argv[1] == 'test') {
	$pNewegg->test($argv[2]);
}
