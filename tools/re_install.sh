#!/bin/bash

WD=`pwd`

if [ -z $1 ]; then 
    echo "Usage: $0 folder [vTigerDB]"
    exit 1
fi

cd $1 

git clean -fxd
git checkout *

cd $WD
./fs_permissions.sh $1
./re_install_sql.sh $2 

