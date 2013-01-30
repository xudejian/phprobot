<?php

if (isset($argv[2]) && $argv[1] == 'test') {
	require_once  '../Config/Config.php';
	require_once  APP_HOME . 'Utility/Http.php';
}

require_once APP_HOME . 'Parser/ProductAbstract.php';
require_once APP_HOME . 'Utility/ContentFilter.php';
require_once APP_HOME . 'Crawler/Http.php';

class Parser_ProductWomai extends Parser_ProductAbstract
{
	private $_shopName = '中粮我买网';
	private $_shopId = 15;

	private $_categoryTree;

	public function parseSummary($document, $productUrl) {
		$type ='';
		
		if (preg_match('#^http://www\.womai\.com/Product-0-\d+#i', $productUrl)) {
			
		}else {
			return FALSE;
		}
		//$document = iconv("UTF-8", "GB18030//IGNORE", $document);
		$itemList = array();
		if (preg_match('#<div class="detail_r1limg".*<img src="(.+)"#iUs', $document, $paragraph)) {
			$itemList['IMAGE_SRC'] = $paragraph[1];
		}
		
		if (preg_match('#<div class="detail_r1_title">\s*<H1>(.+)<\/H1>#Us', $document, $paragraph)) {
			$itemList['Title'] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$paragraph[1]), true);
			$itemList['商品名称'] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$paragraph[1]), true);
		}
		if (!isset($itemList['Title'])) {
			return false;
		}
		
		$itemList['Price'] = $this->_getProductPrice($productUrl);
		
		
		if (preg_match('#<div class="curlink".*>(.*)<\/div>#Us', $document, $v)) {
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
		if(preg_match('#<div class="detail_r1rtop2">(.+)</div>\s*<div id="ProductBlockDiv">#iUs', $document, $paragraph)) {
			if (preg_match_all('|<div class="detail_r1rtop2t\d{1}.*>(.*)<\/div>|Us', $paragraph[1], $nav)) {
				$size =sizeof($nav[1]);
					for($i = 0;$i<$size;$i++){
							if($nav[1][$i]!=''){
								$pro = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$nav[1][$i]), true);
								$pro = explode(':',$pro);
								if(sizeof($pro)==2){
									$itemList[$pro[0]] = $pro[1];
								}
							}							
				}
			}
		} 
		if(preg_match('#<div class="detail_r4_tittle2">商品参数</div>\s*<div class="detail_r4_box1">\s*<ul class="detail_r4ul">(.+)</ul>#iUs', $document, $paragraph)) {
			if (preg_match_all('|<li class="detail_r4uli1">(.*)<\/li>\s*<li class="detail_r4uli2">(.*)<\/li>|Us', $paragraph[1], $nav)) {
				$size =sizeof($nav[1]);
					for($i = 0;$i<$size;$i++){
							if($nav[1][$i]!=''){
								$nav[1][$i] = str_replace('【','',$nav[1][$i]);
								$nav[1][$i] = str_replace('】：','',$nav[1][$i]);
								$p = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$nav[1][$i]), true);
								$v = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$nav[2][$i]), true);								
								$itemList[$p] = $v;								
							}							
				}
			}
		}
		foreach($itemList as $key => $value) {
			$itemList[$key] = Utility_ContentFilter::filterHtmlTags($value, True);
		}
		return $itemList;
	}
	
	private function _getProductPrice($productUrl) {
		$id = basename($productUrl);
		$ids = array();
		if (preg_match('/Product-0-(\d+).htm/Us', $id, $ids)){
			$id = $ids[1];
		}
		$priceContent = Crawler_Http::getHttpFileContent('http://www.womai.com/Product/ProductPrice.do?id='.$id.'&mid=0'); 
		if (preg_match('#<span id="priceCell">￥(.*)<\/span>#Us', $priceContent , $ids)) {
			return  $ids[1];
		}
		return 0;
	}
	
	public function parseDetails($document, $productUrl)
	{
		//$document = iconv("UTF-8", "GB18030//IGNORE", $document);
		$itemList = array();		
		if (preg_match('#<div class="detail_r4_tittle3">商品描述</div>(.*)<div class="detail_r4_tittle2">#Us', $document, $paragraph)) {
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
		if (preg_match('/Product-0-(\d+).htm/Us', $id, $ids)){
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

$pWomai = new Parser_ProductWomai;
$Parser['#^http://www\.womai\.com/Product-0-\d+#i'] = $pWomai;

if (isset($argv[2]) && $argv[1] == 'test') {
	$pWomai->test($argv[2]);
}
