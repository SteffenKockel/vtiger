#!/bin/bash

$WEBSERVER="nginx"
$PHP="php-fpm"
$MOUNT="/var/www/vtiger"

service $WEBSERVER stop
service $PHP stop

umount $MOUNT
mount $MOUNT 

service $WEBSERVER start
service $PHP start

ntpdate time.fu-berlin.de
