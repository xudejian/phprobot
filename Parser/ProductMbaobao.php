<?php

if (isset($argv[2]) && $argv[1] == 'test') {
	require_once  '../Config/Config.php';
	require_once  APP_HOME . 'Utility/Http.php';
}

require_once APP_HOME . 'Parser/ProductAbstract.php';
require_once APP_HOME . 'Utility/ContentFilter.php';
require_once APP_HOME . 'Crawler/Http.php';

class Parser_ProductMbaobao extends Parser_ProductAbstract
{
	private $_shopName = '麦包包';
	private $_shopId = 19;

	private $_categoryTree;

	public function parseSummary($document, $productUrl) {
		$type ='';
		if (preg_match('#^http://item\.mbaobao\.com/pshow-\d+\.html#i', $productUrl)) {
			
		}else {
			return FALSE;
		}
		$document = iconv("UTF-8", "GB18030//IGNORE", $document);
		$itemList = array();
		if (preg_match('#<div class="pic-show">\s*<a class="js_goods_image_url.* href="(.+)"#iUs', $document, $paragraph)) {
			$itemList['IMAGE_SRC'] = $paragraph[1];
		}
		if (preg_match('/<h1 class="goods-title">(.+)<\/h1>/Us', $document, $paragraph)) {
			$itemList['Title'] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$paragraph[1]), true);
			$itemList['商品名称'] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$paragraph[1]), true);
		}
		if (!isset($itemList['Title'])) {
			return false;
		}
		if (preg_match('#<li class="goods-mb-price">.*<em>￥</em>(.+)<\/span>#Us', $document, $paragraph)) {
			$itemList['Price'] = $paragraph[1];
		}
		
		if (preg_match('#<div class="g-s-box-body g-s-cate">(.*)<\/div>#Us', $document, $v)) {
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
		if(preg_match('#<div class="goods-property clearfix">\s*<ul>(.*)<\/ul>#Us', $document, $p)) {
			if (preg_match_all('|<li>(.*)</li>|Us', $p[0], $nav)) {
					foreach($nav[1] as $n){
					$pa = explode(':',Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$n), true));
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
		if (preg_match('#<div class="goods-property clearfix">\s*<ul>(.*)<\/ul>#Us', $document, $paragraph)) {
			$itemList['商品描述'] = Utility_ContentFilter::filterHtmlTags(str_replace('&nbsp;','',$paragraph[1]), true);
		}		
		return $itemList;
	}

	public function parseComments($document, $productUrl) {
		return array();
	}
	
	public function parseFromInfo ($document, $productUrl) {
		$id = basename($productUrl, '.html');
		$p = strpos($id, '-');
		if ($p !== FALSE) {
			$id = substr($id, $p+1);
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

$pMbaobao = new Parser_ProductMbaobao;
$Parser['#^http://item\.mbaobao\.com/pshow-\d+\.html#i'] = $pMbaobao;
if (isset($argv[2]) && $argv[1] == 'test') {
	$pMbaobao->test($argv[2]);
}