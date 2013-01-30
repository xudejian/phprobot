<?php

error_reporting(E_ALL);
set_time_limit(0);
date_default_timezone_set('Asia/Chongqing');

define('APP_HOME', dirname(dirname(__FILE__)) . '/');

$spiderCfg = array(
    'depth'  => 2,
    'wait'   => 3 * 1000000,
);

$dbConfig["hostname"]    = "127.0.0.1";    //服务器地址
$dbConfig["username"]    = "root";        //数据库用户名
$dbConfig["password"]    = "";        //数据库密码
$dbConfig["database"]    = "spider_data";        //数据库名称
$dbConfig["charset"]        = "gbk";
