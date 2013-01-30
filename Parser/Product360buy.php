<?php
if (isset($argv[2]) && $argv[1] == 'test') {
	require_once  '../Config/Config.php';
	require_once  APP_HOME . 'Utility/Http.php';
}
require_once APP_HOME . 'Parser/ProductAbstract.php';
require_once APP_HOME . 'Utility/ContentFilter.php';
require_once APP_HOME . 'Crawler/Http.php';

class Parser_Product360buy extends Parser_ProductAbstract {
	private $_shopName = '京东商城';
	private $_shopId = 1;

	private $_categoryTree;

	public function parseSummary($document, $productUrl) {
		$type = '';
		if (preg_match('#^http\://www\.360buy\.com/product/\d+\.html$#i', $productUrl)) {
			$type = 'www';
		} else if (preg_match('#^http://(book|mvd)\.360buy\.com/\d+\.html$#i', $productUrl)) {
			$type = 'book';
		} else {
			return FALSE;
		}
		
		$itemList = array();
		if ($type == 'www') {
			if (preg_match('|<div class="breadcrumb">.*</div>|Ums', $document, $v)) {
				if (preg_match_all('|<a [^>]*>(.*)</a>|Us', $v[0], $nav)) {
					if (isset($nav[1][2])) {
						$this->_categoryTree[0] = $nav[1][0];
						$this->_categoryTree[1] = $nav[1][1];
						$this->_categoryTree[2] = $nav[1][2];
					}
				}
			}
			if (preg_match('|<ul class="list-h">(.+)</ul>|Ums', $document, $paragraph)) {
				if (preg_match_all('|<img .* src="([^"]+)"|Us', $paragraph[1], $urls)) {
					$patterns[0] = '/img\d+/';$replacements[0] = 'img10';
					$patterns[1] = '/\/n\d+\//';$replacements[1] = '/n0/';
					$itemList['IMAGE_SRC'] = preg_replace($patterns, $replacements, implode(" ", $urls[1]));
				}
			}
			if (preg_match('/<ul id="i-detail">.+?<\/ul>/is', $document, $paragraph)) {
				preg_match_all('/<li[^>]*>(.+)<\/li>/i', $paragraph[0], $matches, PREG_PATTERN_ORDER);
				if (count($matches[1]) > 0) {
					foreach($matches[1] as $item) {
						$tempArray = explode("：", $item);
						if (count($tempArray) == 2) {
							$itemList[$tempArray[0]] = $tempArray[1];
						}
					}
				}
			}
			if (preg_match('|>商品编号：(\d+)<|Us', $document, $paragraph)) {
				$itemList['model'] = $paragraph[1];
			}
		} else if ($type == 'book') {
			if (preg_match('|<div class="crumb">.*</div>|Ums', $document, $v)) {
				if (preg_match_all('|<a [^>]*>(.*)</a>|Us', $v[0], $nav)) {
					if (isset($nav[1][3])) {
						$this->_categoryTree[0] = $nav[1][1];
						$this->_categoryTree[1] = $nav[1][2];
						$this->_categoryTree[2] = $nav[1][3];
					} else if (isset($nav[1][2])){
						$this->_categoryTree[0] = $nav[1][0];
						$this->_categoryTree[1] = $nav[1][1];
						$this->_categoryTree[2] = $nav[1][2];
					}
				}
			}
			if (preg_match('/images\/none\/none_347.gif\'" src="([^"]+)"/i', $document, $paragraph)) {
				$patterns[0] = '/img\d+/';$replacements[0] = 'img10';
				$patterns[1] = '/\/n\d+\//';$replacements[1] = '/n0/';
				$itemList['IMAGE_SRC'] = preg_replace($patterns, $replacements, $paragraph[1]);
			}
			if (preg_match('|<span>ＩＳＢＮ：</span>(.+)<|', $document, $paragraph)) {
				$itemList['model'] = $paragraph[1];
			} else if (preg_match('|<span>条&nbsp;形&nbsp;码：(.+)<|', $document, $paragraph)) {
				$itemList['model'] = $paragraph[1];
			}
			
			if (preg_match('/<ul id="summary"[^>]*>.+<\/ul>/iUs', $document, $paragraph)) {
				preg_match_all('/<li[^>]*>(.+)<\/li>/iUs', $paragraph[0], $matches, PREG_PATTERN_ORDER);
				if (count($matches[1]) > 0) {
					foreach($matches[1] as $item) {
						$tempArray = explode("：", $item);
						if (count($tempArray) == 2) {
							$itemList[trim(Utility_ContentFilter::filterHtmlTags($tempArray[0], true))] = $tempArray[1];
						}
					}
				}
			}
		}
		
		if (preg_match('/<h1>(.+)</Us', $document, $paragraph)) {
			$itemList['Title'] = $paragraph[1];
		}
		
		$itemList['Price'] = $this->_getProductPrice($document);
		/*
		if (preg_match('|var wareinfo = \{ pid: "[^"]*", sid: "([^"]+)", djd: "[^"]*" \};|Us', $document, $paragraph)) {
			$stock_url = 'http://price.360buy.com/stocksoa/StockHandler.ashx?callback=getProvinceStockCallback&type=provincestock&skuid='.$paragraph[1].'&provinceid=1';
			$content = Crawler_Http::getHttpFileContent($stock_url);
			if (strpos($content, '"StockState":34') !== false) {
				$itemList['STOCK'] = 0;
			} else {
				$itemList['STOCK'] = 1;
			}
		}
		*/
		
		if (isset($itemList['价格举报'])) {
			unset($itemList['价格举报']);
		}
		if (isset($itemList['纠错信息'])) {
			unset($itemList['纠错信息']);
		}
		
		foreach($itemList as $key => $value) {
			$itemList[$key] = Utility_ContentFilter::filterHtmlTags($value, TRUE);
		}
		return $itemList;
	}

