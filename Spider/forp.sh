#!/bin/sh

while [ 1 ]
do
	#ps -fC php | grep -c PhpRobot.php
	php forParser.php &
	php update.php > ../log/update.log 2>&1
	sleep 300;
done
