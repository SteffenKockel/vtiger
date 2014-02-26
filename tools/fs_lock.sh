#!/bin/bash

if [ -z $1 ]; then
    echo "Usage: $0 folder"
    exit 1
fi

cd $1

parentdir="$(dirname "$1")"

chown -R root:www-data ../$parentdir
find -type d | xargs chmod 750
find -type f | xargs chmod 740

chmod -R g+w config.inc.php \
tabdata.php \
install.php \
parent_tabdata.php \
cache \
cache/images \
cache/import \
logs \
storage \
user_privileges \
Smarty/cache \
Smarty/templates_c \
Smarty/templates/modules \
modules/Emails/templates \
modules cron/modules \
test/vtlib backup \
test/templates_c \
test/wordtemplatedownload \
test/product \
test/user \
test/contact \
test/logo \
modules/Webmails/tmp
