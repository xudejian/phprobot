<?php
function imageHeadFilter($ch, $header) {
	if (stripos($header, 'Content-Type') !== false) {
		if (stripos($header, 'image/') !== false) {
			return strlen($header);
		} else {
			return 0;
		}
	}
	return strlen($header);
}

function saveImageHttpFile($url, $file) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept-Encoding: gzip,deflate'));
	curl_setopt($ch, CURLOPT_ENCODING, TRUE);
	curl_setopt($ch, CURLOPT_HEADER, FALSE);
	curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HEADERFUNCTION, 'imageHeadFilter');
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; zh-CN;)");
	$data = curl_exec($ch);
	$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if ($httpcode == 200 && !empty($data)) {
		file_put_contents($file, $data);
	}
}

class NewImage {
	private $_newfilelist;

	public function __construct($newfilelist) {
		$this->_newfilelist = $newfilelist;
	}

	public function run() {
		$handle = fopen($this->_newfilelist, 'r');
		if (!$handle) {
			echo 'Can not open newdata list file <', $this->_newfilelist, '> for read!', chr(10);
			return false;
		}
		while ( !feof($handle) ) {
			$line = trim(fgets($handle, 4096));
			if (isset($line[0]) && $line[0] == '#') {
				continue;
			}
			$p = explode(',', $line, 2);
			if (!isset($p[1])) {
				continue;
			}
			$this->importRecords($p[0], $p[1]);
		}
		fclose($handle);
		echo 'all work done.', chr(10);
		return true;
	}

	public function importRecords($Id, $url)
	{
		$Id = intval($Id);
		if ($Id < 1 || empty($url)) {
			return false;
		}
		$dirA = intval($Id / 1000000);
		$dirB = intval(($Id % 1000000)/1000);
		$savePath = '../data/images/' . $dirA . '/' . $dirB;
		if (!is_dir($savePath)) {
			mkdir($savePath, 0755, true);
		}
		$i = 0;
		foreach (explode(' ', $url) as $u) {
			if (empty($u)) {
				continue;
			}
			$saveFullName = $savePath . '/' .$Id.'-'.$i.'.jpg';
			// not save again
			if (!is_file($saveFullName)) {
				saveImageHttpFile($u, $saveFullName);
			}
			++$i;
		}
		return $i;
	}
}

$newdatalist = '../Newdata/70w.image.txt';
$inprocess = '../Newdata/image.inp.'.posix_getpid();

while (is_file($newdatalist) && is_readable($newdatalist) && rename($newdatalist, $inprocess)) {
	$importer = new NewImage($inprocess);
	$importer->run();
	unset($importer);
	unlink($inprocess);
}
