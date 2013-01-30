<?php

if (isset($argv[2]) && $argv[1] == 'test') {
	require_once  '../Config/Config.php';
	require_once  APP_HOME . 'Utility/Http.php';
}

require_once APP_HOME . 'Parser/ProductAbstract.php';
require_once APP_HOME . 'Utility/ContentFilter.php';
require_once APP_HOME . 'Crawler/Http.php';

class Parser_ProductMoonbasa extends Parser_ProductAbstract
{
	private $_shopName = '梦芭莎';
	private $_shopId = 22;

	private $_categoryTree;

	public function parseSummary($document, $productUrl) {
		$type ='';
		if (preg_match('#^http://(www|lady|lingerie)\.moonbasa\.com/p-\d+\.html$#i', $productUrl)) {
		
		}else {
			return FALSE;
		}
		$document = iconv("UTF-8", "GB18030//IGNORE", $document);
		$itemList = array();
		
		if (preg_match('#<div class="jqzoom">\s*<img id="bigimg" src="(.+)"#iUs', $document, $paragraph)) {
			$itemList['IMAGE_SRC'] = $paragraph[1];
		}
		if (preg_match('#<div class="p_info">\s*<h2>\s*(.+)<a#Us', $document, $paragraph)) {
			$itemList['Title'] = $paragraph[1];
			$itemList['商品名称'] = $paragraph[1];
		}
		if (!isset($itemList['Title'])) {
			return false;
		}
		if (preg_match('#<b class="detailprice">￥(.+)<\/b>#Us', $document, $paragraph)) {
			$itemList['Price'] = $paragraph[1];
		}
		
		if (preg_match('#<div class="this_page">(.*)<\/div>#Us', $document, $v)) {
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
		}elseif (preg_match('#<div class="tm_now">(.*)<\/div>#Us', $document, $v)){
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

		foreach($itemList as $key => $value) {
			$itemList[$key] = Utility_ContentFilter::filterHtmlTags($value, True);
		}
		return $itemList;
	}
	
	
	public function parseDetails($document, $productUrl)
	{
		$document = iconv("UTF-8", "GB18030//IGNORE", $document);
		$itemList = array();
		if (preg_match('#<div class="new_div" style="">(.+)<\/div>#Us', $document, $paragraph)) {
			$itemList['商品描述'] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$paragraph[1]), true);
		}elseif (preg_match('#<div class="pro_info">(.+)<\/li>\s*<\/ul>\s*<dl>#Us', $document, $paragraph)){//lady
			$itemList['商品描述'] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$paragraph[1]), true);
		}elseif (preg_match('# <div class="SizeReport" style="width:780px;overflow:hidden;">.*<div class="clear"></div>\b*<dl><dt><b>(.+)<div id="divProBigImages" class="wzbox">#Us', $document, $paragraph)){
			$itemList['商品描述'] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$paragraph[1]), true);
		}elseif (preg_match('#<div class="wzbox">(.+)<div id="divProBigImages" class="wzbox">#Us', $document, $paragraph)){
			$itemList['商品描述'] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$paragraph[1]), true);
		}
		return $itemList;
	}

	public function parseComments($document, $productUrl) {
		return array();
	}
	
	public function parseFromInfo ($document, $productUrl) {
		$id = basename($productUrl, '.html');
		$id = str_replace('p-','',$id );
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
$pMoonbasa = new Parser_ProductMoonbasa();
$Parser['#^http://www\.moonbasa\.com/p-\d+\.html$#i'] = $pMoonbasa;
$Parser['#^http://lady\.moonbasa\.com/p-\d+\.html$#i'] = $pMoonbasa;
$Parser['#^http://lingerie\.moonbasa\.com/p-\d+\.html$#i'] = $pMoonbasa;
if (isset($argv[2]) && $argv[1] == 'test') {
	$pMoonbasa->test($argv[2]);
}