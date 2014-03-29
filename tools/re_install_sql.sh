#!/bin/bash

if [ -z "$1" ] || [ -z "$2" ]; then
    echo "Usage:"
    echo "$0 dbname dbpass"
    exit 1
fi

read -s -p "MySQL password: " PASS


mysql -p$PASS <<EOF
DROP DATABASE IF EXISTS $1;
CREATE DATABASE $1 DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;
GRANT ALL ON dbvtiger.* to '$1'@'localhost' IDENTIFIED BY '$2';
EOF

echo "..."
echo "INFO: Successfully created DB $1 with user $1 and given pw"


if [ -z $3 ]; then
    echo "INFO: Skipped injecting migration source."
    echo "      Give a path as first argument. Like:"
    echo "      $0 dbname password migration_src.sql"
    exit 0
fi

mysql -p$PASS $1 < $3



