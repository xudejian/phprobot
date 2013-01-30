<?php

require_once '../Config/Config.php';
require_once '../Library/Db.php';

define('DEBUG', FALSE);

class DbImporter {
	private $_db;
	private $_scanTime;
	private $_filelist;
	private $_row;
	private $_categories;
	private $_del;

	private $_log = '../log/DBI.newdata.log';
	/**
	 * 临时保存当前记录在来源商城的产品ID
	 * @var unknown_type
	 */
	private $_fromId;
	/**
	 * 新产品计数
	 * @var unknown_type
	 */
	private $_statistic;
	/**
	 * 购买页面计数
	 * @var unknown_type
	 */
	private $_countBuyItem;

	public $Cates = null;
	public $_cateAlias = './cate.alias.txt';

	public function __construct($dbConfig, $dataFile) {
		$this->_del = true;
		if (is_file('.nodel')) {
			$this->_del = false;
		}
		$this->_loadCateAlias();

		$this->_filelist = $dataFile;
		if (!is_file($this->_filelist)) {
			$this->_writeLog('File <' . $dataFile . '> not exists!' . chr(10));
			exit;
		}
		$this->_db = new Db();
		$this->_db->connect($dbConfig);
		$this->_loadCategories();
		$this->_statistic = array(
			'totalRecords'  => 0,
			'newProducts' => 0,
			'newBuyRecords' => 0,
			'newCategories' => 0,
			'newBrands'   => 0,
			'totalTimeCost' => 0,
		);
	}

	public function __destruct() {
		$this->_db->close_db();
	}

	public function run() {
		$this->_writeLog('impoter started ' .posix_getpid() . chr(10));
		$this->importFilelist($this->_filelist);
		$this->_writeLog('impoter ended ' .posix_getpid() . chr(10));
	}

	public function importFilelist($filelist) {
		$handle = fopen($filelist, 'r');
		if (!$handle) {
			$this->_writeLog('Can not open newdata list file <' . $filelist . '> for read!' . chr(10));
			return false;
		}
		while ( !feof($handle) ) {
			$line = trim(fgets($handle, 4096));
			if (strlen($line)<1 || $line{0} == "#") {
				continue;
			}
			if (file_exists($line)) {
				$this->importFile($line);
				if ($this->_del) {
					unlink($line);
				}
			}
		}
		fclose($handle);
		return true;
	}

	public function importFile($fileName) {
		if (empty($fileName)) {
			return;
		}
		$dataStr = file_get_contents($fileName);
		if (empty($dataStr)) {
			$this->_writeLog('Get contents of file <' . $fileName . '> empty.' . chr(10));
			return;
		}
		if (false === $this->_bindRow($dataStr)) {
			$this->_writeLog('bind row of file <' . $fileName . '> failed.' . chr(10));
			return;
		}
		$this->_insertRow();
	}
	
