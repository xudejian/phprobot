<?php

if (isset($argv[2]) && $argv[1] == 'test') {
	require_once  '../Config/Config.php';
	require_once  APP_HOME . 'Utility/Http.php';
}
require_once APP_HOME . 'Parser/ProductAbstract.php';
require_once APP_HOME . 'Utility/ContentFilter.php';
require_once APP_HOME . 'Crawler/Http.php';

class Parser_ProductBookWinxuan extends Parser_ProductAbstract
{
	private $_shopName = '文轩网';
	private $_shopId = 23;

	private $_categoryTree;

	public function __construct($categoryTree)
	{
		$this->_categoryTree = $categoryTree;
	}

	public function parseSummary($document)
	{
		$p = strpos($document, '<div id="cont_main">');
		if ($p !== false) {
			$document = substr($document, $p);
		}
		$p = strpos($document, '<div class="wd">');
		if ($p !== false) {
			$document = substr($document, 0, $p);
		}
		$p = strpos($document, '<div class="title">');
		if ($p !== false) {
			$document = substr($document, 0, $p);
		}
		$document = iconv("UTF-8", "GB18030//IGNORE", $document);
		$itemList = array();
		if (preg_match('|<img .*src="(.+)"|Us', $document, $paragraph)) {
			#$itemList['smallImg'] = '';
			$itemList['IMAGE_SRC'] = $paragraph[1];
			if (strpos($paragraph[1], '_blank.jpg') !== false) {
				unset($itemList['IMAGE_SRC']);
			}
		}
		if (preg_match('|<h4>(.+)<|Us', $document, $paragraph)) {
			$itemList['Title'] = trim($paragraph[1]);
			$itemList['商品名称'] = trim($paragraph[1]);// 目的是为了让格式和电子产品库保持一致
		} else {
			return null;
		}
		if (preg_match('|<td class="jd">现价：<span class="font_red" >￥(.+)</span>|Us', $document, $paragraph)) {
			$itemList['Price'] = $paragraph[1];
		}
		if (strpos($document, '现在有货') === false) {
			$itemList['STOCK'] = 0;
		} else {
			$itemList['STOCK'] = 1;
		}
		if (preg_match('|ISBN：(.+)<|Us', $document, $match)) {
			$itemList['isbn'] = trim($match[1]);
			$itemList['商品毛重'] = trim($match[1]);
		}
		if (preg_match('|作者：(.+)<|Us', $document, $match)) {
			$itemList['作者'] = trim($match[1]);
		}
		if (preg_match('|出版社：(.+)</|Us', $document, $match)) {
			$itemList['出版社'] = trim($match[1]);
			$itemList['品牌'] = trim($match[1]);// 目的是为了让格式和电子产品库保持一致
			$itemList['生产厂家'] = trim($match[1]);// 目的是为了让格式和电子产品库保持一致
		}
		foreach($itemList as $key => $value) {
			$itemList[$key] = Utility_ContentFilter::filterHtmlTags($value);
		}
		return $itemList;
	}
	public function parseDetails($document)
	{
		$p = strpos($document, '<div id="cont_main">');
		if ($p !== false) {
			$document = substr($document, $p);
		}
		$p = strpos($document, '<div class="wd">');
		if ($p !== false) {
			$document = substr($document, 0, $p);
		}
		$document = iconv("UTF-8", "GB18030//IGNORE", $document);
		$itemList = array();

		$itemList['详细信息'] = array();
		if (preg_match('|<h5>编辑推荐</h5>.*<div class="cont_info">(.+)</div>|Us', $document, $match)) {
			$itemList['详细信息']['编辑推荐'] = Utility_ContentFilter::filterHtmlTags($match[1]);
		}
		if (preg_match('|<h5>内容简介</h5>.*<div class="cont_info">(.+)</div>|Us', $document, $match)) {
			$itemList['详细信息']['内容简介'] = Utility_ContentFilter::filterHtmlTags($match[1]);
		}
		if (preg_match('|<h5>作者简介</h5>.*<div class="cont_info">(.+)</div>|Us', $document, $match)) {
			$itemList['详细信息']['作者简介'] = Utility_ContentFilter::filterHtmlTags($match[1]);
		}
		if (preg_match('|<h5>书摘</h5>.*<div class="cont_info">(.+)</div>|Us', $document, $match)) {
			$itemList['详细信息']['书摘'] = Utility_ContentFilter::filterHtmlTags($match[1]);
		}
		if (preg_match('|<h5>媒体评论</h5>.*<div class="cont_info">(.+)</div>|Us', $document, $match)) {
			$itemList['详细信息']['媒体评论'] = Utility_ContentFilter::filterHtmlTags($match[1]);
		}
		if (preg_match('|<h5>目录</h5>.*<div class="cont_info">(.+)</div>|Us', $document, $match)) {
			$itemList['详细信息']['目录'] = Utility_ContentFilter::filterHtmlTags($match[1]);
		}
		if (count($itemList['详细信息']) < 1) {
			unset($itemList['详细信息']);
		}
		return $itemList;
	}

	public function parseComments($document)
	{
		$p = strpos($document, '<div class="book_pin_body">');
		if ($p !== false) {
			$document = substr($document, $p);
		}
		$p = strpos($document, '<div class="wd">');
		if ($p !== false) {
			$document = substr($document, 0, $p);
		}
		$document = iconv("UTF-8", "GB18030//IGNORE", $document);
		$itemList = array();
		$c = preg_match_all('|<span class="lh24">(.+)</span>.*<span class="f12_b">(.+)</span>.*toDate\(\'(.+)\'\)<ul class="book_pin_txt1">(.+)</ul>|Us', $document, $matches);
		for ($i=0; $i<$c; $i++) {
			$itemList[] = array(
				'USERNAME' => Utility_ContentFilter::filterHtmlTags($matches[1][$i], true),
				'TITLE' => Utility_ContentFilter::filterHtmlTags($matches[2][$i], true),
				'SUMMARY' => Utility_ContentFilter::filterHtmlTags($matches[4][$i], true),
				'POST_TIME' => Utility_ContentFilter::filterHtmlTags($matches[3][$i], true)
			);
		}
		return $itemList;
	}
	public function parseFromInfo ($document, $productUrl)
	{
		$id = '0';
		#http://www.winxuan.com/product/book_1_10550910.html
		if (preg_match('|_(\d+)\.htm|', $productUrl, $matches)) {
			$id = $matches[1];
		}
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
