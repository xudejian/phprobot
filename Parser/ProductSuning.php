<?php
if (isset($argv[2]) && $argv[1] == 'test') {
	require_once  '../Config/Config.php';
	require_once  APP_HOME . 'Utility/Http.php';
}
require_once APP_HOME . 'Parser/ProductAbstract.php';
require_once APP_HOME . 'Utility/ContentFilter.php';
require_once APP_HOME . 'Crawler/Http.php';

class Parser_ProductSuning extends Parser_ProductAbstract
{
	private $_shopName = '苏宁易购';
	private $_shopId = 7;

	private $_categoryTree;

	public function parseSummary($document, $productUrl) {
		if (!preg_match('#^http://www\.suning\.com/emall/prd_10052_10051_-7_\d+_\.html$#i', $productUrl)) {
			return false;
		}
		if (strpos($document, '您访问的页面不存在或者已经被删除！') !== false) {
			return false;
		}
		$p = strpos($document, '<div class="main downtwy">');
		if ($p !== false) {
			$document = substr($document, $p);
		}
		$document = iconv('UTF-8', 'GB18030//IGNORE', $document);
		$itemList = array();

		if (preg_match('|<div class="product_b_image">.*<img src="([^"]+)"|Us', $document, $paragraph)) {
			#$itemList['smallImg'] = $this->_saveProductImage($paragraph[1]);
			$itemList['IMAGE_SRC'] = $paragraph[1];
		}
		
		if (preg_match('|<div class="path">.*</div>|Ums', $document, $v)) {
			if (preg_match_all('|<a [^>]*>(.*)</a>|Us', $v[0], $nav)) {
				if ($nav[1][0] == '首页') {
					$this->_categoryTree[0] = $nav[1][1];
					$this->_categoryTree[1] = $nav[1][2];
					$this->_categoryTree[2] = $nav[1][3];
				} else {
					$this->_categoryTree[0] = $nav[1][0];
					$this->_categoryTree[1] = $nav[1][1];
					$this->_categoryTree[2] = $nav[1][2];
				}
			}
			if (preg_match('|<span>(.+)</span>|Us', $v[0], $nav)) {
				$itemList['Title'] = $paragraph[1];
				$itemList['商品名称'] = $itemList['Title'];
			}
		}
		
		if (preg_match('|<span class="product_title_name">([^<]+)<|Us', $document, $paragraph)) {
			$itemList['Title'] = str_replace('&nbsp;', '', $paragraph[1]);
			$itemList['商品名称'] = $itemList['Title'];
		} else if (preg_match('|<input type="hidden" value="([^"]+)" id="ga_itemDataBean_description_name"/>|Us', $document, $paragraph)) {
			$itemList['Title'] = str_replace('&nbsp;', '', $paragraph[1]);
			$itemList['商品名称'] = $itemList['Title'];
		} else {
			return false;
		}

		if (preg_match('|currPrice:"([^"]+)"|Us', $document, $paragraph)) {
			$itemList['Price'] = $paragraph[1];
		}

		#$itemList['STOCK'] = 1;
		#if (preg_match('|url:"(/webapp/wcs/stores/servlet/SNProductStatusView[^"]+)"+myCityId|Us', $document, $paragraph)) {
		#	$url = 'http://www.suning.com' . $paragraph[1] . '9017';
		#	$pcon = Crawler_Http::getHttpFileContent($url);
		#	if (strpos($pcon, '"productStatus":"2"') !== false) {
		#		$itemList['STOCK'] = 0;
		#	}
		#}
		#if (preg_match('|<td class="tdTitle">品牌</td><td>([^<]+)</td>|Us', $document, $match)) {
		#	$itemList['品牌'] = trim($match[1]);
		#}

		foreach($itemList as $key => $value) {
			$itemList[$key] = Utility_ContentFilter::filterHtmlTags($value);
		}
		return $itemList;
	}

	public function parseDetails($document, $productUrl) {
		$itemList = array();
		$document = iconv('UTF-8', 'GB18030//IGNORE', $document);
		$det = preg_match_all('|<tr[^>]*>\s*<td[^>]*>([^<]+)</td>\s*<td[^>]*>([^<]+)</td>|Usi', $document, $detailMatch);
		for ($i=0; $i<$det; $i++) {
			$detailMatch[1][$i] = trim(Utility_ContentFilter::filterSpaces($detailMatch[1][$i]), '：');
			$itemList['商品参数'][$detailMatch[1][$i]] = Utility_ContentFilter::filterHtmlTags($detailMatch[2][$i], true);
		}
		return $itemList;
	}

	public function parseComments($document, $productUrl) {
		return array();
		$itemList = array();
		$p = strpos($document, '<div class="reviewscont drygincontent">');
		if ($p === false) {
			return $itemList;
		}
		$document = substr($document, $p);
		$document = iconv("UTF-8", "GB18030//IGNORE", $document);
		$c = preg_match_all('#<div class="reviewscont drygincontent">.*<p class="top"><strong class="red01 fs14">([^<]+)</strong><Br />发表时间：(.+)</p>\s*<p class="bottom">(.*)<a#Us', $document, $m);
		for ($i=0; $i<$c; $i++) {
			$itemList[] = array(
				'USERNAME' => Utility_ContentFilter::filterHtmlTags($m[1][$i], true),
				'SUMMARY' => Utility_ContentFilter::filterHtmlTags($m[3][$i], true),
				'POST_TIME' => Utility_ContentFilter::filterHtmlTags($m[2][$i], true)
				);
		}
		return $itemList;
	}
	
	public function parseFromInfo ($document, $productUrl) {
		#prd_10052_10051_-7_298948_.html
		$ids = explode('_', basename($productUrl, '.html'));
		do {
			$id = array_pop($ids);
		}while(!is_numeric($id));
		if (!is_numeric($id)) {
			$id = basename($productUrl, '.html');
		}

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
			foreach ($value as $subKey => $subValue) {
				$line = '<' . $subKey . '>' . $subValue . '</' . $subKey . '>';
				$section .= $line;
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

$pSuning = new Parser_ProductSuning;
$Parser['#^http://www\.suning\.com/emall/prd_10052_10051_-7_\d+_\.html$#i'] = $pSuning;

if (isset($argv[2]) && $argv[1] == 'test') {
	$pSuning->test($argv[2]);
}
