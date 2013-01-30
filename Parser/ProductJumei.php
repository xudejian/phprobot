<?php
if (isset($argv[2]) && $argv[1] == 'test') {
	require_once  '../Config/Config.php';
	require_once  APP_HOME . 'Utility/Http.php';
}
require_once APP_HOME . 'Parser/ProductAbstract.php';
require_once APP_HOME . 'Utility/ContentFilter.php';
require_once APP_HOME . 'Crawler/Http.php';

class Parser_ProductJumei extends Parser_ProductAbstract
{
	private $_shopName = '聚美优品';
	private $_shopId = 4;

	private $_categoryTree;

	public function parseSummary($document, $productUrl) {
		if (preg_match('#^http://mall\.jumei\.com/product_\d+\.html#', $productUrl)) {
		} else {
			return FALSE;
		}
		
		if (preg_match('#<div id="container">.*<div class="deal_contents" id="product_report">#Ums', $document, $v)) {
			$document = $v[0];
		}
		
		$itemList = array();
		
		if (preg_match('|<div class="pic">\s*<img src="([^"]+)"|Us', $document, $v)) {
			$itemList['IMAGE_SRC'] = $v[1];
		}
		
		if (preg_match('|<input type="hidden" id="product_sku" value="([^"]+)"/>|Us', $document, $v)) {
			$itemList['model'] = $v[1];
		}
		
		$document = mb_convert_encoding($document, "GBK", "UTF-8");
		
		if (preg_match('|<span class="big_price"><label>￥</label>([^<]+)</span>|Us', $document, $v)) {
			$itemList['Price'] = $v[1];
		}
		
		if (preg_match('|<div class="location">.*</div>|Ums', $document, $v)) {
			if (preg_match_all('|<a [^>]*>(.*)</a>|Us', $v[0], $nav)) {
				if (!empty($nav[1])) {
					$c = count($nav[1]);
					$i = 0;
					if ($nav[1][0] == '聚美商城首页') {
						++$i;
					}
					$this->_categoryTree[0] = '个护化妆';
					if (isset($nav[1][2])) {
						$this->_categoryTree[1] = trim($nav[1][2]);
						$this->_categoryTree[2] = trim($nav[1][1]);
					} else {
						$j=1;
						for (;$i<$c && $j<3;++$i,++$j) {
							$this->_categoryTree[$j] = trim($nav[1][$i]);
						}
						if ($j==2) $this->_categoryTree[2] = $this->_categoryTree[1];
					}
				}
			}
		}
		
		if (preg_match('|<div class="title">(.+)</div>|Ums', $document, $paragraph)) {
			$itemList['Title'] = $paragraph[1];
			$itemList['商品名称'] = $paragraph[1];
		} else {
			return FALSE;
		}
		
		$itemList['STOCK'] = 1;

		if (preg_match('|<div id="detail_info_box" class="detail_info_box">.*</table>|Ums', $document, $v)) {
			preg_match_all('|<td><b>(.*)</td>|Ums', $v[0], $matches, PREG_PATTERN_ORDER);
			if (count($matches[1]) > 0) {
				foreach($matches[1] as $item) {
					$items = explode('：', $item, 2);
					if (isset($items[1])) {
						$itemList[trim($items[0])] = trim($items[1]);
					}
				}
			}
		}
		
		foreach($itemList as $key => $value) {
			$itemList[$key] = Utility_ContentFilter::filterHtmlTags($value);
		}
		return $itemList;
	}
	
	public function parseDetails($document, $productUrl) {
		$itemList = array();
		if (preg_match('#<div id="container">.*<div class="deal_contents" id="product_report">#Ums', $document, $v)) {
			$document = $v[0];
		}
		$document = mb_convert_encoding($document, "GBK", "UTF-8");
		if (preg_match('|<div class="block_title" id="title_product_parameter"></div>(.+)</div>|Ums', $document, $v)) {
			$itemList['product_parameter'] = $v[1];
		}
		if (preg_match('|<div class="block_title" id="title_story"></div>(.+)</div>|Ums', $document, $v)) {
			$itemList['story'] = $v[1];
		}
		if (preg_match('|<div class="block_title" id="title_usage"></div>(.+)</div>|Ums', $document, $v)) {
			$itemList['usage'] = $v[1];
		}
		foreach($itemList as $key => $value) {
			$itemList[$key] = Utility_ContentFilter::filterHtmlTags($value);
		}
		return $itemList;
	}

	public function parseComments($document, $productUrl) {
		return NULL;
	}
	
	public function parseFromInfo ($document, $productUrl) {
		$id = '0';
		if (preg_match('#^http://mall\.jumei\.com/product_(\d+)\.html#', $productUrl, $matches)) {
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
	
	public function toXml($productInfo, $comments=null) {
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

$pjumei = new Parser_ProductJumei;
$Parser['#^http://mall\.jumei\.com/product_\d+\.html#'] = $pjumei;

if (isset($argv[2]) && $argv[1] == 'test') {
	$pjumei->test($argv[2]);
}
