<?php

if (isset($argv[2]) && $argv[1] == 'test') {
	require_once  '../Config/Config.php';
	require_once  APP_HOME . 'Utility/Http.php';
}

require_once APP_HOME . 'Parser/ProductAbstract.php';
require_once APP_HOME . 'Utility/ContentFilter.php';
require_once APP_HOME . 'Crawler/Http.php';

class Parser_ProductLetao extends Parser_ProductAbstract
{
	private $_shopName = '乐淘';
	private $_shopId = 17;

	private $_categoryTree;

	public function parseSummary($document, $productUrl) {
		$type ='';
		if (preg_match('#^http://www\.letao\.com/shoe-.*-\d{9}#i', $productUrl)) {
			
		}else  if (preg_match('#^http://www\.letao\.com/[^/]+/shoe-\d{9}\.html#i', $productUrl)) {
					
		}else{
			return FALSE;
		}
		$document = iconv("UTF-8", "GB18030//IGNORE", $document);
		$itemList = array();
		if (preg_match('#<div id="simgouter"><img.*src="(.+)"#iUs', $document, $paragraph)) {
			$itemList['IMAGE_SRC'] = $paragraph[1];
		}
		
		if (preg_match('#<div id="buyinfo">.*<h1>(.+)<\/h1>#Us', $document, $paragraph)) {
			$itemList['Title'] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$paragraph[1]), true);
			$itemList['商品名称'] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$paragraph[1]), true);
		}
		if (!isset($itemList['Title'])) {
			return false;
		}
		if (preg_match('#<i class="ltprice big">(.+)元<\/i>#Us', $document, $paragraph)) {
			$itemList['Price'] = $paragraph[1];
		}
		
		if (preg_match('#<div id="ltlinknav">(.*)<\/div>#Us', $document, $v)) {
			if (preg_match_all('|<a [^>]*>(.*)</a>|Us', $v[0], $nav)) {
				$this->_categoryTree[0] = '服饰鞋帽';
				$this->_categoryTree[1] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$nav[1][1]), true);
				$this->_categoryTree[2] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$nav[1][2]), true);				
			}
		}
		if(preg_match('#<table id="shoe_property".*>(.*)<\/table>#Us', $document, $p)) {
			if (preg_match_all('|<th>(.*)</th><td>(.*)</td>|Us', $p[0], $nav)) {
				$size =sizeof($nav[1]);
				for($i = 0;$i<$size;$i++){
						if($nav[1][$i]!=''){
							$property_k = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$nav[1][$i]), true);
							$property_v = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$nav[2][$i]), true);
							$itemList[$property_k] = $property_v;
						}
							
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
		if (preg_match('#<table id="shoe_property".*>(.*)<\/table>#Us', $document, $paragraph)) {
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
		if (preg_match('/shoe-.*-(\d{9})/Us', $id, $ids)){
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

$pLetao = new Parser_ProductLetao;
$Parser['#^http://www\.letao\.com/shoe-.*-\d{9}#i'] = $pLetao;
$Parser['#^http://www\.letao\.com/[^/]+/shoe-\d{9}\.html#i'] = $pLetao;
if (isset($argv[2]) && $argv[1] == 'test') {
	$pLetao->test($argv[2]);
}
