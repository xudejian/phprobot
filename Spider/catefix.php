<?php
class PhpSpiderTmpJob {
	private $db;
	
	public function __construct() {
		$this->db = new PDO( 
			'mysql:host=localhost;dbname=spider_data', 
			'root',//username
			'',//password
			array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES GBK",
		PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true) 
		);
	}

	public function __destruct(){
		$this->db = null;
	}

	public function insert_cate1($name) {
		$sth = $this->db->prepare('INSERT INTO data_categories(name,tree_level,parent_id) values(?,0,0)');
		$sth->bindParam(1, $name);
		if ($sth->execute() === FALSE) {
			echo __LINE__,':PDOStatement::errorInfo():',chr(10);
			die(print_r($sth->errorInfo(), true));
		}
		$rowCount = $sth->rowCount();
		if ($rowCount < 1) {
			die('row count < 1'.chr(10));
		}
		$sth = null;
		return $this->db->lastInsertId();
	}
	
	public function insert_cate2($id, $name) {
		$sth = $this->db->prepare('INSERT INTO data_categories(name,tree_level,parent_id) values(?,1,?)');
		$sth->bindParam(1, $name);
		$sth->bindParam(2, $id);
		if ($sth->execute() === FALSE) {
			echo __LINE__,':PDOStatement::errorInfo():',chr(10);
			die(print_r($sth->errorInfo(), true));
		}
		$rowCount = $sth->rowCount();
		if ($rowCount < 1) {
			die('row count < 1'.chr(10));
		}
		$sth = null;
		return $this->db->lastInsertId();
	}
	
	public function insert_cate3($id, $name) {
		$sth = $this->db->prepare('INSERT INTO data_categories(name,tree_level,parent_id) values(?,2,?)');
		$sth->bindParam(1, $name);
		$sth->bindParam(2, $id);
		if ($sth->execute() === FALSE) {
			echo __LINE__,':PDOStatement::errorInfo():',chr(10);
			die(print_r($sth->errorInfo(), true));
		}
		$rowCount = $sth->rowCount();
		if ($rowCount < 1) {
			die('row count < 1'.chr(10));
		}
		$sth = null;
		return $this->db->lastInsertId();
	}
	
	public function fix_cate($id1, $id2, $id3, $id) {
		$sth = $this->db->prepare('update data_products set top_category_id=?,category_id=?,sub_category_id=? where id=?');
		$sth->bindParam(1, $id1);
		$sth->bindParam(2, $id2);
		$sth->bindParam(3, $id3);
		$sth->bindParam(4, $id);
		if ($sth->execute() === FALSE) {
			echo __LINE__,':PDOStatement::errorInfo():',chr(10);
			die(print_r($sth->errorInfo(), true));
		}
		$sth = null;
	}

	public function check() {
		$this->db->exec('truncate data_categories');
		#$this->db->exec('update data_products b, data_base_products a where a.top_category=b.top_category,a.category=b.category,a.sub_category=b.sub_category where a.id=b.base_id');
		$sql = 'select top_category,category,sub_category from data_products';
		$cate = array();
		$sth = $this->db->query($sql);
		while ($row = $sth->fetch()) {
			$cate[$row['top_category']][$row['category']][$row['sub_category']] = 0;
		}
		$sth = null;
		$t1 = array();
		$t2 = array();
		$t3 = array();
		foreach ($cate as $top=>$topcat) {
			$topid = $this->insert_cate1($top);
			$t1[$top] = $topid;
			foreach ($topcat as $cate =>$category) {
				$id = $this->insert_cate2($topid, $cate);
				$t2[$cate] = $id;
				foreach ($category as $sub=>$subv) {
					$subid = $this->insert_cate3($id, $sub);
					$t3[$sub] = $subid;
					echo $topid,' ',$top,' ',$id,' ',$cate,' ',$subid,' ',$sub,chr(10);
				}
			}
		}
		$sql = 'select id,top_category,category,sub_category from data_products';
		$sth = $this->db->query($sql);
		while ($row = $sth->fetch()) {
			$this->fix_cate($t1[$row['top_category']], $t2[$row['category']], $t3[$row['sub_category']], $row['id']);
		}
		$sth = null;
	}
}

#$job = new PhpSpiderTmpJob;
#$job->check();

