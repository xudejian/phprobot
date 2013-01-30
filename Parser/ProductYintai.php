<?php
if (isset($argv[2]) && $argv[1] == 'test') {
	require_once  '../Config/Config.php';
	require_once  APP_HOME . 'Utility/Http.php';
}
require_once APP_HOME . 'Parser/ProductAbstract.php';
require_once APP_HOME . 'Utility/ContentFilter.php';
require_once APP_HOME . 'Crawler/Http.php';

class Parser_ProductYintai extends Parser_ProductAbstract
{
	private $_shopName = '银泰网';
	private $_shopId = 3;

	private $_categoryTree;

	public function parseSummary($document, $productUrl) {
		$itemList = array();
		$id=0;
		if (preg_match('#^http://www\.yintai\.com/product/productdetail\.aspx\?itemcode=([^&]+)#', $productUrl, $itemcode)) {
			$id = $itemcode[1];
			$itemList['model'] = $id;
		} else {
			return FALSE;
		}
		if (preg_match('|var YT_ProdDetailData=.*}\],|Us', $document, $v)) {
			if (preg_match('|"IC":"'.$id.'",.*"YP":"([^"]+)"|', $v[0], $p)) {
				$itemList['Price'] = $p[1];
			}
		}
		
		if (preg_match('|img:.*},|Us', $document, $v)) {
			if (preg_match('|"M":"(http:.*/largeimage[^"]+)"|', $v[0], $p)) {
				$itemList['IMAGE_SRC'] = str_replace('\/','/',$p[1]);
			}
		}
		
		if (preg_match('#<div class="grid">.*<!--y-pro-place-->#Ums', $document, $v)) {
			$document = $v[0];
		}
		
		$document = mb_convert_encoding($document, "GBK", "UTF-8");
		
		if (preg_match('|>您现在的位置.*</div>|Ums', $document, $v)) {
			if (preg_match_all('|<a [^>]*>(.*)</a>|Us', $v[0], $nav)) {
				if (!empty($nav[1])) {
					$c = count($nav[1]);
					$i = 0;
					if ($nav[1][0] == '首页') {
						++$i;
					}
					//服饰鞋帽
					$j=0;
					for (;$i<$c && $j<3;++$i,++$j) {
						$this->_categoryTree[$j] = trim($nav[1][$i]);
					}
					if ($j<3) for ($i=$j+1;$i<3;++$i) {
						$this->_categoryTree[$i] = $this->_categoryTree[$j];
					}
					static $cat = array('美妆'=>1,'箱包'=>1,'配饰'=>1);
					if (!isset($cat[$this->_categoryTree[0]])) {
						$this->_categoryTree[2] = $this->_categoryTree[1];
						$this->_categoryTree[1] = $this->_categoryTree[0];
						$this->_categoryTree[0] = '服饰鞋帽';
					}
				}
			}
		}
		
		if (preg_match('|<h1 class="p-tit">([^<]+)<|', $document, $paragraph)) {
			$itemList['Title'] = $paragraph[1];
			$itemList['商品名称'] = $paragraph[1];
		} else {
			return FALSE;
		}
		
		$itemList['STOCK'] = 1;

		if (preg_match('|<div class="pd-attr-place">.*</div>|Ums', $document, $v)) {
			preg_match_all('|<li>([^<]+)</li>|', $v[0], $matches, PREG_PATTERN_ORDER);
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
	
	public function parseDetails($document, $productUrl)
	{
		$itemList = array();
		return $itemList;
	}

	public function parseComments($document, $productUrl)
	{
		return NULL;
	}
	public function parseFromInfo ($document, $productUrl)
	{
		$id = '0';
		#http://www.yintai.com/product/productdetail.aspx?itemcode=20-011-7433&intcmp=20120418_yx_home_b_3-yundong_3
		if (preg_match('|itemcode=([^&]+)|', $productUrl, $matches)) {
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

$pyintai = new Parser_ProductYintai;
$Parser['#^http://www\.yintai\.com/product/productdetail\.aspx\?itemcode=#'] = $pyintai;

if (isset($argv[2]) && $argv[1] == 'test') {
	$pyintai->test($argv[2]);
}
