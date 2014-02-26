#!/bin/bash

#rm -fR vtiger5.4.0
#svn checkout svn://svn.code.sf.net/p/vtigercrm/code/vtigercrm/branches/5.4.0 vtiger5.4.0
WD=`pwd`

if [ -z $1 ]; then 
    echo "Usage: $0 folder"
    exit 1
fi

cd $1 
#svn revert --recursive .
#svn status | grep ^\? | cut -c9- | xargs -d \\n rm -r 
git clean -fxd
git checkout *

mkdir -p cache/import/ backup modules/Webmails/tmp/

cd $WD
./fs_permissions.sh $1
mysql -pkrau5AL < re_install.sql
mysql -u root -pkrau5AL dbvtiger < /home/walter/vtiger.sql 

