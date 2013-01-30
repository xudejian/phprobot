<?php
abstract class Parser_ProductAbstract {
	public function parse($document, $productUrl) {
		$fromInfo = $this->parseFromInfo($document, $productUrl);
		if ($fromInfo === FALSE) {
			echo $productUrl,chr(10);
			return FALSE;
		}
		$summary  = $this->parseSummary($document, $productUrl);
		if ($summary === FALSE) {
			return FALSE;
		}
		$details  = $this->parseDetails($document, $productUrl);
		//$comments = $this->parseComments($document);
		$comments = array();
		
		if (isset($summary['model'])) {
			$fromInfo['model'] = $summary['model'];
		} else {
			$fromInfo['model'] = $fromInfo['fromId'];
		}
		
		$productInfo =  array('fromInfo' => $fromInfo,
							  'summary' => $summary,
							  'details' => $details);
		return array('info'     => $productInfo,
					 'comments' => $comments,
					);
	}
	
	public function saveInfo($saveData, $saveDir, $test=false) {
		$info = $saveData['info'];
		if (empty($info['fromInfo'])) {
			return;
		}
		$fromId = $info['fromInfo']['fromId'];
		$shopId = $info['fromInfo']['shopId'];
		$need_del = false;
		$saveDir .= $shopId . '/';
		
		if (empty($info['summary'])) {
			$need_del = true;
		}
		$saveDir .= intval(intval($fromId) / 10000);
		$saveFileFullName = $saveDir . '/' .$fromId . '.xml';
		$infoXml = $this->toXml($info, $saveData['comments']);
		if (empty($info['summary'])) {
			if (is_array($infoXml)) {
				$infoXml[] = '<DELETE />';
			} else {
				$infoXml .= '<DELETE />';
			}
		}
		if ($test) {
			if (is_array($infoXml)) {
				var_dump(implode($infoXml));
			} else {
				var_dump($infoXml);
			}
			return;
		}
		if (file_put_contents($saveFileFullName, $infoXml) === FALSE) {
			if (!is_dir($saveDir)) {
				mkdir($saveDir, 0766, true);
				file_put_contents($saveFileFullName, $infoXml);
				file_put_contents('../Newdata/new.txt', $saveFileFullName.chr(10), FILE_APPEND);
			}
		} else {
			file_put_contents('../Newdata/new.txt', $saveFileFullName.chr(10), FILE_APPEND);
		}
	}
	
	public function test($url) {
		if (empty($url)) {
			return false;
		}
		$document = Crawler_Http::getHttpFileContent($url);
		$info = $this->parse($document, $url);
		var_dump($info);
		$this->saveInfo($info, '', true);
	}
	
	abstract public function parseFromInfo($document, $productUrl);
	abstract public function parseSummary($document, $productUrl);
	abstract public function parseDetails($document, $productUrl);
	abstract public function parseComments($document, $productUrl);
	abstract public function toXml($productInfo, $comments=null);
	abstract public function toCommentXml($productInfo);
}
