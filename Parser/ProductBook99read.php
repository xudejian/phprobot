<?php

if (isset($argv[2]) && $argv[1] == 'test') {
	require_once  '../Config/Config.php';
	require_once  APP_HOME . 'Utility/Http.php';
}
require_once APP_HOME . 'Parser/ProductAbstract.php';
require_once APP_HOME . 'Utility/ContentFilter.php';
require_once APP_HOME . 'Crawler/Http.php';

class Parser_ProductBook99read extends Parser_ProductAbstract
{
	private $_shopName = '久久书城';
	private $_shopId = 18;

	private $_categoryTree;

	public function __construct($categoryTree)
	{
		$this->_categoryTree = $categoryTree;
	}

	public function parseSummary($document)
	{
		$document = iconv("UTF-8", "GB18030//IGNORE", $document);
		$itemList = array();
		if (preg_match('|<img id="ImageShow" src="([^\"]+)"|', $document, $paragraph)) {
			#$itemList['smallImg'] = $this->_saveProductImage($url);
			if (strpos($paragraph[1], 'nobook-') === false) {
				$itemList['IMAGE_SRC'] = $paragraph[1];
			}
		}
		if (preg_match('|<td class="main-name">([^<]+)</td>|', $document, $paragraph)) {
			$itemList['Title'] = trim($paragraph[1]);
			$itemList['商品名称'] = trim($paragraph[1]);// 目的是为了让格式和电子产品库保持一致
		} else {
			return false;
		}
		if (preg_match('|【99价】：<span [^\>]+>([.0-9]*)</span>元</div>|', $document, $paragraph)) {
			$itemList['Price'] = $paragraph[1];
		}
		if (strpos($document, '<img src="images/zanshiquehuo.gif" id="IMG1"') !== false) {
			$itemList['STOCK'] = 0;
		} else {
			$itemList['STOCK'] = 1;
		}
		if (preg_match('|<td class="main-name">.*<!-- 基本信息 end-->|Ums', $document, $paragraph)) {
			if (preg_match('|【作者】：</span>.*<a[^\>]*>([^\<]+)</a>|Us', $paragraph[0], $match)) {
				$itemList['作者'] = trim($match[1]);
			}
			if (preg_match('|【I  S  B  N】：</span><span class="main-property-content">(.+)</span>|Us', $paragraph[0], $match)) {
				$itemList['isbn'] = trim($match[1]);
				$itemList['商品毛重'] = trim($match[1]);// 目的是为了让格式和电子产品库保持一致
			}
			if (preg_match('|【出版社】：</span>.*<span[^\>]*>([^\<]+)<|Ums', $paragraph[0], $match)) {
				$itemList['出版社'] = trim($match[1]);
				$itemList['品牌'] = trim($match[1]);// 目的是为了让格式和电子产品库保持一致
				$itemList['生产厂家'] = trim($match[1]);// 目的是为了让格式和电子产品库保持一致
			}
			$itemList['商品产地'] = '';// 目的是为了让格式和电子产品库保持一致
			if (preg_match('|【出版日期】：</span>[^>]*>([^\<]+)<|Us', $paragraph[0], $match)) {
				$itemList['出版时间'] = $match[1];
				$itemList['上架时间'] = $match[1];// 目的是为了让格式和电子产品库保持一致
				if (preg_match('|第(\d+)版|', $paragraph[0], $match)) {
					$itemList['版次'] = $match[1];
				}
				if (preg_match('|第(\d+)次|', $paragraph[0], $match)) {
					$itemList['印次'] = $match[1];
				}
			}
			if (preg_match('|【总 页 数】：<span class="main-property-content">(\d+)|Us', $paragraph[0], $match)) {
				$itemList['页数'] = $match[1];
			}
			if (preg_match('|【装　　帧】：</span><span class="main-property-content">([^<]+)<|Us', $paragraph[0], $match)) {
				$itemList['包装'] = $match[1];
			}
			if (preg_match('|【开　　本】：</span><span class="main-property-content">([^<]+)<|Us', $paragraph[0], $match)) {
				$itemList['开本'] = $match[1];
			}
		}
		foreach($itemList as $key => $value) {
			$itemList[$key] = Utility_ContentFilter::filterHtmlTags($value);
		}
		return $itemList;
	}
	private function _saveProductImage($imgUrl)
	{
		$imgName = basename($imgUrl);
		$savePath = '../images/s' . $this->_shopId . '/product/' . substr($imgName, 0, 4);
		if (!is_dir($savePath)) {
			@mkdir($savePath, 0744, true);
		}
		$saveFullName = $savePath . '/' . $imgName;
		// not save again
		if (!is_file($saveFullName)) {
			$imgContent = Crawler_Http::getHttpFileContent($imgUrl);
			file_put_contents($saveFullName, $imgContent);
		}
		return $saveFullName;
	}
	private function _getProductPrice($priceUrl)
	{
		$priceContent = Crawler_Http::getHttpFileContent($priceUrl);
		if (preg_match('/FFE5([\d\.]+)",/', $priceContent, $paragraph)) {
			return $paragraph[1];
		} else {
			return 0;
		}
	}
	public function parseDetails($document)
	{
		return array();
	}

	public function parseComments($document)
	{
		$itemList = array();
		#Updater(escape("/AjaxControls/ProductComentList"), "dProductCommentList",null,{ name:"prd", value:'884451' });
		if (preg_match('|Updater\(escape\("/AjaxControls/ProductComentList"\), "dProductCommentList",null,\{ name:"prd", value:\'(\d+)\'|', $document, $v)) {
			$document = Crawler_Http::getHttpFileContent('http://www.99read.com/AjaxHelper/AjaxHelper.aspx?AjaxTemplate=/AjaxControls/ProductComentList&prd='.$v[1]);
			$document = iconv("UTF-8", "GB18030//IGNORE", $document);
			$c = preg_match_all('|<table class="comment-item"[^>]*>.*<a [^>]* class="comment-title">(.*)</a>.*<span class="comment-author">.*<a class="red_link" href="http://club\.99read\.com/my/UserIndex\.aspx\?mid=.*">(.+)</a>(.*)发表</span>.*<span class="comment-content">(.*)</span>|Us', $document, $matches);
			for ($i=0; $i<$c; $i++) {
				$itemList[] = array(
					'URL' => 'http://www.99read.com/AjaxHelper/AjaxHelper.aspx?AjaxTemplate=/AjaxControls/ProductComentList&prd='.$v[1],
					'USERNAME' => Utility_ContentFilter::filterHtmlTags($matches[2][$i], true),
					'TITLE' => Utility_ContentFilter::filterHtmlTags($matches[1][$i], true),
					'SUMMARY' => Utility_ContentFilter::filterHtmlTags($matches[4][$i], true),
					'POST_TIME' => Utility_ContentFilter::filterHtmlTags($matches[3][$i], true)
					);
			}
		}
		return $itemList;
	}
	public function parseFromInfo ($document, $productUrl)
	{
		$id = '0';
		$components = parse_url($productUrl);
		#http://99read.com/product/detail.aspx?proid=872336&20110304-99SY-XPDH
		if (preg_match('|proid=([^&]+)|', $components['query'], $matches)) {
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
