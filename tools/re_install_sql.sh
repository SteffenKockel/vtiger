#!/bin/bash

read -s -p "MySQL password: " PASS


mysql -p$PASS <<EOF
DROP DATABASE IF EXISTS dbvtiger;
CREATE DATABASE dbvtiger DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;
GRANT ALL ON dbvtiger.* to 'dbvtiger'@'localhost' IDENTIFIED BY 'VtiG3rSDB1';
EOF

if [ -z $1 ]; then
    echo "INFO: Skipped injecting migration source. Give a path"
    echo "      as first argument. Like:"
    echo " $0 /here/is/my/gigration_source.sql"
    exit 0
fi

mysql -p$PASS dbvtiger < $1



