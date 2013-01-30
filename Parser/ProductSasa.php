<?php

require_once APP_HOME . 'Parser/ProductAbstract.php';
require_once APP_HOME . 'Utility/ContentFilter.php';
require_once APP_HOME . 'Crawler/Http.php';

class Parser_ProductSasa extends Parser_ProductAbstract
{
	private $_shopName = '香港sasa';
	private $_shopId = 11;

	private $_categoryTree;

	public function __construct($categoryTree)
	{
		$this->_categoryTree = $categoryTree;
	}

	public function parseSummary($document)
	{
		$itemList = array();
		$pos_start = strpos($document, '<!-- product_previously_viewed -->');
		if ($pos_start !== false) {
			$document = substr($document, $pos_start);
		}
		$pos_start = strpos($document, '<!-- product_previously_viewed end-->');
		if ($pos_start !== false) {
			$document = substr($document, $pos_start);
		}
		$pos_start = strpos($document, '<!-- product_page_images -->');
		if ($pos_start !== false) {
			$document = substr($document, $pos_start);
		}
		$pos_start = strpos($document, '<!-- product_description end -->');
		if ($pos_start !== false) {
			$document = substr($document, 0, $pos_start);
		}
		
		if (preg_match('|<td width="350"\s*align="center"><IMG SRC="([^"]+)"|', $document, $paragraph)) {
			#$itemList['smallImg'] = $this->_saveProductImage($paragraph[1]);
			$itemList['IMAGE_SRC'] = 'http://web3.sasa.com' . $paragraph[1];
		}
		$document = iconv('UTF-8', 'GB18030//IGNORE', $document);

		if (preg_match('|<h2><a[^>]*>(.+)</a></h2>|', $document, $paragraph)) {
			$itemList['Title'] = $paragraph[1];
			$itemList['商品名称'] = $paragraph[1];// 目的是为了让格式和电子产品库保持一致
		}
		if (preg_match('|<td class="txt_11px_b_EB6495">现价︰</td>\s*<td class="txt_11px_b_EB6495">US$ (.+)</td>|Ums', $document, $paragraph)) {
			$itemList['Price'] = number_format(floatval($paragraph[1]) * 6.568, 2);
		} else if (preg_match('|<td class="txt_11px_b_EB6495"[^>]*>现价︰</td>\s*<td class="txt_11px_b_EB6495">US$ (.+)</td>|Ums', $document, $paragraph)) {
			$itemList['Price'] = number_format(floatval($paragraph[1]) * 6.568, 2);
		}
		if (preg_match('|<h1><a href="/SasaWeb/sch/product/searchProduct.jspa[^"]*" class="txt_16px_b_666666" style="font-size:18px">(.+)</a></h1>|', $document, $match)) {
			$itemList['品牌'] = trim($match[1]);
		}
		if (preg_match('|<td class="txt_12px_b_666666" width="50">大小︰</td>\s*<td class="txt_12px_n_666666">(.+)</td>|Ums', $document, $match)) {
			$itemList['大小'] = trim($match[1]);
		}
		if (preg_match('|<td class="txt_12px_n_666666">产品号码︰(.+)</td>|', $document, $match)) {
			$itemList['产品号码'] = trim($match[1]);
		}
		if (strpos($document, '货到时以电邮通知我') !== false) {
			$itemList['STOCK'] = 0;
		} else {
			$itemList['STOCK'] = 1;
		}
		foreach($itemList as $key => $value) {
			$itemList[$key] = trim(Utility_ContentFilter::filterHtmlTags($value));
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
	private function _saveProductXml()
	{
	}
	public function parseDetails($document)
	{
		$itemList = array();
		$pos_start = strpos($document, '<!-- product_description -->');
		if ($pos_start === false) {
			return $itemList;
		}
		$document = substr($document, $pos_start);
		$pos_start = strpos($document, '<!-- product_description end -->');
		if ($pos_start === false) {
			return $itemList;
		}
		$document = substr($document, 0, $pos_start);
		$document = iconv('UTF-8', 'GB18030//IGNORE', $document);
		if (preg_match('|<td height="25" class="txt_12px_n_666666">(.*)</td>|Ums', $document, $paragraph)) {
			$c = preg_match_all('#<font class="txt_12px_b_EB6495">(.+)</font><br>(.+)<br>#Us', $paragraph[1].'<br>', $matches);
			for ($i=0; $i<$c; $i++) {
				$n = trim(Utility_ContentFilter::filterHtmlTags($matches[1][$i]));
				$v = trim(Utility_ContentFilter::filterHtmlTags($matches[2][$i]));
				$itemList[$n] = $v;
			}
		}
		return $itemList;
	}

	public function parseComments($document)
	{
		$itemList = array();
		$p = strpos($document, '<!-- product review -->');
		if ($p === false) {
			return $itemList;
		}
		$document = substr($document, $p);
		$p = strpos($document, '<!-- product review end -->');
		if ($p !== false) {
			$document = substr($document, 0, $p);
		}
		$document = iconv("UTF-8", "GB18030//IGNORE", $document);
		$c = preg_match_all('#<TABLE	width="640" border="0" cellpadding="0" cellspacing="2" align="center">\s*<TR>\s*<TD class="txt_11px_n_666666"><img src="[^"]*"> <B>([^<]*)</B></TD>\s*</TR>\s*<TR>\s*<TD class="txt_11px_n_666666">([^<]*)</TD>\s*</TR>\s*<TR>\s*<TD class="txt_11px_n_666666"><I>([^,]+), [^,]+, ([^<]+)</I></TD>\s*</TR>\s*</TABLE>#Us', $document, $m);
		for ($i=0; $i<$c; $i++) {
			$itemList[] = array(
				'TITLE' => Utility_ContentFilter::filterHtmlTags($m[1][$i], true),
				'USERNAME' => Utility_ContentFilter::filterHtmlTags($m[3][$i], true),
				'POST_TIME' => Utility_ContentFilter::filterHtmlTags($m[4][$i], true),
				'SUMMARY' => Utility_ContentFilter::filterHtmlTags($m[2][$i], true),
				);
		}
		return $itemList;
	}
	public function parseFromInfo ($document, $productUrl)
	{
		$id = '0';
		$components = parse_url($productUrl);
		#http://web3.sasa.com/SasaWeb/sch/product/viewProductDetail.jspa?itemno=104775802001
		if (preg_match('|itemno=(\d+)|', $components['query'], $matches)) {
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

		$xmlData .= '<规格参数><基本信息>';
		$filterArray = array(
			'smallImg',
			'Title',
			'Price',
			'生产厂家',
			'商品毛重',
			'商品产地',
			'上架时间',
			'品牌',
		);
		foreach ($productInfo['summary'] as $key => $value) {
			if (in_array($key, $filterArray)) {
				continue;
			}
			$line = '<' . $key . '>' . $value . '</' . $key . '>';
			$xmlData .= $line;
		}
		$xmlData .= '</基本信息></规格参数>';

		$xmlData .= '<商品详情>';
		foreach ($productInfo['details'] as $key => $value) {
			$section = '<' . $key . '>';
			$line = '<![CDATA[' . $value . ']]>';
			$section .= $line;
			$section.= '</' . $key . '>';
			$xmlData .= $section;
		}
		$xmlData .= '</商品详情>';
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
