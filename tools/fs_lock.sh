#!/bin/bash

if [ -z $1 ]; then
    echo "Usage: $0 folder"
    exit 1
fi

cd $1
chmod -R a-r,a-x,a-w install install.php
chmod -R g-w \
vtlib \
jscalendar

for i in `find . -type d -name .svn`; do 
    chown -R root:root $i
    chmod -R 550 $i
done

