#!/bin/sh

while [ 1 ]
do
	#ps -fC php | grep -c PhpRobot.php
	php PhpRobot.php standalone >../log/spider.log 2>&1
	sleep 61
done
