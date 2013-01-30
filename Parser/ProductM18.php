<?php

if (isset($argv[2]) && $argv[1] == 'test') {
	require_once  '../Config/Config.php';
	require_once  APP_HOME . 'Utility/Http.php';
}

require_once APP_HOME . 'Parser/ProductAbstract.php';
require_once APP_HOME . 'Utility/ContentFilter.php';
require_once APP_HOME . 'Crawler/Http.php';

class Parser_ProductM18 extends Parser_ProductAbstract
{
	private $_shopName = '麦考林';
	private $_shopId = 14;

	private $_categoryTree;

	public function parseSummary($document, $productUrl) {
		$type ='';
		
		if (preg_match('#^http://product\.m18\.com/p-.+#i', $productUrl)) {
			
		}else {
			return FALSE;
		}
		$document = iconv("UTF-8", "GB18030//IGNORE", $document);
		$itemList = array();
		if (preg_match('#<div class="bigpic">\s*<div id="J_zoom">\s*<img.*oriSrc="(.+)"#iUs', $document, $paragraph)) {
			$itemList['IMAGE_SRC'] = $paragraph[1];
		}
		
		if (preg_match('#<div id="styleName".*<h1.*>(.+)<\/div>#Us', $document, $paragraph)) {
			$itemList['Title'] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$paragraph[1]), true);
			$itemList['商品名称'] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$paragraph[1]), true);
		}
		if (!isset($itemList['Title'])) {
			return false;
		}
	if (preg_match('#<span id="stylePrice">(.*)<\/span>#Us', $document, $paragraph)) {
			$itemList['Price'] = $paragraph[1];
		}
		
		
		
		if (preg_match('#<ul class="crumb">(.*)<\/ul>#Us', $document, $v)) {
			if (preg_match_all('|<a [^>]*>(.*)</a>|Us', $v[1], $nav)) {
				if (isset($nav[1][3])) {
						$this->_categoryTree[0] = $nav[1][1];
						$this->_categoryTree[1] = $nav[1][2];
						$this->_categoryTree[2] = $nav[1][3];
					} else {
						$this->_categoryTree[0] = '';
						$this->_categoryTree[1] = $nav[1][1];
						$this->_categoryTree[2] = $nav[1][2];
					}			
			}
		}
		if(preg_match('#<div class="ginfoList c6">(.+)</div>#iUs', $document, $paragraph)) {
			if (preg_match_all('|<li>(.*)<\/li>|Us', $paragraph[1], $nav)) {
				
				foreach($nav[1] as $n){
					
					$pa = explode('：',Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$n), true));
					if(sizeof($pa)==2){
						$itemList[$pa[0]] = $pa[1];
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
		if (preg_match('#<div class="ginfoList c6">(.+)</div>#iUs', $document, $paragraph)) {
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
		if (preg_match('/p-(.+).htm/Us', $id, $ids)){
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

$pM18 = new Parser_ProductM18;
$Parser['#^http://product\.m18\.com/p-.+#i'] = $pM18;
if (isset($argv[2]) && $argv[1] == 'test') {
	$pM18->test($argv[2]);
}
