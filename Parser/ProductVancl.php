<?php

if (isset($argv[2]) && $argv[1] == 'test') {
	require_once  '../Config/Config.php';
	require_once  APP_HOME . 'Utility/Http.php';
}

require_once APP_HOME . 'Parser/ProductAbstract.php';
require_once APP_HOME . 'Utility/ContentFilter.php';
require_once APP_HOME . 'Crawler/Http.php';

class Parser_ProductVancl extends Parser_ProductAbstract
{
	private $_shopName = '凡客';
	private $_shopId = 20;

	private $_categoryTree;

	public function parseSummary($document, $productUrl) {
		$type ='';
		if (preg_match('#^http://item\.vancl\.com/\d+\.html#i', $productUrl)) {
			
		}else {
			return FALSE;
		}
		$document = iconv("UTF-8", "GB18030//IGNORE", $document);
		$itemList = array();
		if (preg_match('#<div class="danpinColCenter">\s*<div class="bigImg" id="vertical">\s*<img id="midimg" src="(.+)"#iUs', $document, $paragraph)) {
			$itemList['IMAGE_SRC'] = $paragraph[1];
		}
		if (preg_match('/<div id="productTitle" class="danpinTitleTab">\s*<h2>(.+)<\/h2>/Us', $document, $paragraph)) {
			$itemList['Title'] = $paragraph[1];
			$itemList['商品名称'] = $paragraph[1];
		}
		if (!isset($itemList['Title'])) {
			return false;
		}
		if (preg_match('#<div class="cuxiaoPrice">\s*售价：<span>￥<strong>(.+)<\/strong>#Us', $document, $paragraph)) {
			$itemList['Price'] = $paragraph[1];
		}
		
		if (preg_match('#<div class="breadNav">(.*)<\/div>#Us', $document, $v)) {
			if (preg_match_all('|<a [^>]*>(.*)</a>|Us', $v[0], $nav)) {
				if ($nav[1][0] == '首页') {
					if (isset($nav[1][3])) {
						$this->_categoryTree[0] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$nav[1][1]), true);
						$this->_categoryTree[1] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$nav[1][2]), true);
						$this->_categoryTree[2] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$nav[1][3]), true);
					} else {
						$this->_categoryTree[0] = '';
						$this->_categoryTree[1] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$nav[1][1]), true);
						$this->_categoryTree[2] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$nav[1][2]), true);
					}
				} else {
						$this->_categoryTree[0] = '';
						$this->_categoryTree[1] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$nav[1][1]), true);
						$this->_categoryTree[2] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$nav[1][2]), true);
				}
			}
		}
		
		foreach($itemList as $key => $value) {
			$itemList[$key] = Utility_ContentFilter::filterHtmlTags($value, True);
		}
		return $itemList;
	}
	
	
	public function parseDetails($document, $productUrl)
	{
		
		$document = iconv("UTF-8", "GB18030//IGNORE", $document);
		$itemList = array();		
		if (preg_match('#<h3>\s*产品描述：\s*<\/h3>(.+)<h3>\s*产品属性：\s*<\/h3>#Us', $document, $paragraph)) {
			$itemList['商品描述'] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$paragraph[1]), true);
		}		
		return $itemList;
	}

	public function parseComments($document, $productUrl) {
		return array();
	}
	
	public function parseFromInfo ($document, $productUrl) {
		$id = basename($productUrl);
		$ids = array();
		if (preg_match('#<span id="productcode">商品编号：(.+)<\/span>#Us', $document, $paragraph)){
			$id = $paragraph[1];
		}else if (preg_match('/(.+).html/Us', $id, $ids)){
			$id = $ids[1];
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

$pVancl = new Parser_ProductVancl;
$Parser['#^http://item\.vancl\.com/\d+\.html#i'] = $pVancl;

if (isset($argv[2]) && $argv[1] == 'test') {
	$pVancl->test($argv[2]);
}