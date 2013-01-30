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
	
	public function ParseForeachDB() {
		$sql = 'select id,uri from ps_urls';
		$sth = $this->db->query($sql);
		while($data = $sth->fetch()){
			#http://www.yihaodian.com/ctg/s2/c5260-%E8%96%AF%E7%89%87/b362/a23905-
			if (strpos($data['uri'], 'http://www.yihaodian.com/ctg/') !== FALSE) {
				if (strpos($data['uri'], '/b0/a-') === FALSE) {
					echo $data['id'],chr(10);
				}
			}
		}
	}
}

$parser = new forParser();
$parser->ParseForeachDB();
