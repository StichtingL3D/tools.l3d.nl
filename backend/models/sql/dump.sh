#!/bin/bash

scriptdir=`dirname $0`

read -s -p "MySQL root password: " mysqlrootpass
echo

echo Dumping main tables..
for tables in forms sessions users
do
	echo - $tables
	mysqldump -u root -p$mysqlrootpass --compact --no-data l3dtools $tables | sed 's/AUTO_INCREMENT=[0-9]*\b//' > $scriptdir/$tables.sql
done

#echo Dumping relation tables..
#for relations in rel_x_y
#do
#	echo - $relations
#	mysqldump -u root -p$mysqlrootpass --compact --no-data l3dtools $relations | sed 's/AUTO_INCREMENT=[0-9]*\b//' > $scriptdir/$relations.sql
#done

echo Done
