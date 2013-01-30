<?php

if (isset($argv[2]) && $argv[1] == 'test') {
	require_once  '../Config/Config.php';
	require_once  APP_HOME . 'Utility/Http.php';
}
require_once APP_HOME . 'Parser/ProductAbstract.php';
require_once APP_HOME . 'Utility/ContentFilter.php';
require_once APP_HOME . 'Crawler/Http.php';

class Parser_ProductBookBookschina extends Parser_ProductAbstract
{
	private $_shopName = '中国图书网';
	private $_shopId = 22;

	private $_categoryTree;

	public function __construct($categoryTree)
	{
		$this->_categoryTree = $categoryTree;
	}

	public function parseSummary($document)
	{
		$itemList = array();
		if (preg_match('|<img src="(http://.+)" alt="(.+)"|Us', $document, $paragraph)) {
			$itemList['Title'] = trim($paragraph[2]);
			$itemList['商品名称'] = trim($paragraph[2]);// 目的是为了让格式和电子产品库保持一致
			#$itemList['smallImg'] = '';
			$itemList['IMAGE_SRC'] = $paragraph[1];
			if (strpos($paragraph[1], 'nocover.gif') !== false) {
				unset($itemList['IMAGE_SRC']);
			} else if (preg_match('|(http://.+/)s([^/]+)$|Us', $paragraph[1], $p)) {
				$itemList['IMAGE_SRC'] = $p[1] . $p[2];
			}
		} else {
			return false;
		}
		if (preg_match('|三&nbsp;星&nbsp;价：<span class=red>(.+)</span>|Us', $document, $paragraph)) {
			$itemList['Price'] = $paragraph[1];
		}
		if (strpos($document, 'outofstock.gif') !== false) {
			$itemList['STOCK'] = 0;
		} else {
			$itemList['STOCK'] = 1;
		}
		if (preg_match('|I&nbsp;S&nbsp;B&nbsp;N ：</td><td[^>]*>(.+)</td>|Us', $document, $match)) {
			$itemList['isbn'] = trim($match[1]);
			$itemList['商品毛重'] = trim($match[1]);
		}
		if (preg_match('|作&nbsp;&nbsp;&nbsp;&nbsp;者：</td><td[^>]*>(.+)</td>|Us', $document, $match)) {
			$itemList['作者'] = trim($match[1]);
		}
		if (preg_match('|出 版 社：</td><td[^>]*><a[^>]*>(.+)</a>|Ums', $document, $match)) {
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
		$itemList = array();

		$itemList['详细信息'] = array();
		if (preg_match('|<a name=this_intro>.*<div class="section">(.+)</div>|Us', $document, $match)) {
			$itemList['详细信息']['内容简介'] = Utility_ContentFilter::filterHtmlTags($match[1]);
		}
		if (preg_match('|<a name=this_contents>.*<div class="section">(.+)</div>|Us', $document, $match)) {
			$itemList['详细信息']['本书目录'] = Utility_ContentFilter::filterHtmlTags($match[1]);
		}
		if (preg_match('|<a name=this_captor>.*<div class="section">(.+)</div>|Us', $document, $match)) {
			$itemList['详细信息']['文章节选'] = Utility_ContentFilter::filterHtmlTags($match[1]);
		}
		if (preg_match('|<a name=this_author>.*<div class="section">(.+)</div>|Us', $document, $match)) {
			$itemList['详细信息']['作者介绍'] = Utility_ContentFilter::filterHtmlTags($match[1]);
		}
		if (count($itemList['详细信息']) < 1) {
			unset($itemList['详细信息']);
		}
		return $itemList;
	}

	public function parseComments($document)
	{
		$itemList = array();
		if (preg_match('|<a name=book_review>(.+)<p|Us', $document, $p)) {
			$c = preg_match_all('|<b>主题：</b>(.+)　　([\d- :]+).*<br><b>读者：</b>(.+)</font>.*<br>&nbsp;&nbsp;&nbsp;&nbsp;(.+)<div align=left>|Us', $p[1], $matches);
			for ($i=0; $i<$c; $i++) {
				$itemList[] = array(
					'USERNAME' => Utility_ContentFilter::filterHtmlTags($matches[3][$i], true),
					'TITLE' => Utility_ContentFilter::filterHtmlTags($matches[1][$i], true),
					'SUMMARY' => Utility_ContentFilter::filterHtmlTags($matches[4][$i], true),
					'POST_TIME' => Utility_ContentFilter::filterHtmlTags($matches[2][$i], true)
				);
			}
		}
		return $itemList;
	}
	public function parseFromInfo ($document, $productUrl)
	{
		$id = '0';
		#http://www.bookschina.com/4138659.htm
		if (preg_match('|/([^/]+)\.htm|', $productUrl, $matches)) {
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