	private function _insertCategory($topCat, $Cat, $subCat, $insert = FALSE) {
		if (empty($topCat) || empty($Cat) || empty($subCat)) {
			return array('topCategoryId'=>0, 'categoryId'=>0, 'subCategoryId'=>0);
		}
		$key = $topCat . '#' . $Cat . '#' . $subCat;
		if ( array_key_exists($key, $this->_categories) ) {
			return array(
				'topCategoryId'  => $this->_categories[$key]['topId'],
				'categoryId'     => $this->_categories[$key]['pId'],
				'subCategoryId'  => $this->_categories[$key]['id'],
			);
		}

		$topId = 0;
		$pRow = $this->_db->row_select_one('data_categories', 'tree_level=0 AND name=\'' . addslashes($topCat).'\'', 'id');
		if (false === $pRow) {
			if ($insert) {
				$data = array();
				$data['name'] = $topCat;
				$data['tree_level'] = 0;
				$data['parent_id'] = 0;
				$this->_db->row_insert('data_categories', $data);
				$topId = $this->_db->insert_id();
				$this->_noticeCates(0, $topCat);
			}
		} else {
			$topId = $pRow['id'];
		}
		if ($topId === 0) {
			$topCat = '其他';
			$key = $topCat . '#' . $Cat . '#' . $subCat;
			if ( array_key_exists($key, $this->_categories) ) {
				return array(
					'topCategoryId'  => $this->_categories[$key]['topId'],
					'categoryId'     => $this->_categories[$key]['pId'],
					'subCategoryId'  => $this->_categories[$key]['id'],
				);
			}
		}

		$pId = 0;
		$pRow = $this->_db->row_select_one('data_categories', 'tree_level=1 AND name=\'' . addslashes($Cat).'\'', 'id');
		if (false === $pRow) {
			$data = array();
			$data['name'] = $Cat;
			$data['tree_level'] = 1;
			$data['parent_id'] = $topId;
			$this->_db->row_insert('data_categories', $data);
			$pId = $this->_db->insert_id();
			$this->_noticeCates(1, $topCat.','.$Cat);
		} else {
			$pId = $pRow['id'];
		}

		$Id = 0;
		$pRow = $this->_db->row_select_one('data_categories', 'tree_level=2 AND name=\'' . addslashes($subCat).'\'', 'id');
		if (false === $pRow) {
			$data = array();
			$data['name'] = $subCat;
			$data['tree_level'] = 2;
			$data['parent_id'] = $pId;
			$this->_db->row_insert('data_categories', $data);
			$Id = $this->_db->insert_id();
			$this->_noticeCates(2, $topCat.','.$Cat.','.$subCat);
		} else {
			$Id = $pRow['id'];
		}

		$value = array(
			'id' => $Id,
			'pId'  => $pId,
			'topId' => $topId
		);
		if ($Id && $pId && $topId) {
			$this->_categories[$key] = $value;
		}
		return array(
			'topCategoryId'  => $topId,
			'categoryId'     => $pId,
			'subCategoryId'  => $Id
		);
	}
	
	private function _loadCategories() {
		$this->_categories = array();
		$topCats = $this->_db->row_select('data_categories', 'tree_level=0');
		$topCatsMap = array(0=>'其他');
		foreach ($topCats as $row) {
			$topCatsMap['keyValue'][$row['id']] = $row['name'];
			$topCatsMap['valueKey'][$row['name']] = $row['id'];
		}

		$cats = $this->_db->row_select('data_categories', 'tree_level=1');
		$catsMap = array();
		foreach ($cats as $row) {
			$catsMap['keyValue'][$row['id']] = $row['name'];
			$catsMap['valueKey'][$row['name']] = $row['id'];
		}
		$subCats = $this->_db->row_select('data_categories', 'tree_level=2');
		foreach($subCats as $row) {
			$pId = $row['parent_id'];
			$pName = $catsMap['keyValue'][$pId];
			$pRow = $this->_db->row_select_one('data_categories', 'id=' . $pId);
			$topId = $pRow['parent_id'];
			if ($topId == 0) {
				$topName = '其他';
			} else {
				$topName = $topCatsMap['keyValue'][$topId];
			}
			$key = $topName . '#' . $pName . '#' . $row['name'];
			$value = array(
				'id' => $row['id'],
				'pId'  => $pId,
				'topId' => $topId
			);
			$this->_categories[$key] = $value;
		}
	}

	private function _loadCateAlias() {
		$this->Cates = array(1=>array(),2=>array(),3=>array());
		$handle = fopen($this->_cateAlias, "r");
		if ($handle !== false) {
			while (!feof($handle)) {
				$line = trim(fgets($handle, 4096));
				if (empty($line)) {
					continue;
				}
				if ($line[0] == '#') {
					continue;
				}
				$items = explode(",", $line);
				$l = intval($items[0]);
				if ($l<1 || $l>3) {
					continue;
				}
				$c = count($items);
				for ($i=2; $i<$c; $i++) {
					$this->Cates[$l][$items[$i]] = $items[1];
				}
			}
			fclose($handle);
		}
	}

	private function _getCategoryIds() {
		return $this->_insertCategory($this->_row['top_category'], $this->_row['category'], $this->_row['sub_category'], $this->_row['site_id']==1);
	}

	protected function _clearRow() {
		unset($this->_row);
		$this->_row = array();
	}

	protected function _recognizeCategory() {
		$this->_row['top_category'] = trim($this->_row['top_category']);
		if (array_key_exists($this->_row['top_category'], $this->Cates[1])) {
			$this->_row['top_category'] = $this->Cates[1][$this->_row['top_category']];
		}
		$this->_row['category'] = trim($this->_row['category']);
		if (array_key_exists($this->_row['category'], $this->Cates[2])) {
			$this->_row['category'] = $this->Cates[2][$this->_row['category']];
		}
		$this->_row['sub_category'] = trim($this->_row['sub_category']);
		if (array_key_exists($this->_row['sub_category'], $this->Cates[3])) {
			$this->_row['sub_category'] = $this->Cates[3][$this->_row['sub_category']];
		}
	}

