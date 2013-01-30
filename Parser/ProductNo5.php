<?php

if (isset($argv[2]) && $argv[1] == 'test') {
	require_once  '../Config/Config.php';
	require_once  APP_HOME . 'Utility/Http.php';
}

require_once APP_HOME . 'Parser/ProductAbstract.php';
require_once APP_HOME . 'Utility/ContentFilter.php';
require_once APP_HOME . 'Crawler/Http.php';

class Parser_ProductNo5 extends Parser_ProductAbstract
{
	private $_shopName = 'No5';
	private $_shopId = 11;

	private $_categoryTree;

	public function parseSummary($document, $productUrl) {
		if (preg_match("#^http://www\.no5\.com\.cn/goods/\d+\.html$#i", $productUrl)) {
		} else {
			return FALSE;
		}
		$itemList = array();
		if (preg_match('#img .* src="(http://photo\.no5.com.cn/product/bphoto/[^"]+)"#Us', $document, $paragraph)) {
			$itemList['IMAGE_SRC'] = $paragraph[1];
		}
		if (preg_match('#<span class="sub_nametitle">(.+)</span>#Us', $document, $paragraph)) {
			$itemList['Title'] = $paragraph[1];
			$itemList['商品名称'] = $paragraph[1];
		}
		if (!isset($itemList['Title'])) {
			return false;
		}
		
		if (preg_match('#id="groupDefaultPrice" value="([^"]+)"#Us', $document, $paragraph)) {
			$itemList['Price'] = $paragraph[1];
		} else if (preg_match('#<span id="groupTotalPrice">￥(.+)元</span>#Us', $document, $paragraph)) {
			$itemList['Price'] = $paragraph[1];
		}
		
		if (preg_match('#<div class="pro_name">所属分类：</div><div class="pro_text">.*</div>#Us', $document, $v)) {
			#<span><a href="/browse/c1.html">护肤</a></span><span>→</span><span><a href="/browse/c26.html">卸妆</a></span>
			if (preg_match_all('|<a [^>]*>(.*)</a>|Us', $v[0], $nav)) {
				if (isset($nav[1][2])) {
					$this->_categoryTree[0] = $nav[1][0];
					$this->_categoryTree[1] = $nav[1][1];
					$this->_categoryTree[2] = $nav[1][2];
				} else {
					$this->_categoryTree[0] = $nav[1][0];
					$this->_categoryTree[1] = $nav[1][1];
					$this->_categoryTree[2] = $nav[1][1];
				}
			}
		}
		
		if (preg_match('#<div class="pro_name">所属品牌：.*<a href="/browse/b[^>]*>([^<]*)</a>#Us', $document, $paragraph)) {
			$itemList['品牌'] = $paragraph[1];
		}

		foreach($itemList as $key => $value) {
			$itemList[$key] = Utility_ContentFilter::filterHtmlTags($value, True);
		}
		return $itemList;
	}
	
	public function parseDetails($document, $productUrl)
	{
		#<ul class="introduce"><li><span class="title">包装说明：</span><span class="context">无外盒有密封 </span></li></ul>
		$itemList = array();
		if (preg_match('#<ul class="introduce">.*</ul>#Us', $document, $v)) {
			$c = preg_match_all('|<li><span class="title">(.+)：</span><span class="context">(.+)</span></li>|Us', $v[0], $nav);
			for ($i = 0; $i < $c; ++$i) {
				$k = Utility_ContentFilter::filterHtmlTags($nav[1][$i], True);
				$itemList[$k] = Utility_ContentFilter::filterHtmlTags($nav[2][$i], True);
			}
		}
		
		if (preg_match('#<div class="sp_p_texth">(.*)</div>#mUs', $document, $paragraph)) {
			$itemList['商品明细'] = Utility_ContentFilter::filterHtmlTags($paragraph[1], true);
		}
		return $itemList;
	}

	public function parseComments($document, $productUrl) {
		return array();
	}
	
	public function parseFromInfo ($document, $productUrl) {
		$id = basename($productUrl, '.html');
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

$pNo5 = new Parser_ProductNo5;
$Parser["#^http://www\.no5\.com\.cn/goods/\d+\.html$#i"] = $pNo5;

if (isset($argv[2]) && $argv[1] == 'test') {
	$pNo5->test($argv[2]);
}