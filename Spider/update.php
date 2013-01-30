<?php
require_once "PhpRobot.php";
function load_status() {
	$handle = fopen('data/status.txt', 'r');
	if (!$handle) {
		echo 'Can not open newdata list file <data/status.txt> for read!', chr(10);
		return false;
	}
	$status = array();
	while ( !feof($handle) ) {
		$line = trim(fgets($handle, 4096));
		if (isset($line[0]) && $line[0] == '#') {
			continue;
		}
		$p = explode(' ', $line, 3);
		if (!isset($p[2])) {
			continue;
		}
		if ($p[0] == 'USE') {
			$status['USE'][intval($p[1])] = $p[2];
		} else if ($p[0] == 'LAST'){
			$status['LAST'][intval($p[1])] = $p[2];
		}
	}
	fclose($handle);
	return $status;
}

function update_status($status) {
	//$status['USE'] $status['LAST']
	$spider = new PhpRobot;
	$cur_time = time();
	foreach(glob('data/empty.*', GLOB_NOSORT) as $file) {
		$site_id = intval(substr($file, 11));
		$tm = intval(file_get_contents($file));
		$exp_hour = intval(($cur_time - $tm) / 3600);
		if ($exp_hour < $spider->getConfig()->page_expires) {
			continue;
		}
		$spider->queue_copy_from_entry($site_id);
		$spider->prepare_update($site_id);
		if (isset($status['LAST'][$site_id])) {
			$status['USE'][$site_id] = $tm - intval($status['LAST'][$site_id]);
			$status['LAST'][$site_id] = $tm;
		} else {
			$status['LAST'][$site_id] = $tm;
			$status['USE'][$site_id] = 0;
		}
		unlink($file);
	}
	file_put_contents('data/status.txt', '#spider_time_info'.chr(10));
	file_put_contents('data/status.txt', '#USE'.chr(10), FILE_APPEND);
	foreach($status['USE'] as $site_id=>$t) {
		file_put_contents('data/status.txt', 'USE '.$site_id.' '.$t.chr(10), FILE_APPEND);
	}
	
	file_put_contents('data/status.txt', '#LAST'.chr(10), FILE_APPEND);
	foreach($status['LAST'] as $site_id=>$t) {
		file_put_contents('data/status.txt', 'LAST '.$site_id.' '.$t.chr(10), FILE_APPEND);
	}
}

$tinfo = load_status();
update_status($tinfo);
