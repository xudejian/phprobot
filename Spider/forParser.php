<?php

require_once  '../Config/Config.php';
require_once  APP_HOME . 'Crawler/Http.php';
require_once  APP_HOME . 'Utility/Http.php';
require_once  APP_HOME . 'Library/Log.php';

require_once  APP_HOME . 'Parser/Factory.php';

class forParser {
	private $db;
	private $save_path;
	private $base_id_file;
	private $productParser;
	private $obj_info_re;
    public function __construct() {
		$this->base_id_file = 'base_id';
		
		$configfile = 'site.js';
		if (!file_exists($configfile)) {
			echo __LINE__, ' config file no exists: ', $configfile;
			die (chr(10));
		}
		$config_str = file_get_contents ($configfile);
		if (strncmp($config_str, "\xEF\xBB\xBF", 3) === 0) {
			$config_str = substr($config_str, 3);
		}
		
		$config = json_decode ($config_str);
		if (!is_object($config)) {
			echo __LINE__,' config format error: ', $configfile, chr(10);
			if (function_exists('json_last_error')) {
				switch(json_last_error()) {
					case JSON_ERROR_DEPTH:
						echo ' - Maximum stack depth exceeded';
					break;
					case JSON_ERROR_CTRL_CHAR:
						echo ' - Unexpected control character found';
					break;
					case JSON_ERROR_SYNTAX:
						echo ' - Syntax error, malformed JSON';
					break;
					case JSON_ERROR_NONE:
						echo ' - No errors';
					break;
				}
			}
			die (chr(10));
		}
		if (!isset($config->save_path)) {
			$this->save_path = 'data';
		} else {
			$this->save_path = $config->save_path;
		}
		try {
			$this->db = new PDO($config->db_dsn, $config->db_username, $config->db_password);
		} catch (PDOException $e) {
			echo 'Connection failed: ', $e->getMessage();
			die (chr(10));
		}
		$config = null;
    }
	
	public function __destruct(){
		$this->db = null;
	}
	
	public function getPage($page_id) {
		$dirA = intval($page_id / 1000000);
		$dirB = intval(($page_id % 1000000)/1000);
		$savePath = $this->save_path . '/' . $dirA . '/' . $dirB;
		$saveFullName = $savePath . '/' .$page_id.'.html';
		return file_get_contents($saveFullName);
	}
	
	public function rmPage($page_id) {
		$dirA = intval($page_id / 1000000);
		$dirB = intval(($page_id % 1000000)/1000);
		$savePath = $this->save_path . '/' . $dirA . '/' . $dirB;
		$saveFullName = $savePath . '/' .$page_id.'.html';
		return unlink($saveFullName);
	}
	
	public function ParseLogFile() {
		global $Parser;
		$parsers = $Parser;
		for ($hour=0; $hour<24; ++$hour) {
			$fileName = $this->save_path . '/new.' . $hour;
			if (file_exists($fileName)) {
				$nFileName = $fileName.'.inp';
				if (file_exists($nFileName)) {
					continue;
				}
				rename($fileName, $nFileName);
				$handle = fopen($nFileName, 'r');
				if (!$handle) {
					continue;
				}
				echo 'parsing ', $fileName, chr(10);
				while ( !feof($handle) ) {
					$id = intval(fgets($handle, 4096));
					if ($id < 1) {
						continue;
					}
					$sql = 'select uri from ps_urls where id='.$id;
					$sth = $this->db->query($sql);
					$rows = $sth->fetchAll();
					$sth = null;
					if (empty($rows)) {
						continue;
					}
					$row = $rows[0];
					echo 'parsing ', $id, ' ', $row['uri'], chr(10);
					foreach ($parsers as $re=>$p) {
						if (preg_match($re, $row['uri'])) {
							$source = $this->getPage($id);
							$productInfo = $p->parse($source, $row['uri']);
							$p->saveInfo($productInfo, '../data/save/');
							break;
						}
					}
					$this->rmPage($id);
					unset($rows);
				}
				fclose($handle);
				unlink($nFileName);
				break;
			}
			if (file_exists('parse.stop')) {
				unlink('parse.stop');
				break;
			}
		}
	}
	
	public function ParseForeachDB() {
		global $Parser;
		$parsers = $Parser;
		if (!file_exists($this->base_id_file)) {
			echo 'not exists ',$this->base_id_file;
			die(chr(10));
		}
		$base_id = intval(file_get_contents($this->base_id_file));
		do {
			$sql = 'select id,uri from ps_urls where id>'.$base_id.' order by id asc limit 100';
			$sth = $this->db->query($sql);
			$rows = $sth->fetchAll();
			$sth = null;
			if (empty($rows)) {
				break;
			}
			foreach ($rows as $row) {
				foreach ($parsers as $re=>$p) {
					if (preg_match($re, $row['uri'])) {
						$source = $this->getPage($row['id']);
						$productInfo = $p->parse($source, $row['uri']);
						$p->saveInfo($productInfo, '../data/save/');
						break;
					}
				}
				$base_id = $row['id'];
			}
			file_put_contents($this->base_id_file, $base_id);
			unset($rows);
			if (file_exists('parse.stop')) {
				unlink('parse.stop');
				break;
			}
		} while (true);
		file_put_contents($this->base_id_file, $base_id);
	}
}

$parser = new forParser();
if (isset($argv[1]) && $argv[1] == 'DB') {
	$parser->ParseForeachDB();
} else {
	$parser->ParseLogFile();
}