	private function _getProductPrice($document) {
		if (preg_match('|京东价：￥([\d\.]+)。|Us', $document, $p)) {
			return $p[1];
		} else if (preg_match('|src="(http://price\.360buy\.com/price-[^\"]+)"></script>|', $document, $paragraph)) {
			$priceUrl = $paragraph[1];
			$priceContent = Crawler_Http::getHttpFileContent($priceUrl);
			if (preg_match('/￥([0-9]+.00)/', $priceContent, $matches)) {
				return $matches[1];
			} else if (preg_match('/FFE5([\d\.]+)",/', $priceContent, $matches)) {
				return $matches[1];
			} else {
				return 0;
			}
		} else if (preg_match('|京&nbsp;东&nbsp;价：<strong class="price"><img onerror = "this\.src=\'http://www\.360buy\.com/images/no2\.gif\'"  src ="(http://price\.[^\,]+,3\.png)"/>|Us', $document, $paragraph)) {
			$pricePicUrl = $paragraph[1];
			$returnInfo = array();
			exec('python ' . APP_HOME . 'Library/Python/360buy.py ' . escapeshellarg($pricePicUrl), $returnInfo);
			if (count($returnInfo)) {
				$priceStr = $returnInfo[0];
				$price = (int)$priceStr / 100;
				return $price;
			}
		}
		return 0;
	}
	
	public function parseDetails($document, $productUrl) {
		if (preg_match('/<table cellpadding="0" cellspacing="1" width="100\%" border="0" class="Ptable">(.+)<\/table>/is', $document, $paragraph)) {
			preg_match_all('/<tr>(.*?)<\/tr>/', $paragraph[1], $matches, PREG_PATTERN_ORDER);
			$itemList = array();
			if (!empty($matches[1])) {
				$currentCategory = '';
				foreach($matches[1] as $item) {
					if (preg_match('/<th[^>]*>(.*?)<\/th><tr><tr><td[^>]*>(.*?)<\/td><td>(.*?)<\/td>/is', $item, $categoryMatch)) {
						$categoryMatch[1] = Utility_ContentFilter::filterSpaces($categoryMatch[1]);
						$categoryMatch[2] = Utility_ContentFilter::filterSpaces($categoryMatch[2]);
						$currentCategory = $categoryMatch[1];
						$itemList[$currentCategory][$categoryMatch[2]] = Utility_ContentFilter::filterHtmlTags($categoryMatch[3], true);
						$currentCategoryArray = $itemList[$categoryMatch[1]];
					} else if (preg_match('/<td[^>]*>(.*?)<\/td><td>(.*?)<\/td>/is', $item, $detailMatch)) {
						$detailMatch[1] = Utility_ContentFilter::filterSpaces($detailMatch[1]);
						$itemList[$currentCategory][$detailMatch[1]] = Utility_ContentFilter::filterHtmlTags($detailMatch[2], true);
					}
				}
			}
			return $itemList;
		}
		return array();
	}

	public function parseComments($document, $productUrl) {
		return array();
	}
	
	public function parseFromInfo ($document, $productUrl) {
		if (preg_match('#^http\://www\.360buy\.com/product/\d+\.html$#i', $productUrl)) {
		} else if (preg_match('#^http://(book|mvd)\.360buy\.com/\d+\.html$#i', $productUrl)) {
		} else {
			return FALSE;
		}
		$id = basename($productUrl, '.html');
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

		$xmlData[] = '<规格参数>';
		foreach ($productInfo['details'] as $key => $value) {
			$section = '<' . $key . '>';
			foreach ($value as $subKey => $subValue) {
				$line = '<' . $subKey . '>' . $subValue . '</' . $subKey . '>';
				$section .= $line;
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
		$xmlData [] = '</Product>';

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

$p360buy = new Parser_Product360buy;
$Parser['#^http://www\.360buy\.com/product/\d+\.html$#i'] = $p360buy;
$Parser['#^http://(book|mvd)\.360buy\.com/\d+\.html$#i'] = $p360buy;

if (isset($argv[2]) && $argv[1] == 'test') {
	$p360buy->test($argv[2]);
}