	protected function _bindRow($dataStr) {
		$this->_clearRow();
		$this->_row['site_id'] = (1 == preg_match('/<shopId>(\d+)/', $dataStr, $matches)) ? $matches[1] : 0;
		$this->_row['site_id'] = intval($this->_row['site_id']);
		$this->_fromId = (1 == preg_match('/<fromId>([^<]+)</', $dataStr, $matches)) ? $matches[1] : '';
		$this->_row['from_id'] = $this->_fromId;

		if (strrpos($dataStr, '<DELETE />') !== FALSE) {
			$this->_row['DELETE'] = true;
			return true;
		}

		// title字段保持和来源页面一致；NAME字段优先使用“商品名称”，并保持大小写不变
	    $this->_row['title'] = '';
	    if (1 == preg_match('/<Title>([^<]+)<\/Title>/', $dataStr, $matches)) {
			$this->_row['title'] = trim($matches[1]);
        }
		if (empty($this->_row['title'])) {
			if (1 == preg_match('/<商品名称>([^<]+)<\/商品名称>/', $dataStr, $matches)) {
				$this->_row['title'] = trim($matches[1]);
			}
		}
		
		if (empty($this->_row['site_id'])) {
			return false;
		}
		if (empty($this->_row['title'])) {
			return false;
		}

		$bookcat['音乐'] = true;
		$bookcat['图书'] = true;
		$bookcat['教育音像'] = true;
		$bookcat['影视'] = true;
		$this->_row['top_category'] = (1 == preg_match('/<Product><([^>]+)>/', $dataStr, $matches)) ? trim($matches[1]) : '';
		if (empty($this->_row['top_category'])) {
			echo $this->_row['site_id'], "\t", $this->_row['from_id'], chr(10);
		}
		if (isset($bookcat[$this->_row['top_category']])) $this->_row['top_category'] = '图书音像';
		$this->_row['category'] = (1 == preg_match('/<Product><([^>]+)><([^>]+)>/', $dataStr, $matches)) ? trim($matches[2]) : '';
		$this->_row['sub_category'] = (1 == preg_match('/<Product><([^>]+)><([^>]+)><([^>]+)>/', $dataStr, $matches)) ? trim($matches[3]) : '';
		$this->_recognizeCategory();
		$this->_row['from_url'] = (1 == preg_match('/<fromUrl>([^<]+)<\/fromUrl>/', $dataStr, $matches)) ? $matches[1] : '';
		$this->_row['model'] = (1 == preg_match('/<model>([^<]+)<\/model>/', $dataStr, $matches)) ? strtolower(trim($matches[1])) : '';

		$this->_row['home'] = (1 == preg_match('/<商品产地>([^<]+)<\/商品产地>/Us', $dataStr, $matches)) ? $matches[1] : '';
		if (strcmp($this->_row['top_category'], '图书音像') === 0) {
			$this->_row['home'] = (1 == preg_match('/<作\s*者>([^<]+)</Us', $dataStr, $matches)) ? $matches[1] : '';
			$isbn = (1 == preg_match('/<isbn>([^<]+)<\/isbn>/i', $dataStr, $matches)) ? strtolower(trim($matches[1])) : '';
			if (strpos($isbn, ',') !== FALSE) {
				list($isbn,) = explode($isbn, ',',2);
				$this->_row['model'] = $isbn;
			}
		}
		$this->_row['publish_time'] = date('Y-m-d H:i:s');
		$this->_row['detail'] = (1 == preg_match('/<规格参数>(.*?)<\/规格参数>/Us', $dataStr, $matches)) ? $matches[1] : '';
		$this->_row['image_src'] = (1 == preg_match('/<IMAGE_SRC>(.*?)<\/IMAGE_SRC>/', $dataStr, $matches)) ? $matches[1] : '';
		$price = (1 == preg_match('/<Price>(.*?)<\/Price>/', $dataStr, $matches)) ? $matches[1] : '';
		$price = str_replace(',','', $price);
		$price = str_replace('￥','', $price);
		$this->_row['price'] = intval(100 * floatval($price));
		$this->_row['stock'] = (1 == preg_match('/<STOCK>(\d*)<\/STOCK>/', $dataStr, $matches)) ? $matches[1] : "1";

		// parse brand & ebrand
		$brand = (1 == preg_match('/<品牌>([^<]+)<\/品牌>/Us', $dataStr, $matches)) ? $matches[1] : '';
		if ($brand === '') {
			$brand = (1 == preg_match('/<生产厂家>([^<]+)<\/生产厂家>/Us', $dataStr, $matches)) ? $matches[1] : '';
		}
		$brandArray = $this->_recognizeEnglishBrand($brand);
		$this->_row['brand'] = $brandArray['NAME'];
		$this->_row['ENBRAND'] = $brandArray['ENAME'];

		foreach($this->_row as $item => $val) {
			$this->_row[$item] = trim($val);
		}
	}
	
