#!/bin/bash

############################################
#					   #
#  D A N G E R !   			   #
#					   #
# this makes the whole directory writeable #
# by the webserver. Make shure to correct  #
# at least the parent folders permissions  #
# or run fs_lock.sh 			   #
#					   #
############################################

if [ -z $1 ]; then 
    echo "Usage: "$1" /path/to/vtiger [webserver-runuser]"
    exit 1
fi

WWWUSER="$2"
if [ -z $2 ]; then
    WWWUSER="www-data"
fi
   

cd $1

mkdir -p cache/import/ backup modules/Webmails/tmp/

echo "" > config.inc.php # just in case ...

chown -R $WWWUSER:$WWWUSER $1
chmod -R 777 $1

