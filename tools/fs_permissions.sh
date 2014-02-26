#!/bin/bash

if [ -z $1 ]; then 
    echo "Usage: "$1" /path/to/vtiger"
    exit 1
fi

cd $1

mkdir -p cache/import/ backup modules/Webmails/tmp/
chown root:www-data cache/import/ backup modules/Webmails/tmp

echo "" > config.inc.php # just in case ...

parentdir="$(dirname "$1")"

# this makes the whole directory writeable
# by the webserver. Make shure to correct 
# at least the parent folders permissions 
# or run fs_lock.sh 
chown -R :www-data ../$parentdir
chmod -R g+w,g+x ../$parentdir