	protected function _noticeKernel($productId) {
		file_put_contents('../Newdata/new.tokernel.txt', $productId.chr(10), FILE_APPEND);
	}
	
	protected function _noticeImage($productId, $imgurl) {
		file_put_contents('../Newdata/new.image.txt', $productId.','.$imgurl.chr(10), FILE_APPEND);
	}
	
	protected function _noticeCates($level, $msg) {
		file_put_contents('../Newdata/new.cates.txt', $level.','.$msg.chr(10), FILE_APPEND);
	}
	
	private function del_product($shop_id, $from_id) {
		$where = 'site_id='.intval($shop_id).' and from_id="'.mysql_escape_string($from_id).'"';
		$row = $this->_db->row_select_one('data_products', $where, "id,base_id");
		if (false !== $row) {
			$where = 'id='.$row['id'];
			$rv = $this->_db->row_delete('data_products', $where);
			$where = 'base_id='.$row['base_id'];
			$count = $this->_db->row_count('data_products', $where, "id,base_id");
			if ($count < 1) {
				$where = 'id='.$row['base_id'];
				$rv = $this->_db->row_delete('data_base_products', $where);
			}
			file_put_contents('../Newdata/del.txt', $shop_id.chr(9).$from_id.chr(10), FILE_APPEND);
		}
	}
	
	protected function _insertRow() {
		if (isset($this->_row['DELETE']) && $this->_row['DELETE']) {
			$this->del_product($this->_row['site_id'], $this->_row['from_id']);
			return;
		}
		$categoryIds = $this->_getCategoryIds();
		$this->_row['top_category_id'] = $categoryIds['topCategoryId'];
		$this->_row['category_id'] = $categoryIds['categoryId'];
		$this->_row['sub_category_id'] = $categoryIds['subCategoryId'];
		
		$productId = 0;
		$row = false;

		$where = 'site_id='.$this->_row['site_id'].' AND from_id="'.addslashes($this->_row['from_id']).'"';
		$row = $this->_db->row_select_one('data_products', $where, "id,base_id");
		if (false !== $row) {
			$this->_row['base_id'] = $row['base_id'];
			$this->_row['update_id'] = $row['id'];
		}

		if (false === $row && $this->_row['top_category'] == '图书音像' && !empty($this->_row['model'])) {
			$where = 'model="' . addslashes($this->_row['model']) . '"';
			$row = $this->_db->row_select_one('data_base_products', $where, 'id');
			if (false !== $row) {
				$this->_row['base_id'] = $row['id'];
			}
		}
		static $cate = array('手机数码'=>true,'电脑、办公'=>true,'家用电器、汽车用品'=>true);
		if (false === $row && isset($cate[$this->_row['top_category']])) {
			//分类,品牌,型号
			$where = 'brand="'.addslashes($this->_row['brand']).'" and model="' . addslashes($this->_row['model']) . '" and top_category_id='.intval($this->_row['top_category_id']).' and category_id='.intval($this->_row['category_id']).' and sub_category_id='.intval($this->_row['sub_category_id']);
			$row = $this->_db->row_select_one('data_base_products', $where, 'id');
			if (false !== $row) {
				$this->_row['base_id'] = $row['id'];
			}
		}

		if (!isset($this->_row['base_id'])) {
			$productId = $this->_insertBaseProduct();
			$this->_insertBuyInfo($productId);
			if ($productId > 0) {
				$this->_writeLog('_insert : ' . $productId . chr(10));
				$this->_insertImage($productId);
			}
		} else {
			$this->_writeLog('update : ' . $this->_row['base_id'] . chr(10));
			$this->_insertBuyInfo();
		}
	}
	
