#!/bin/sh

while [ 1 ]
do
	if [ -f ../Newdata/new.txt ];then
		php DBI.newdata.php
		#exec /bin/sh $0 $@
	fi
	sleep 60;
done
