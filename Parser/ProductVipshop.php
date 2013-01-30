<?php

if (isset($argv[2]) && $argv[1] == 'test') {
	require_once  '../Config/Config.php';
	require_once  APP_HOME . 'Utility/Http.php';
}

require_once APP_HOME . 'Parser/ProductAbstract.php';
require_once APP_HOME . 'Utility/ContentFilter.php';
require_once APP_HOME . 'Crawler/Http.php';

class Parser_ProductVipshop extends Parser_ProductAbstract
{
	private $_shopName = '唯品会';
	private $_shopId = 18;

	private $_categoryTree;

	public function parseSummary($document, $productUrl) {
		$type ='';
		if (preg_match('#^http://www\.vipshop\.com/detail-(.+)\.html$#i', $productUrl)) {
			$type = 'www';
		}else if (preg_match('#^http://lux\.vipshop\.com/detail-(.+)\.html$#i', $productUrl)) {
			$type = 'lux';
		}else {
			return FALSE;
		}
		$document = iconv("UTF-8", "GB18030//IGNORE", $document);
		$itemList = array();
		if($type == 'www'){
			if (preg_match('#<div class="spjbxx_img_big"><div id="div_sale_out"><img src="img/shop/shop_over.gif" /></div><a href="(.+)"#iUs', $document, $paragraph)) {
				$itemList['IMAGE_SRC'] = $paragraph[1];
			}
			if (preg_match('/<div id="spjbxx_text">\s*<h1>(.+)<\/h1>/Us', $document, $paragraph)) {
				$itemList['Title'] = $paragraph[1];
				$itemList['商品名称'] = $paragraph[1];
			}
			if (!isset($itemList['Title'])) {
				return false;
			}
			if (preg_match('#<span class="bor_red"><span class="hyjia">￥(.+)<\/span>#Us', $document, $paragraph)) {
				$itemList['Price'] = $paragraph[1];
			}
			
			if (preg_match('#<p id="ymnav">(.*)<\/p>#Us', $document, $v)) {
				if (preg_match_all('|<a [^>]*>(.*)</a>|Us', $v[0], $nav)) {
						$this->_categoryTree[0] = '';
						$this->_categoryTree[1] = $nav[1][1];					
					
				}
			if (preg_match('#&gt;([^&]*)</p>#Us', $v[0]."</p>", $nav)) {
						$this->_categoryTree[2] = Utility_ContentFilter::filterHtmlTags($nav[1], True);					
					
				}
				
				
			}
			if(preg_match('#产地：([^&]+)&#', $document, $paragraph)) {
					$itemList['产地'] = $paragraph[1];
			} 
			if(preg_match('#品牌：([^&]+)&#', $document, $paragraph)) {
					$itemList['品牌'] = $paragraph[1];
			} 
			
		}elseif ($type == 'lux'){
			if (preg_match('#<div class="show_midpic"><a href="(.+)"#iUs', $document, $paragraph)) {
				$itemList['IMAGE_SRC'] = $paragraph[1];
			}
			if (preg_match('/<h1 class="txt_protit">(.+)<\/h1>/Us', $document, $paragraph)) {
				$itemList['Title'] = $paragraph[1];
				$itemList['商品名称'] = $paragraph[1];
			}
			if (!isset($itemList['Title'])) {
				return false;
			}
			if (preg_match('#<span class="price_border"><em>&yen;(.+)<\/em>#Us', $document, $paragraph)) {
				$itemList['Price'] = $paragraph[1];
			}
			
			if (preg_match('#<div class="position">(.*)<\/div>#Us', $document, $v)) {
				if (preg_match_all('|<a [^>]*>(.*)</a>|Us', $v[0], $nav)) {
						$this->_categoryTree[0] = '';
						$this->_categoryTree[1] = $nav[1][1];					
					
				}
			if (preg_match('#;([^&]*)</div>#Us', $v[0]."</div>", $nav)) {
						$this->_categoryTree[2] = Utility_ContentFilter::filterHtmlTags($nav[1], True);					
					
				}
				
				
			}
			
		}
		if(preg_match('#产地：([^&]+)&#', $document, $paragraph)) {
			$itemList['产地'] = $paragraph[1];
		} 
		if(preg_match('#品牌：([^&]+)&#', $document, $paragraph)) {
			$itemList['品牌'] = $paragraph[1];
		} 

		foreach($itemList as $key => $value) {
			$itemList[$key] = Utility_ContentFilter::filterHtmlTags($value, True);
		}
		return $itemList;
	}
	
	
	public function parseDetails($document, $productUrl)
	{
		$type ='';
		if (preg_match('#^http://www\.vipshop\.com/detail-.*\.html$#i', $productUrl)) {
			$type = 'www';
		}else if (preg_match('#^http://lux\.vipshop\.com/detail-.*\.html$#i', $productUrl)) {
			$type = 'lux';
		}else {
			return FALSE;
		}
		$document = iconv("UTF-8", "GB18030//IGNORE", $document);
		$itemList = array();
		if ($type == 'www'){
			if (preg_match('#<p class="p_bot_bor">(.+)<div class="p_bot_pic">#Us', $document, $paragraph)) {
				$itemList['商品描述'] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$paragraph[1]), true);
			}
		}elseif ($type == 'lux') {
			if (preg_match('#<div class="pro_txt">(.+)<div class="pro_model_pic">#Us', $document, $paragraph)) {
				$itemList['商品描述'] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$paragraph[1]), true);
			}
		}
		
		
		return $itemList;
	}

	public function parseComments($document, $productUrl) {
		return array();
	}
	
	public function parseFromInfo ($document, $productUrl) {
		$id = basename($productUrl, '.html');
		$ids = array();
		if (preg_match('/detail-(.+)-0-0/Us', $id, $ids)){
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

$pVipshop = new Parser_ProductVipshop;
$Parser['#^http://(www|lux)\.vipshop\.com/detail-\d+-0-0\.html$#i'] = $pVipshop;

if (isset($argv[2]) && $argv[1] == 'test') {
	$pVipshop->test($argv[2]);
}