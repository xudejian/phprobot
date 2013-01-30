<?php
if (isset($argv[2]) && $argv[1] == 'test') {
	require_once  '../Config/Config.php';
	require_once  APP_HOME . 'Utility/Http.php';
}
require_once APP_HOME . 'Parser/ProductAbstract.php';
require_once APP_HOME . 'Utility/ContentFilter.php';
require_once APP_HOME . 'Crawler/Http.php';

class Parser_ProductDangdang extends Parser_ProductAbstract
{
	private $_shopName = '当当网';
	private $_shopId = 2;

	private $_categoryTree;

	public function parseSummary($document, $productUrl)
	{
		if (preg_match('#^http://product\.dangdang\.com/product\.aspx\?product_id=\d+$#i', $productUrl)) {
		} else {
			return FALSE;
		}
		$itemList = array();
		
		if (preg_match('|>您现在的位置.*</div>|Ums', $document, $v)) {
			if (preg_match_all('|<a [^>]*>(.*)</a>|Us', $v[0], $nav)) {
				if (!empty($nav[1])) {
					$c = count($nav[1]);
					$i = 0;
					if ($nav[1][0] == '当当网') {
						++$i;
					}
					$j=0;
					for (;$i<$c && $j<3;++$i,++$j) {
						$this->_categoryTree[$j] = $nav[1][$i];
					}
					if ($j<3) for ($i=$j+1;$i<3;++$i) {
						$this->_categoryTree[$i] = $this->_categoryTree[$j];
					}
					if ($this->_categoryTree[0] == '图书') {
						$this->_categoryTree[0] = '图书音像';
					}
				}
			}
		}
		
		if (strpos($document, '<script type="text/javascript" src="js/404.js"></script>')!==false) {
			return FALSE;
		}
		if (preg_match('|<h1>([^<]+)<|', $document, $paragraph)) {
			$itemList['Title'] = $paragraph[1];
			$itemList['商品名称'] = $paragraph[1];// 目的是为了让格式和电子产品库保持一致
			if (strpos($itemList['Title'], '301 moved permanently') !== false) {
				return FALSE;
			}
		} else {
			return FALSE;
		}

		if (preg_match('|<img src="([^"]+)" alt="" id="largePic"|Us', $document, $paragraph)) {
			$itemList['IMAGE_SRC'] = $paragraph[1];
		}
		
		if (preg_match('|<p class="price_d">当 当 价：<span.*￥([^\<]+)<|Us', $document, $paragraph)) {
			$itemList['Price'] = $paragraph[1];
		}

		$itemList['STOCK'] = 1;
		if (strpos($document, '<p class=lack_tips><span>该商家暂时缺货</span></p>') !== false) {
			$itemList['STOCK'] = 0;
		}

		if (preg_match('|<div class="book_detailed" name="__Property_pub">.*<\/ul>|Ums', $document, $paragraph)) {
			if (preg_match('|>([^\<]+)</a>\s*著</p>|', $paragraph[0], $match)) {
				$itemList['作者'] = trim($match[1]);
			} else if (preg_match('|<p>作　　者：<a[^>]*>([^\<]+)<|', $paragraph[0], $match)) {
				$itemList['作者'] = trim($match[1]);
			} else if (preg_match('|>([^\<]+)</a> 主编</p>|', $paragraph[0], $match)) {
				$itemList['作者'] = trim($match[1]);
			}
			if (preg_match('|<span>I S B N：([^\<]+)</span>|', $paragraph[0], $match)) {
				$itemList['isbn'] = trim($match[1]);
				$itemList['model'] = $itemList['isbn'];// 目的是为了让格式和电子产品库保持一致
			} else if (preg_match('|<span>ISRC ：([^\<]+)</span>|', $paragraph[0], $match)) {
				$itemList['isbn'] = trim($match[1]);
				$itemList['model'] = $itemList['isbn'];// 目的是为了让格式和电子产品库保持一致
			}
			if (preg_match('|<p>出 版 社：<a href="[^\"]+" target="_blank">([^\<]+)</a></p>|Ums', $paragraph[0], $match)) {
				$itemList['出版社'] = trim($match[1]);
				$itemList['品牌'] = trim($match[1]);// 目的是为了让格式和电子产品库保持一致
				$itemList['生产厂家'] = trim($match[1]);// 目的是为了让格式和电子产品库保持一致
			}
			$itemList['商品产地'] = '';// 目的是为了让格式和电子产品库保持一致
			if (preg_match('|<p>出版时间：([^\<]+)</p>|', $paragraph[0], $match)) {
				$itemList['出版时间'] = $match[1];
				$itemList['上架时间'] = $match[1];// 目的是为了让格式和电子产品库保持一致
			}
			if (preg_match('|<ul class="clearfix">(.*)</ul>|Ums', $paragraph[0], $match)) {
				preg_match_all('|<span>([^\：]+)：([^<]+)</span>|', $match[1], $matches, PREG_PATTERN_ORDER);
				if (count($matches[1]) > 0) {
					$keyArray = array();
					foreach($matches[1] as $item) {
						$item = str_replace('　', '', $item);
						$keyArray[] = $item;
					}
					$fieldArray = array_combine($keyArray, $matches[2]);
					foreach($fieldArray as $field => $value) {
						$itemList[$field] = $value;
					}
				}
			}
		}
		
		foreach($itemList as $key => $value) {
			$itemList[$key] = Utility_ContentFilter::filterHtmlTags($value);
		}
		return $itemList;
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
	public function parseDetails($document, $productUrl)
	{
		$itemList = array();
		return $itemList;
	}

	public function parseComments($document, $productUrl)
	{
		$itemList = array();
		$p = strpos($document, '<div class="new_comments_tips">');
		if ($p === false) {
			return $itemList;
		}
		$document = substr($document, $p);
		$c = preg_match_all('#<h5[^>]*><a [^>]* name="reviewDetail">([^<]+)</a></h5>\s*<div class="text">\s*<div class="title clearfix">.*<span class="time">([^<]+)</span>.*<p>(.+)</p>#Us', $document, $m);
		for ($i=0; $i<$c; $i++) {
			$itemList[] = array(
				'TITLE' => Utility_ContentFilter::filterHtmlTags($m[1][$i], true),
				'SUMMARY' => Utility_ContentFilter::filterHtmlTags($m[3][$i], true),
				'POST_TIME' => Utility_ContentFilter::filterHtmlTags($m[2][$i], true)
				);
		}
		return $itemList;
	}
	public function parseFromInfo ($document, $productUrl)
	{
		$id = '0';
		$components = parse_url($productUrl);
		if (preg_match('|product_id=(\d+)|', $components['query'], $matches)) {
			$id = $matches[1];
		} else {
			return FALSE;
		}
		
		$fromInfo = array(
			'shopName'  => $this->_shopName,
			'shopId'    => $this->_shopId,
			'fromId'    => $id,
			'fromUrl'   => $productUrl
		);
		return $fromInfo;
	}
	public function toXml($productInfo, $comments=null)
	{
		$xmlData[] = '<?xml version="1.0" encoding="GB2312"?>';
		$xmlData[] = chr(10);
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

		$xmlData[] = '<规格参数><基本信息>';
		foreach ($productInfo['summary'] as $key => $value) {
			$line = '<' . $key . '>' . $value . '</' . $key . '>';
			$xmlData[] = $line;
		}
		$xmlData[] = '</基本信息></规格参数>';

		$xmlData[] = '<商品详情>';
		foreach ($productInfo['details'] as $key => $value) {
			$section = '<' . $key . '>';
			$line = '<![CDATA[' . $value . ']]>';
			$section .= $line;
			$section.= '</' . $key . '>';
			$xmlData[] = $section;
		}
		$xmlData[] = '</商品详情>';
		if ($comments) {
			$xmlData[] = $this->toCommentXml($comments);
		}

		$xmlData[] = '</' . $this->_categoryTree[2]
				 . '></' . $this->_categoryTree[1]
				 . '></' . $this->_categoryTree[0] . '>';
		$xmlData[] = '</Product>';

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

$pdangdang = new Parser_ProductDangdang;
$Parser['#^http://product\.dangdang\.com/product\.aspx\?product_id=\d+$#i'] = $pdangdang;
