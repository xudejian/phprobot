<?php

if (isset($argv[2]) && $argv[1] == 'test') {
	require_once  '../Config/Config.php';
	require_once  APP_HOME . 'Utility/Http.php';
}

require_once APP_HOME . 'Parser/ProductAbstract.php';
require_once APP_HOME . 'Utility/ContentFilter.php';
require_once APP_HOME . 'Crawler/Http.php';

class Parser_Product51buy extends Parser_ProductAbstract {
	private $_shopName = '易讯网';
	private $_shopId = 10;
	private $_categoryTree;

	public function parseSummary($document, $productUrl) {
		if (!preg_match('#^http://item\.51buy\.com/item-\d+\.html$#i', $productUrl)) {
			return FALSE;
		}
		$itemList = array();
		if (strpos($document, '<title>出错啦</title>') !== false) {
			return false;
		}
		$p = strpos($document, 'var itemInfo =');
		if ($p !== false) {
			$document = substr($document, $p);
		}
		if (preg_match("|name\s*:\s*\'([^\']+)\'|Us", $document, $paragraph)) {
			$itemList['Title'] = $paragraph[1];
		} else if (preg_match('|<div class="property[^>]*>\s*<h1>(.+)</h1>|Us', $document, $paragraph)) {
			$itemList['Title'] = $paragraph[1];
		} else {
			return false;
		}
		
		if (preg_match('#<div class="crumbs"[^>]*>(.*)</div>#Us', $document, $v)) {
			$v[1] = str_replace('> > <', '><', $v[1]);
			if (preg_match_all('|>([^<]+)<|Us', $v[1], $nav)) {
				foreach($nav[1] as $key => $value) {
					$value = trim($value);
					if (empty($value)) {
						unset($nav[1][$key]);
					} else if ($value == '>') {
						unset($nav[1][$key]);
					}
				}
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
		
		if (preg_match('|<img id="PicNormal" src="([^"]+)"|Us', $document, $paragraph)) {
			$itemList['IMAGE_SRC'] = $paragraph[1];
		}

		if (preg_match("|price\s*:\s*\'(\d+)\'|Us", $document, $paragraph)) {
			$itemList['Price'] = floatval($paragraph[1]) / 100;
		} else if (preg_match('|>市场价格：</.*<del>([^<]+)</del>.*节省了(.*)元|Us', $document, $paragraph)) {
			$p = floatval(trim(strip_tags($paragraph[1])));
			$psub = floatval(trim(strip_tags($paragraph[2])));
			$itemList['Price'] = $p - $psub;
		}

		if (preg_match("|p_char_id\s*:\s*\'(.+)\'|Us", $document, $paragraph)) {
			$itemList['model'] = $paragraph[1];
		}

		if (preg_match('|<TD class=name>品牌</TD><TD[^>]*>([^<]+)</TD>|Usi', $document, $paragraph)) {
			$itemList['品牌'] = $paragraph[1];
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
	
	public function parseDetails($document, $productUrl) {
		if (preg_match('|id="myviewB">.*(<TABLE.*</TABLE>)|Usi', $document, $paragraph)) {
			$r = explode('<TD colspan=2 class=title>', $paragraph[1]);
			$itemList = array();
			foreach($r as $item) {
				if (strlen($item) < 1) { continue; }
				$p = strpos($item, "</TD>");
				if ($p === false) { continue; }
				$currentCategory = Utility_ContentFilter::filterSpaces(substr($item, 0, $p));
				$det = preg_match_all('|<TD class=name>(.+)</TD><TD class=desc>(.+)</TD>|Usi', $item, $detailMatch);
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
		$id = trim(basename($productUrl, '.html'), 'item-');
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

$p51buy = new Parser_Product51buy;
$Parser['#^http://item\.51buy\.com/item-\d+\.html$#i'] = $p51buy;

if (isset($argv[2]) && $argv[1] == 'test') {
	$p51buy->test($argv[2]);
}

