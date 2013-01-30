<?php

if (isset($argv[2]) && $argv[1] == 'test') {
	require_once  '../Config/Config.php';
	require_once  APP_HOME . 'Utility/Http.php';
}
require_once APP_HOME . 'Parser/ProductAbstract.php';
require_once APP_HOME . 'Utility/ContentFilter.php';
require_once APP_HOME . 'Crawler/Http.php';

class Parser_ProductGome extends Parser_ProductAbstract
{
	private $_shopName = '国美电器';
	private $_shopId = 25;

	private $_categoryTree;

	public function __construct($categoryTree)
	{
		$this->_categoryTree = $categoryTree;
	}

	public function parseSummary($document)
	{
		if (strpos($document, '<img src="http://app.gome.com.cn/images/error/404.jpg"') !== false) {
			return false;
		}
		$p = strpos($document, '<div id="middlecontent">');
		if ($p !== false) {
			$document = substr($document, $p);
		}
		$document = iconv('UTF-8', 'GB18030//IGNORE', $document);
		$itemList = array();

		if (preg_match('|<img src="([^"]+)"|Us', $document, $paragraph)) {
			#$itemList['smallImg'] = $this->_saveProductImage($paragraph[1]);
			$itemList['IMAGE_SRC'] = $paragraph[1];
		}
		if (preg_match('|<h1>([^<]+)<|Us', $document, $paragraph)) {
			$itemList['Title'] = $paragraph[1];
			$itemList['商品名称'] = $paragraph[1];// 目的是为了让格式和电子产品库保持一致
		} else {
			return false;
		}

		$itemList['STOCK'] = 1;
		if (preg_match('|<input id="hidgoodsId" type="hidden" value="([^"]+)"/>|Us', $document, $paragraph)) {
			$id = $paragraph[1];
			$url = 'http://www.gome.com.cn/pro/goods.do?goodsId=' . $id;
			$pcon = Crawler_Http::getHttpFileContent($url);
			$pcon = iconv('UTF-8', 'GB18030//IGNORE', $pcon);
			if (preg_match('|"￥([^"]+)"|Us', $pcon, $paragraph)) {
				$itemList['Price'] = $paragraph[1];
			}
			$url = 'http://www.gome.com.cn/pro/getRegion.do?goodsNo='.$id.'&city=11010000';
			$pcon = Crawler_Http::getHttpFileContent($url);
			if (intval($pcon) === 0) {
				$itemList['STOCK'] = 0;
			}
		}
		if (preg_match('|<td class="tdTitle">品牌</td><td>([^<]+)</td>|Us', $document, $match)) {
			$itemList['品牌'] = trim($match[1]);
		}

		foreach($itemList as $key => $value) {
			$itemList[$key] = Utility_ContentFilter::filterHtmlTags($value);
		}
		return $itemList;
	}
	public function parseDetails($document)
	{
		$itemList = array();
		if (preg_match('|<table cellspacing="1" cellpadding="0" border="0" width="100%" class="Ptable">(.+)</table>|Us', $document, $paragraph)) {
			$document = iconv('UTF-8', 'GB18030//IGNORE', $paragraph[1]);
			$r = explode('<td class="tdTitle" style="width:15%;font-weight:bold;">', $document);
			foreach($r as $item) {
				if (strlen($item) < 1) { continue; }
				$p = strpos($item, "</td>");
				if ($p === false) { continue; }
				$currentCategory = Utility_ContentFilter::filterSpaces(substr($item, 0, $p));
				$det = preg_match_all('|<td class="tdTitle">([^<]+)</td><td>([^<]+)</td>|Usi', $item, $detailMatch);
				for ($i=0; $i<$det; $i++) {
					$detailMatch[1][$i] = Utility_ContentFilter::filterSpaces($detailMatch[1][$i]);
					$itemList[$currentCategory][$detailMatch[1][$i]] = Utility_ContentFilter::filterHtmlTags($detailMatch[2][$i], true);
				}
			}
			return $itemList;
		}
		return $itemList;
	}

	public function parseComments($document)
	{
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
	public function parseFromInfo ($document, $productUrl)
	{
		$id = basename($productUrl, '.html');
		$fromInfo = array(
			'shopName'  => $this->_shopName,
			'shopId'    => $this->_shopId,
			'fromId'    => $id,
			'fromUrl'   => $productUrl,
		);
		return $fromInfo;
	}
	public function toXml($productInfo, $comments=null)
	{
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
	public function toCommentXml($comments)
	{
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