	private function _insertBaseProduct() {
		// insert to data_base_products
		$data = array();
		$data['title'] = $this->_row['title'];
		$data['brand'] = $this->_row['brand'];
		$data['top_category'] = $this->_row['top_category'];
		$data['category'] = $this->_row['category'];
		$data['sub_category'] = $this->_row['sub_category'];
		$data['top_category_id'] = $this->_row['top_category_id'];
		$data['category_id'] = $this->_row['category_id'];
		$data['sub_category_id'] = $this->_row['sub_category_id'];
		$data['home'] = $this->_row['home'];
		$data['publish_time'] = $this->_row['publish_time'];
		$data['site_id'] = $this->_row['site_id'];
		$data['from_id'] = $this->_row['from_id'];
		$data['from_url'] = $this->_row['from_url'];
		$data['detail'] = $this->_row['detail'];
		//$data['stock'] = $this->_row['stock'];
		$data['model'] = $this->_row['model'];

		$this->_db->row_insert('data_base_products', $data);
		return $this->_db->insert_id();
	}
	
	private function _insertBuyInfo($productId=0) {
		$productId = intval($productId);
		$data = array();
		if (isset($this->_row['base_id'])) {
			$data['base_id'] = $this->_row['base_id'];
		} else {
			$data['base_id'] = $productId;
		}
		if (isset($this->_row['update_id'])) {
			$data['id'] = $this->_row['update_id'];
		}
		$data['site_id'] = $this->_row['site_id'];
		$data['title'] = $this->_row['title'];
		$data['home'] = $this->_row['home'];
		$data['publish_time'] = $this->_row['publish_time'];
		$data['price'] = $this->_row['price'];
		//$data['stock'] = $this->_row['stock'];
		$data['model'] = $this->_row['model'];
		$data['detail'] = $this->_row['detail'];
		$data['from_url'] = $this->_row['from_url'];
		$data['from_id'] = $this->_row['from_id'];

		$data['brand'] = $this->_row['brand'];
		$data['top_category'] = $this->_row['top_category'];
		$data['category'] = $this->_row['category'];
		$data['sub_category'] = $this->_row['sub_category'];
		$data['top_category_id'] = $this->_row['top_category_id'];
		$data['category_id'] = $this->_row['category_id'];
		$data['sub_category_id'] = $this->_row['sub_category_id'];

		$this->_db->row_replace('data_products', $data);
	}
	
	private function _insertImage($productId) {
		$data = array();
		$data['id'] = $productId;
		$iarray = explode(' ', trim($this->_row['image_src']));
		if (empty($iarray)) {
			$data['photo_count'] = 0;
		} else {
			$data['photo_count'] = count($iarray);
		}
		$data['srcs'] = $this->_row['image_src'];
		$this->_db->row_replace('data_photos', $data);
	}

	private function _recognizeEnglishBrand($str) {
		$brandArray = array(
			'NAME'  => '',
			'ENAME' => '',
		);
		$str = str_replace(")", '', $str);
		$str = str_replace("）", '', $str);
		$str = str_replace("(", "#", $str);
		$str = str_replace("（", "#", $str);
		$fields = explode("#", $str);
		if (count($fields) === 2) {
			$brandArray['NAME'] = trim($fields[0]);
			$brandArray['ENAME'] = trim($fields[1]);
		} else {
			$brandArray['NAME'] = trim($str);
		}
		return $brandArray;
	}
	
	private function _writeLog($message) {
		file_put_contents($this->_log, date('Y-m-d H:i:s') . ' ' . $message, FILE_APPEND);
	}
}


$dbConfig["hostname"]    = "127.0.0.1";    //服务器地址
$dbConfig["username"]    = "root";        //数据库用户名
$dbConfig["password"]    = '';        //数据库密码
$dbConfig["database"]    = "spider_data";        //数据库名称
$dbConfig["charset"]     = "gbk";

$newdatalist = '../Newdata/new.txt';
$inprocess = '../Newdata/new.DBI.inp.'.posix_getpid();

if (is_file($newdatalist) && is_readable($newdatalist) && rename($newdatalist, $inprocess)) {
	$importer = new DbImporter($dbConfig, $inprocess);
	$importer->run();
	unset($importer);
	unlink($inprocess);
}
